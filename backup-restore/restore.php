<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);



$HOME = getenv('HOME');

$SITES = '/opt/app-root/src/html/sites';


$s3ini = parse_ini_file("$HOME/.s3cfg", false, INI_SCANNER_RAW);

$bucket = $s3ini['host_bucket'];



if (empty($bucket)) {

    echo "No S3 bucket defined!\n";

    exit;

}

// store database credentials from settings.php

if (file_exists("$SITES/default/settings.php")) {

    $settings_exists = TRUE;

    require "$SITES/default/settings.php";

    $db_name = $databases['default']['default']['database'];

    $db_user = $databases['default']['default']['username'];

    $db_pass = $databases['default']['default']['password'];

    $db_host = $databases['default']['default']['host'];

    $db_port = $databases['default']['default']['port'];

}

else {

    $settings_exists = FALSE;

}


// create .pgpass file

$pgpass = "$db_host:$db_port:$db_name:$db_user:$db_pass";

if ($fp = fopen("$HOME/.pgpass", "w")) {

  fwrite($fp, "$pgpass\n");

  fclose($fp);

  chmod("$HOME/.pgpass", 0600);

}


// cleanup

`rm -f *.db.tar`;

`rm -rf sites`;


// store 5 most recent backups from s3 bucket in array

$s3_url = "s3://ised-s3-676567182757-drupal-custom-backup";

$backup_files = explode("\n", rtrim(`s3cmd ls $s3_url | tail -5`));


// print backup file names

foreach ($backup_files as $n => $file) {

    echo ($n+1) . '. ' . "$file\n";

}


// prompt user for backup file number

do {

    echo "Backup to restore (enter number): ";

    $input = fgets(STDIN);

} while ($input < 1 || $input > 5);


// get backup file from s3 bucket

$index = $input - 1;

preg_match('(s3:[a-z0-9\-./]+)', "$backup_files[$index]", $backup_url);

$cmd = "s3cmd get $backup_url[0]";

echo "$cmd\n";

`$cmd`;


// unzip backup file

$backup_file = shell_exec('find -name "*.tar.gz"');

$cmd = "gunzip -f $backup_file";

echo "$cmd\n";

`$cmd`;

$backup_file = preg_replace('/\.gz$/', '', $backup_file);

$cmd = "tar xf $backup_file";

echo "$cmd\n";

`$cmd`;


// locate database dump

$db_dump = shell_exec('find -name "*.db.tar"');


// restore database dump

$cmd = "pg_restore -h $db_host -U $db_user -d $db_name -F t -c $db_dump";

echo "$cmd\n";

`$cmd`;


// remove compressed backup file

`rm $backup_file`;


// move sites directory into place

`chmod -R ug+w /drupal/sites`;

$cmd = "cp -rp /opt/backup/sites/* $SITES/";

echo "$cmd\n";

`$cmd`;


// replace settings.php database credentials prompt

if ($settings_exists) {

    do {

        echo "Replace settings.php database credentials with those that existed before restore (y/n)? ";

        $input = trim(fgets(STDIN));

    } while ($input != 'y' && $input != 'Y' && $input != 'n' && $input != 'N' );

    if ($input == 'y' || $input == 'Y') {

        $db = array (
            'database' => $db_name,
            'username' => $db_user,
            'password' => $db_pass,
            'prefix' => '',
            'host' => $db_host,
            'port' => $db_port,
            'namespace' => 'Drupal\\Core\\Database\\Driver\\pgsql',
            'driver' => 'pgsql'
        );

        /*

        // replace settings.php with default.settings.php

        $cmd = "cp -p /drupal/sites/default/default.settings.php /drupal/sites/default/settings.php";

        echo "$cmd\n";

        `$cmd`;

        */

        // append database credentials to settings.php (TODO - replace)

        if ($fp = fopen("$SITES/default/settings.php", "a+")) {

            fwrite($fp, '$databases[\'default\'][\'default\'] = ' . var_export($db, true) . ';');

            fclose($fp);

            chmod("$SITES/default/settings.php", 0644);

        }

        echo("Credentials set.\n");

    }
}

echo "Restore complete.\n";


// remove the .pgpass file

unlink("$HOME/.pgpass");
