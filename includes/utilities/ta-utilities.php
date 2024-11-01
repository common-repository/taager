<?php

defined( 'ABSPATH' ) || exit;

function taager_get_option_time_diff_in_minutes($target_date) {
  $target_timestamp = strtotime($target_date);
  $current_timestamp = time();
  $time_difference = $current_timestamp - $target_timestamp;
  return round($time_difference / 60);
}
