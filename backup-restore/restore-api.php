<?php

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
  exit;
}

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$HOME = getenv('HOME');
$SITES = "$HOME/html/sites";

$pgpassfile = "$HOME/.pgpass";
$s3cfgfile = "$HOME/.s3cfg";

putenv("PGPASSFILE=$pgpassfile");

$access_key = $_POST['access_key'];
$secret_key = $_POST['secret_key'];
$bucket_location = $_POST['bucket_location'];
$host_base = $_POST['host_base'];
$host_bucket = $_POST['host_bucket'];
$backup_file = $_POST['backup_file'];

$s3data = file_get_contents('/opt/backup/s3cfg.template');
$s3data = str_replace('__ACCESS_KEY__', $access_key, $s3data);
$s3data = str_replace('__SECRET_KEY__', $secret_key, $s3data);
$s3data = str_replace('__BUCKET_LOCATION__', $bucket_location, $s3data);
$s3data = str_replace('__HOST_BASE__', $host_base, $s3data);
$s3data = str_replace('__HOST_BUCKET__', $host_bucket, $s3data);
file_put_contents($s3cfgfile, $s3data);
chmod($s3cfgfile, 0600);

// enter maintenance mode
$json['messages'][] = "Entering maintenance mode...\n";
exec("$HOME/vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer");
exec("$HOME/vendor/bin/drush cr");

require "$SITES/default/settings.php";

$db_name = $databases['default']['default']['database'];
$db_user = $databases['default']['default']['username'];
$db_pass = $databases['default']['default']['password'];
$db_host = $databases['default']['default']['host'];
$db_port = $databases['default']['default']['port'];


// create .pgpass file
$pgpass = "$db_host:$db_port:$db_name:$db_user:$db_pass";
if ($fp = fopen($pgpassfile, "w")) {
  fwrite($fp, "$pgpass\n");
  fclose($fp);
  chmod($pgpassfile, 0600);
}

chdir('/tmp');

// get most recent backup file from s3 bucket
$cmd = "s3cmd get s3://$host_bucket/$backup_file";
$json['messages'][] = $cmd;
`$cmd`;


// unzip backup file
//$backup_file = shell_exec('find -name "*.tar.gz"');
$cmd = "gunzip -f $backup_file";
$json['messages'][] = $cmd;
`$cmd`;

$backup_file = preg_replace('/\.gz$/', '', $backup_file);
$cmd = "tar xf $backup_file";
$json['messages'][] = $cmd;
`$cmd`;

// locate database dump
$db_dump = shell_exec('find -name "*.db.tar"');

// restore database dump
$cmd = "pg_restore --no-privileges --no-owner -h $db_host -U $db_user -d $db_name -F t -c $db_dump";
$json['messages'][] = $cmd;
`$cmd`;

// move sites directory into place
`chmod -R ug+w $SITES/`;
$cmd = "cp -rp sites/* $SITES/";
$json['messages'][] = $cmd;
`$cmd`;

// cleanup
$cmd = "rm $backup_file";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "rm -rf sites";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "rm *.db.tar";
$json['messages'][] = $cmd;
`$cmd`;

// replace settings.php database credentials with those
// that existed before restore
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

chmod("$SITES/default/settings.php", 0775);
if ($fp = fopen("$SITES/default/settings.php", "a+")) {
    fwrite($fp, '$databases[\'default\'][\'default\'] = ' . var_export($db, true) . ';');
    fclose($fp);
}
chmod("$SITES/default/settings.php", 0644);

unlink($pgpassfile);
unlink($s3cfgfile);

// exit maintenance mode
$json['messages'][] = "Exiting maintenance mode...\n";
exec("$HOME/vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer");
exec("$HOME/vendor/bin/drush cr");

header('Content-type: application/json; charset=utf-8');
echo json_encode($json);