<?php

/**
 * @file
 * File download external script.
 *
 * @ingroup pubdlcnt
 *
 * Usage:  pubdlcnt.php?fid={file_id}
 *
 * NOTE: we can not use variable_get() function from this external PHP program
 *   since variable_get() depends on Drupal's internal global variable.
 *   So we need to directly access {variable} table of the Drupal databse
 *   to obtain some module settings.
 *
 * Copyright 2016 Corey Halpin <chalpin@scout.wisc.edu>
 * Copyright 2009 Hideki Ito <hide@pixture.com> Pixture Inc.
 * See LICENSE.txt for licensing terms.
 */

// Step-1: start Drupal's bootstrap to use drupal database
// and includes necessary drupal files:
$current_dir = getcwd();

// We need to change the current directory to the (drupal-root) directory
// in order to include some necessary files.
if (file_exists('../../../../includes/bootstrap.inc')) {
  // If  this  script  is in  the  (drupal-root)/sites/(site)/modules/pubdlcnt
  // directory, go to drupal root:
  chdir('../../../../');
}
elseif (file_exists('../../includes/bootstrap.inc')) {
  // If this script is in the (drupal-root)/modules/pubdlcnt directory,
  // go to drupal root:
  chdir('../../');
}
else {
  // Non standard location: you need to edit the line below so that chdir()
  // command change the directory to the drupal root directory of your server
  // using an absolute path.
  // First, please delete the line below and then edit the next line.
  print "Error: Public Download Count module failed to work. The file pubdlcnt.php requires manual editing.\n";
  chdir('/absolute-path-to-drupal-root/');

  if (!file_exists('./includes/bootstrap.inc')) {
    exit;
  }
}
define('DRUPAL_ROOT', realpath(getcwd()));
include_once DRUPAL_ROOT . '/includes/bootstrap.inc';
// Following two lines are needed for check_url() and valid_url() call:
include_once DRUPAL_ROOT . '/includes/common.inc';
include_once DRUPAL_ROOT . '/modules/filter/filter.module';

// Start Drupal bootstrap for accessing database:
drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
chdir($current_dir);

// Step 2: Get file query value (fid of the file todownload)
if (!isset($_GET["fid"])) {
  header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
  print "<pre>ERROR: no file specified for donwload.</pre>";
  exit;
}

// Check that the fid given is valid:
$rec = db_query(
    "SELECT * FROM {pubdlcnt} WHERE fid=:fid",
    [':fid' => $_GET["fid"]]
)->fetchObject();
if ($rec === FALSE) {
  header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
  print "<pre>ERROR: invalid fid provided.</pre>";
  exit;
}

$url = $rec->url;
$nid = $rec->nid;

// Is this an absolute url?
if (!preg_match("%^(f|ht)tps?://.*%i", $url)) {
  // If the URL is relative, then convert it to absolute:
  $url = "http://" . $_SERVER['SERVER_NAME'] . $url;
}

// Step 3: Check that the URL is valid:
if (!pubdlcnt_is_valid_file_url($url)) {
  header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
  print "<pre>ERROR: Invalid download url.</pre>";
  exit;
}

// Step 4: If this is an external link and referer is also external, refuse to
// redirect to prevent an open redirect vulnerability.
$tgt_domain = parse_url($url, PHP_URL_HOST);
$referer = isset($_SERVER["HTTP_REFERER"]) ?
    parse_url($_SERVER["HTTP_REFERER"], PHP_URL_HOST) :
    FALSE;
if ($tgt_domain != $_SERVER['SERVER_NAME'] &&
    $referer != $_SERVER['SERVER_NAME']) {
  header($_SERVER["SERVER_PROTOCOL"] . " 400 Bad Request");
  print "<pre>Refusing to redirect to external site.</pre>";
  exit;
}

// Step 5: At this point, request must be valid. Update counter data.
pubdlcnt_update_counter($rec->fid);

// Step 6: redirect to the original URL of the file:
header('Cache-Control: max-age=0');
header('Location: ' . $url);

/**
 * Function to check if the specified file URL is valid or not.
 *
 * @param string $url
 *   Url to check.
 *
 * @return bool
 *   TRUE for valid files, FALSE otherwise.
 */
function pubdlcnt_is_valid_file_url(string $url) {
  // Replace space characters in the URL with '%20' to support file name
  // with space characters:
  $url = preg_replace('/\s/', '%20', $url);
  if (!valid_url($url, TRUE)) {
    return FALSE;
  }

  // URL end with slach (/) and no file name:
  if (preg_match('/\/$/', $url)) {
    return FALSE;
  }

  // In case of FTP, we just return TRUE (the file exists):
  if (preg_match('/ftps?:\/\/.*/i', $url)) {
    return TRUE;
  }

  // Extract file name and extension:
  $filename = basename($url);
  $extension = explode(".", $filename);

  // File name does not have extension:
  if (($num = count($extension)) <= 1) {
    return FALSE;
  }
  $ext = $extension[$num - 1];

  // Get valid extensions settings from Drupal:
  $result = db_query("SELECT value FROM {variable}
                      WHERE name = :name", array(':name' => 'pubdlcnt_valid_extensions'))->fetchField();
  $valid_extensions = unserialize($result);
  if (!empty($valid_extensions)) {
    // Check if the extension is a valid extension or not (case insensitive):
    $s_valid_extensions = strtolower($valid_extensions);
    $s_ext = strtolower($ext);
    $s_valid_ext_array = explode(" ", $s_valid_extensions);
    if (!in_array($s_ext, $s_valid_ext_array)) {
      return FALSE;
    }
  }

  // Check if url exists:
  $result = drupal_http_request($url, array("method" => "HEAD"));
  if ($result->code != 200) {
    return FALSE;
  }

  // It seems that the file URL is valid:
  return TRUE;
}

/**
 * Function to check duplicate download from the same IP address within a day.
 *
 * @param int $fid
 *   Id of file being downloaded.
 *
 * @return int
 *   0 - OK,  1 - duplicate (skip counting)
 */
function pubdlcnt_check_duplicate(int $fid) {
  // Get the settings:
  $result = db_query("SELECT value FROM {variable} WHERE name = :name",
                     array(':name' => 'pubdlcnt_skip_duplicate'))->fetchField();
  $skip_duplicate = unserialize($result);
  if (!$skip_duplicate) {
    return 0;
  }

  // OK, we should check the duplicate download:
  $ip = filter_var(ip_address(), FILTER_VALIDATE_IP);
  if ($ip === FALSE) {
    // Invalid IPv4 address:
    return 1;
  }

  // Unix timestamp:
  $today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

  $result = db_query(
      "SELECT * FROM {pubdlcnt_ip} WHERE fid=:fid AND ip=:ip AND utime=:utime", [
        ':fid' => $fid,
        ':ip' => $ip,
        ':utime' => $today,
      ]);
  if ($result->rowCount()) {
    // Found duplicate!
    return 1;
  }

  // Add IP address to the database:
  db_insert('pubdlcnt_ip')
    ->fields([
      'fid' => $fid,
      'ip' => $ip,
      'utime' => $today,
    ])->execute();

  return 0;
}

/**
 * Function to update the data base with new counter value.
 *
 * @param int $fid
 *   Id of file being downloaded.
 */
function pubdlcnt_update_counter(int $fid) {
  // Check the duplicate download from the same IP and skip updating counter:
  if (pubdlcnt_check_duplicate($fid)) {
    return;
  }

  db_update('pubdlcnt')
    ->expression('count', 'count + 1')
    ->condition('fid', $fid)
    ->execute();

  // Get the settings:
  $result = db_query(
      "SELECT value FROM {variable} WHERE name=:name",
      [':name' => 'pubdlcnt_save_history']
  )->fetchField();
  $save_history = unserialize($result);

  if ($save_history) {
    $today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

    db_merge('pubdlcnt_history')
      ->key(['fid' => $fid, 'utime' => $today])
      ->fields(['count' => 1])
      ->expression('count', 'count + 1')
      ->execute();
  }
}
