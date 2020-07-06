<?php

ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

$HOME = getenv('HOME');
$SITES = "$HOME/html/sites";

putenv("PGPASSFILE=$HOME/.pgpass");

// enter maintenance mode
echo "Entering maintenance mode...\n";
exec("$HOME/vendor/bin/drush state:set system.maintenance_mode 1 --input-format=integer");
exec("$HOME/vendor/bin/drush cr");

// check for s3 credentials configuration file
$s3ini = parse_ini_file("$HOME/.s3cfg", false, INI_SCANNER_RAW);
$bucket = $s3ini['host_bucket'];
if (empty($bucket)) {
    echo "No S3 bucket defined!\n";
    exit;
}

require "$SITES/default/settings.php";

$db_name = $databases['default']['default']['database'];
$db_user = $databases['default']['default']['username'];
$db_pass = $databases['default']['default']['password'];
$db_host = $databases['default']['default']['host'];
$db_port = $databases['default']['default']['port'];

// create .pgpass file
$pgpass = "$db_host:$db_port:$db_name:$db_user:$db_pass";
if ($fp = fopen("$HOME/.pgpass", "w")) {
  fwrite($fp, "$pgpass\n");
  fclose($fp);
  chmod("$HOME/.pgpass", 0600);
}

$dtm = date('Y-m-d-H-i');
$dbbackup = "$db_name.$dtm.db.tar";
$tarfile = "drupal-$dtm.tar";

// Dump using tar format (-F t)
$cmd = "pg_dump -U $db_user -h $db_host -p $db_port -x -F t $db_name > /tmp/$dbbackup";
echo "$cmd\n";
`$cmd`;

chdir('/tmp');
$cmd = "tar cf /tmp/$tarfile $dbbackup";
echo "$cmd\n";
`$cmd`;

chdir("$SITES/..");
$cmd = "tar rf /tmp/$tarfile sites";
echo "$cmd\n";
`$cmd`;

$cmd = "gzip -f /tmp/$tarfile";
echo "$cmd\n";
`$cmd`;

$cmd = "s3cmd -q --mime-type=application/x-gzip put /tmp/$tarfile.gz s3://$bucket/$tarfile.gz";
echo "$cmd\n";
`$cmd`;

$cmd = "rm -f /tmp/$dbbackup";
echo "$cmd\n";
`$cmd`;

$cmd = "rm -f /tmp/$tarfile.gz";
echo "$cmd\n";
`$cmd`;

// Remove the pgpass file
unlink("$HOME/.pgpass");

// exit maintenance mode
echo "Exiting maintenance mode...\n";
exec("$HOME/vendor/bin/drush state:set system.maintenance_mode 0 --input-format=integer");
exec("$HOME/vendor/bin/drush cr");

