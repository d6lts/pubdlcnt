<?php

/**
 * @file
 * A sample template file showing how to customize pubdlcnt's display.
 *
 * Copyright 2009 Hideki Ito <hide@pixture.com> Pixture Inc.
 * See LICENSE.txt for licensing terms.
 */

/**
 * Customize download counter display.
 *
 * @param array $variables
 *   Array of counter variables with elements type, value, and path.
 *     type -- either 'node' (including Views field if one
 *       exists) or 'block'.
 *     value -- value of the counter.
 *     path -- path to the statistics page, if allowed by permissions.
 */
function phptemplate_pubdlcnt_counter(array $variables) {

  $type = $variables['type'];
  $value = $variables['value'];
  $path = $variables['path'];

  if ($type == 'node') {
    if ($path) {
      $output = ' <a href="' . $path . '">(' . $value . ' downloads)</a>';
    }
    else {
      $output = ' (' . $value . ' downloads)';
    }
  }
  elseif ($type == 'block') {
    $output = '<br>';
    if ($path) {
      $output .= ' <a href="' . $path . '">Total ' . $value . ' downloads</a>';
    }
    else {
      $output .= ' Total ' . $value . ' downloads';
    }
  }
  return $output;
}
