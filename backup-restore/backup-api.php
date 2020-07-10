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
$app_name = $_POST['app_name'];

$s3data = file_get_contents('/opt/backup/s3cfg.template');
$s3data = str_replace('__ACCESS_KEY__', $access_key, $s3data);
$s3data = str_replace('__SECRET_KEY__', $secret_key, $s3data);
$s3data = str_replace('__BUCKET_LOCATION__', $bucket_location, $s3data);
$s3data = str_replace('__HOST_BASE__', $host_base, $s3data);
$s3data = str_replace('__HOST_BUCKET__', $host_bucket, $s3data);
file_put_contents($s3cfgfile, $s3data);
chmod($s3cfgfile, 0600);

// enter maintenance mode
$json['messages'][] = "Entering maintenance mode...";
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

$dtm = date('Y-m-d-H-i');
$dbbackup = "$db_name.$dtm.db.tar";
$tarfile = "$app_name/$dtm.tar";

// Dump using tar format (-F t)
$cmd = "pg_dump -U $db_user -h $db_host -p $db_port -x -F t $db_name > /tmp/$dbbackup";
$json['messages'][] = $cmd;
`$cmd`;

chdir('/tmp');
$cmd = "tar cf /tmp/$tarfile $dbbackup";
$json['messages'][] = $cmd;
`$cmd`;

chdir("$SITES/..");
$cmd = "tar rf /tmp/$tarfile sites";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "gzip -f /tmp/$tarfile";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "s3cmd -q --mime-type=application/x-gzip put /tmp/$tarfile.gz s3://$host_bucket/$tarfile.gz";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "rm -f /tmp/$dbbackup";
$json['messages'][] = $cmd;
`$cmd`;

$cmd = "rm -f /tmp/$tarfile.gz";
$json['messages'][] = $cmd;
`$cmd`;

unlink($pgpassfile);
unlink($s3cfgfile);

// exit maintenance mode
$json['messages'][] = "Exiting maintenance mode...";
exec("$HOME/vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer");
exec("$HOME/vendor/bin/drush cr");

header('Content-type: application/json; charset=utf-8');
echo json_encode($json);
