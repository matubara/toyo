<?php

/**
 * @file
 */

function display_timer_statistics($start_timers, $end_timers) {
  // Settings
  $key_width = '100px';
  $decimals = 4;
  $decimals_wallclock = $decimals;
  $decimals_request_time_float = $decimals;

  // Variables
  $start_resource_usage_timer = $start_timers[0];
  $start_wallclock = $start_timers[1];
  $end_resource_usage_timer = $end_timers[0];
  $end_wallclock = $end_timers[1];

  // # User time
  // Add seconds and microseconds for the start/end, and subtract from another
  $end_user_time_seconds = $end_resource_usage_timer['ru_utime.tv_sec'] * 1000;
  $end_user_time_microseconds = intval($end_resource_usage_timer['ru_utime.tv_usec'] / 1000);
  $start_user_time_seconds = $start_resource_usage_timer['ru_utime.tv_sec'] * 1000;
  $start_user_time_microseconds = intval($start_resource_usage_timer['ru_utime.tv_usec'] / 1000);
  $total_user_time = $end_user_time_seconds + $end_user_time_microseconds - ($start_user_time_seconds + $start_user_time_microseconds);

  // # System time
  // Add seconds and microseconds for the start/end, and subtract from another
  $end_system_time_seconds = $end_resource_usage_timer['ru_stime.tv_sec'] * 1000;
  $end_system_time_microseconds = intval($end_resource_usage_timer['ru_stime.tv_usec'] / 1000);
  $start_system_time_seconds = $start_resource_usage_timer['ru_stime.tv_sec'] * 1000;
  $start_system_time_microseconds = intval($start_resource_usage_timer['ru_stime.tv_usec'] / 1000);
  $total_system_time = $end_system_time_seconds + $end_system_time_microseconds - ($start_system_time_seconds + $start_system_time_microseconds);

  // Wallclock
  $total_wallclock_time = number_format($end_wallclock - $start_wallclock, $decimals_wallclock);

  // Server request_time_float
  $request_time_float = microtime(TRUE) - $_SERVER['REQUEST_TIME_FLOAT'];
  $request_time_float = number_format($request_time_float, $decimals_request_time_float);

  // Print
  $span_start = "<span style='width: $key_width; display: inline-block;'>";
  $span_end = '</span>';

  $output = '# RUNTIME AND TIMERS ' . PHP_EOL;
  $output .= PHP_EOL;
  $output .= $span_start . $total_user_time . $span_end . ' User time (utime)' . PHP_EOL;
  $output .= $span_start . $total_system_time . $span_end . ' System time (stime)' . PHP_EOL;
  $output .= PHP_EOL;
  $output .= $span_start . $total_wallclock_time . $span_end . ' Wallclock' . PHP_EOL;
  $output .= PHP_EOL;
  $output .= $span_start . $request_time_float . $span_end . ' REQUEST_TIME_FLOAT' . PHP_EOL . PHP_EOL . PHP_EOL;

  return nl2br($output);
}

function get_timers() {
  return [getrusage(), microtime(TRUE)];
}
