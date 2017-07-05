<?php

/**
 * @file
 * File download external script.
 *
 * @ingroup pubdlcnt
 *
 * Usage:  pubdlcnt.php?file=http://server/path/file.ext
 *
 * Requirement: PHP5 - get_headers() function is used
 *              (The script works fine with PHP4 but better with PHP5)
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
    // We can not locate the bootstrap.inc file, let's give up using the
    // script and just fetch the file:
    header('Location: ' . $_GET['file']);
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

// Step 2: Get file query value (URL of the actual file to be downloaded.
$url = check_url($_GET['file']);
$nid = check_url($_GET['nid']);

// Is this an absolute url?
if (!preg_match("%^(f|ht)tps?://.*%i", $url)) {
  // If the URL is relative, then convert it to absolute:
  $url = "http://" . $_SERVER['SERVER_NAME'] . $url;
}

// Step 3: Check that the URL is valid:
if (pubdlcnt_is_valid_file_url($url)) {
  // Step 4: update counter data if URL was valid and file exists:
  $filename = basename($url);
  pubdlcnt_update_counter($url, $filename, $nid);

  // Step 5: redirect to the original URL of the file:
  header('Location: ' . $url);
}
else {
  print "<pre>ERROR: Invalid download link.</pre>";
}

exit;

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
 * @param string $url
 *   Url to check.
 * @param string $name
 *   Name of file being downloaded.
 * @param int $nid
 *   Id of node where download started.
 *
 * @return int
 *   0 - OK,  1 - duplicate (skip counting)
 */
function pubdlcnt_check_duplicate(string $url, string $name, int $nid) {

  // Get the settings:
  $result = db_query("SELECT value FROM {variable} WHERE name = :name",
                     array(':name' => 'pubdlcnt_skip_duplicate'))->fetchField();
  $skip_duplicate = unserialize($result);
  if (!$skip_duplicate) {
    return 0;
  }

  // OK, we should check the duplicate download:
  $ip = ip_address();
  if (!preg_match("/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $ip)) {
    // Invalid IPv4 address:
    return 1;
  }
  // Unix timestamp:
  $today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

  // Obtain fid:
  $fid = db_query("SELECT fid FROM {pubdlcnt} WHERE name=:name", array(':name' => $name))->fetchField();
  if ($fid) {
    $result = db_query("SELECT * FROM {pubdlcnt_ip} WHERE fid=:fid AND ip=:ip AND utime=:utime", array(
      ':fid' => $fid,
      ':ip' => $ip,
      ':utime' => $today,
    ));
    if ($result->rowCount()) {
      // Found duplicate!
      return 1;
    }
    else {
      // Add IP address to the database:
      db_insert('pubdlcnt_ip')
        ->fields(array(
          'fid' => $fid,
          'ip' => $ip,
          'utime' => $today,
        ))->execute();
    }
  }
  else {
    // No file record -> create file record first:
    $fid = db_insert('pubdlcnt')
      ->fields(array(
        'nid' => $nid,
        'name' => $name,
        'url' => $url,
        'count' => 0,
        'utime' => $today,
      ))->execute();
    // Next, add IP address to the database:
    db_insert('pubdlcnt_ip')
      ->fields(array(
        'fid' => $fid,
        'ip' => $ip,
        'utime' => $today,
      ))->execute();
  }
  return 0;
}

/**
 * Function to update the data base with new counter value.
 *
 * @param string $url
 *   Url being downloaded.
 * @param string $name
 *   Name of file being downloaded.
 * @param int $nid
 *   Id of node file was downloaded from.
 */
function pubdlcnt_update_counter(string $url, string $name, int $nid) {
  // Check if nid is invalid:
  if (empty($nid)) {
    return;
  }

  // Check the duplicate download from the same IP and skip updating counter:
  if (pubdlcnt_check_duplicate($url, $name, $nid)) {
    return;
  }

  $count = 1;

  // Today(00:00:00AM) in Unix time:
  $today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));

  // Obtain fid:
  $result = db_query("SELECT fid, count FROM {pubdlcnt} WHERE name=:name", array(':name' => $name));
  if (!$result->rowCount()) {
    // No file record -> create file record first:
    $fid = db_insert('pubdlcnt')
      ->fields(array(
        'nid'   => $nid,
        'name'  => $name,
        'url'   => $url,
        'count' => 1,
        'utime' => $today,
      ))->execute();
  }
  else {
    $rec = $result->fetchObject();
    $fid = $rec->fid;
    // Update total counter:
    $total_count = $rec->count + 1;
    db_update('pubdlcnt')
      ->fields(array(
        'nid'   => $nid,
        'url'   => $url,
        'count' => $total_count,
        'utime' => $today,
      ))->condition('fid', $rec->fid)
      ->execute();
  }

  // Get the settings:
  $result = db_query("SELECT value FROM {variable} WHERE name=:name",
                      array(':name' => 'pubdlcnt_save_history'))->fetchField();
  $save_history = unserialize($result);

  if ($save_history) {
    $count = db_query("SELECT count FROM {pubdlcnt_history} WHERE fid=:fid AND utime=:utime",
                     array(':fid' => $fid, ':utime' => $today))->fetchField();
    if ($count) {
      $count++;
      // Update an existing record:
      db_update('pubdlcnt_history')
        ->fields(array('count' => $count))
        ->condition('fid', $fid)
        ->condition('utime', $today)
        ->execute();
    }
    else {
      // Insert a new record:
      db_insert('pubdlcnt_history')
        ->fields(array(
          'fid' => $fid,
          'utime' => $today,
          'count' => 1,
        ))->execute();
    }
  }
}
