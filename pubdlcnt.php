<?php
// $Id: 

/**
 * @file
 *
 * file download external script
 *
 * @ingroup pubdlcnt
 *
 * Usage:  pubdlcnt.php?file=http://server/path/file.ext
 *
 * Requirement: PHP5 - get_headers() function is used
 *              (The script works fine with PHP4 but better with PHP5)
 *
 * NOTE: we can not use variable_get() function from this external PHP program
 *	     since variable_get() depends on Drupal's internal global variable.
 *       So we need to directly access {variable} table of the Drupal databse 
 *       to obtain some module settings.
 *
 * Copyright 2009 Hideki Ito <hide@pixture.com> Pixture Inc.
 * Distributed under the GPL Licence.
 */

/**
 * Step-1: start Drupal's bootstrap to use drupal database
 *         and includes necessary drupal files
 */

$current_dir = getcwd();

// Change the following line based on the location of this module directory
chdir('../../../../'); // go to drupal root

include_once './includes/bootstrap.inc';
// following two lines are needed for check_url() and valid_url() call
include_once './includes/common.inc';
include_once './modules/filter/filter.module';
// start Drupal bootstrap for accessing database
drupal_bootstrap(DRUPAL_BOOTSTRAP_DATABASE);
chdir($current_dir);

/**
 * Step-2: get file query value (URL of the actual file to be downloaded)
 */
$url = check_url($_GET['file']);
$nid = check_url($_GET['nid']);

if (!eregi("^(f|ht)tps?:\/\/.*", $url)) { // check if this is absolute URL 
  // if the URL is relative, then convert it to absolute
  $url = "http://" . $_SERVER['SERVER_NAME'] . $url;
}

/**
 * Step-3: check if the url is valid or not
 */
if (is_valid_file_url($url)) {
  /**
   * Step-4: update counter data (only if the URL is valid and file exists)
   */
  $filename = basename($url);
  pubdlcnt_update_counter($filename, $nid);
}

/**
 * Step-5: redirect to the original URL of the file
 */
header('Location: ' . $url);
exit;

/**
 * Function to check if the specified file URL is valid or not
 */
function is_valid_file_url($url) {
  if (!valid_url($url, true)) {
    return false;
  }
  // URL end with slach (/) and no file name
  if (preg_match('/\/$/', $url)) {
    return false;
  }

  // extract file name and extention
  $filename = basename($url);
  $extension = explode(".", $filename);
  // file name does not have extension
  if (($num = count($extension)) <= 1) {
    return false;
  }
  $ext = $extension[$num - 1];

  // get valid extensions settings from Drupal
  $result = db_query("SELECT value FROM {variable} 
						WHERE name = 'pubdlcnt_valid_extensions'");
  $valid_extensions = unserialize(db_result($result));
  if (!empty($valid_extensions)) {
    $valid_ext_array = explode(" ", $valid_extensions);
    // invalid extension
    if (!in_array($ext, $valid_ext_array)) {
      return false;
    }
  }

  if (!url_exits($url)) {
    return false;
  }
  return true; // it seems that the file URL is valid
}

/**
 * Function to check if the specified file URL really exists or not
 */
function url_exits($url) {
  if (!function_exists('get_headers')) {
    return true;	// PHP4 
  }
  $header = get_headers($url);
  // Here are popular status code back from the server
  //
  // URL exits              'HTTP/1.1 200 OK'
  // URL does not exits     'HTTP/1.1 404 Not Found'
  // Can not access URL     'HTTP/1.1 403 Forbidden'
   // Can not access server  'HTTP/1.1 500 Internal Server Error
  // 
  // So we return true only when 'HTTP/1.1 200 OK' is returned
  if (strstr($header[0], '200')) {
    return true;
  }
  return false;
}

/**
 * Function to update the data base with new counter value
 */
function pubdlcnt_update_counter($name, $nid) {
  $count = 1;
  $name = db_escape_string($name);	// security purpose

  if (empty($nid)) { // node nid is invalid
    return;
  }
  // today(00:00:00AM) in Unix time
  $today = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
  // convert to datettime format
  $mysqldate = date("Y-m-d H:i:s", $today);

  $result = db_query("SELECT * FROM {pubdlcnt} WHERE name='%s' AND date='%s'", 
                      $name, $mysqldate);
  if ($rec = db_fetch_object($result)) {
    $count = $rec->count + 1;
    // update an existing record
    db_query("UPDATE {pubdlcnt} SET count=%d WHERE name='%s' AND date='%s'", 
			$count, $name, $mysqldate);
  }
  else {
    // insert a new record
    db_query("INSERT INTO {pubdlcnt} (name, nid, date, count) VALUES ('%s', %d, '%s', %d)", 
			$name, $nid, $mysqldate, $count);
  }
}
?>
