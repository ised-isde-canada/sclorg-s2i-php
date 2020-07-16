<?php
/* Backup.php
 * Purpose: Backup a Moodle or Drupal Site.
 * Written by: Duncan Sutter, Samantha Tripp and Michael Milette
 * Date: July 2020
 * License: MIT
 */

$cfg['debug'] = true; // Set to true to enable debugging, otherwise false.
if ($cfg['debug']) {
   ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/**
 * Execute a shell command - with error handling.
 *
 * @param string $cmd The command to be executed.
 * @param string $dir Optional directory from where to execute the command.
 */
function execmd($cmd, $dir = '') {
    $cwd = getcwd();

    // Change into specified directory if specified.
    if (!empty($dir)) {
        chdir($dir);
    }

    echo $cmd . PHP_EOL; // Display it.
    shell_exec($cmd, $output, $err); // Execute it.

    // Restore current directory back to its original state.
    chdir($cwd);

    // Report it - if an error occured, display error message and stop.
    if ($err !== 0) {
        maintmode(false);
        $output = implode("\n", $output);
        sendmail($output);
        die("Error ($errorlevel)\n" . $output);
    }
}

/**
 * Enable or disable maintenance mode. Currently only supports Drupal and Moodle.
 *
 * @param boolean $enable True to enable maintenance mode (default) or false to disable it.
 */
function maintmode($enable = true) {
    global $cfg;

    if ($enable) { // Enable maintenance mode.

        echo "Entering maintenance mode...\n";

        // Set-up tmp folder.
        $cfg['tmpdir'] = sys_get_temp_dir() . '/' . uniqid();
        execmd('md ' . $cfg['tmp']);

        // Create .pgpass file.
        $cfg['pgpassfile'] = $cfg['tmpdir'] . '.pgpass';
        putenv('PGPASSFILE=' . $cfg['pgpassfile']);
        $pgpass = $cfg['dbhost'] . ':' . $cfg['dbport'] . ':' . $cfg['dbname'] . ':' . $cfg['dbuser'] . ':' . $cfg['dbpass'];
        if ($fp = fopen($cfg['pgpassfile'], 'w')) {
            fwrite($fp, "$pgpass\n");
            fclose($fp);
            chmod($cfg['pgpassfile'], 0600);
        }

        switch($cfg['appname']) {
            case 'drupal':
                // Enable maintenance mode in Drupal.
                execmd($cfg['homedir'] . '/vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer');
                // Purge cache.
                execmd($cfg['homedir'] . '/vendor/bin/drush cr');
                break;
            case 'moodle':
                // Enable maintenance mode in Moodle.
                execmd('cp climaintenance.html ' . $cfg['$pvdir'] . '/' . $datadir);
                // Purge cache.
                execmd('/usr/local/bin/php -f admin/cli/purge_caches.php', $homedir);
                break;
        }

    } else { // Disable maintenance mode.

        echo "Exiting maintenance mode...\n";

        switch($cfg['appname']) {
            case 'drupal':
                exec($cfg['$homedir'] . '/vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer');
                break;
            case 'moodle':
                exec('rm ' . $cfg['$pvdir'] . '/' . $datadir . '/climaintenance.html');
        }

        // Clean-up temporary tar, gz and .pgpass files.
        execmd('rm -rf ' . $cfg['tmpdir']);
    }
}

/**
 * Send email notification.
 *
 * @param string 
 */
function sendmail($content) {
    $to = '';
    $from = '';
    $smpt = '';
    $status = false;
    // $status = send($from, $to, $subject, $content $smtp);
    return $status;
}

$cfg['dtm'] = date('Y-m-d-H-i');
$cfg['homedir'] = getenv('HOME'); // Directory of webroot.
$cfg['appname'] = file_exists($cfg['homedir'] . '/config.php') ? 'moodle' : 'drupal';


// Identify application and set application specific configurations.
switch($appname) {
    case 'drupal':
        // Get path to sites folder.
        $cfg['pvdir'] = "$homedir/html/sites";
        $databackup = "sites"; // Persistent volumne backup file.
        break;
    case 'moodle':
        // Get path to moodledata from environment.
        $cfg['pvdir'] = getenv('MODOLE_DATA_DIR');
        $cfg['databackup'] = "moodledata"; // Persistent volumne backup file.
        break;
    default:
        die('Could not identify application.');
}
$s3file = "$appname-" . getenv('OPENSHIFT_BUILD_NAMESPACE') . "-$dtm"; // Compressed gz file to upload into S3.
// Split PV path and data directory name.
$cfg['datadir'] = explode('/', $cfg['pvdir']);
$cfg['datadir'] = end($cfg['datadir']);
$cfg['pvdir'] = substr($cfg['pvdir'], 0, -(strlen($cfg['datadir']) + 1));

// Get settings from OpenShift environment.
$cfg['dbname'] = getenv('DBNAME');     // Database name.
$cfg['dbuser'] = getenv('DBUSERNAME'); // Database username.
$cfg['dbpass'] = getenv('DBPASSWORD'); // Database password.
$cfg['dbhost'] = getenv('DBHOST');     // E.g. 'localhost' or 'db.isp.com' or IP.
$cfg['dbport'] = getenv('DBPASSWORD'); // Database port.

// To be removed once Drupal sites use environment variables.
if (empty($cfg['dbhost']) && $cfg['appname']  == 'drupal') {
    require $cfg['pvdir'] . '/' . $cfg['datadir'] . '/default/settings.php';
    $cfg['dbname'] = $databases['default']['default']['database'];
    $cfg['dbuser'] = $databases['default']['default']['username'];
    $cfg['dbpass'] = $databases['default']['default']['password'];
    $cfg['dbhost'] = $databases['default']['default']['host'];
    $cfg['dbport'] = $databases['default']['default']['port'];
}
if (empty($cfg['dbhost'])) {
    die('Could not retrieve database credentials.');
}
if (empty($cfg['dbport'])) {
    $dbport = '5432';
}

// Start maintenance mode.
maintmode(true);

// Check for s3 credentials configuration file.
$s3ini = parse_ini_file("$homedir/.s3cfg", false, INI_SCANNER_RAW);
$bucket = $s3ini['host_bucket'];
if (empty($bucket)) {
    maintmode(false); // Clean-up and disable maintenance mode.
    die("No S3 bucket defined!\n");
}

// Dump database using tar format (-F t).
execmd('pg_dump -U ' . $cfg['dbuser'] . ' -h ' . $cfg['dbhost'] . ' -p ' . $cfg['dbport'] . ' -x -F t ' . $cfg['dbname'] . ' > ' . $cfg['tmpdir'] . '/' . $cfg['dbname'] . '.sql 2>&1');

// Tar the files in the PV directory.
execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['databackup'] . '.tar ' . $cfg['datadir'] . ' 2>&1', $pvdir);

// Tar the files in the application directory.
execmd('tar rf ' . $cfg['tmpdir'] . '/' . $cfg['appname'] . '.tar src 2>&1', $homedir . '/..');

// GZ compress all of the temporary files so far.
execmd('gzip -f ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . ' 2>&1', $cfg['tmpdir']);

// Copy the compressed gz file to S3.
execmd('s3cmd -q --mime-type=application/x-gzip put ' . $cfg['tmpdir'] . '/' . $cfg['s3file'] . '.gz s3://$bucket/' . $cfg['s3file'] . '.gz 2>&1');

// Exit maintenance mode and clean-up.
maintmode(false);

/* EOF. */