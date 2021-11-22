<?php
/**
 * Utility static functions for Phixator
 */
class PhixatorUtil {

  /**
   * Convert a time log string to corresponding time in minutes
   */
  public static function timeStringToMinutes(string $time): int {
    $totalMinutes = 0;
    if (!$parts = explode(' ', $time)) return 0;
    foreach ($parts as $part) {
      $modifier = substr($part, -1, 1);
      $value = (int)substr($part, 0, strlen($part) - 1);
      switch ($modifier) {
        case 'h': $totalMinutes += $value * 60; break;
        case 'm': $totalMinutes += $value; break;
      }
    }
    return $totalMinutes;
  }


  /**
   * Convert a time in minutes to a corresponding time log string
   */
  public static function minutesToTimeString($minutes): string {
    if (!$minutes) return '';
    $minutes = intval($minutes);
    if ($minutes < 60) return $minutes.'m';
    $out = ['h' => 0, 'm' => 0];
    while ($minutes >= 60) {
      $minutes -= 60;
      $out['h']++;
    };
    $out['m'] += $minutes;
    $formatted = '';
    foreach ($out as $timeTitle => $timeValue) {
      if ($timeValue == 0) continue;
      if($formatted) $formatted .= ' ';
      $formatted .= $timeValue . $timeTitle;
    }
    return $formatted;
  }


  /**
   * Check if time log string is in correct format
   */
  public static function isTimeStringFormatCorrect(string $time): bool {
    $parts = explode(' ', $time);
    foreach($parts as $part) {
      $modifier = substr($part, -1, 1);
      $value = substr($part, 0, strlen($part) - 1);
      if(!is_numeric($value)) return false;
      switch($modifier) {
        case 'h': if($value > 23) return false; break;
        case 'm': if($value > 60) return false; break;
        default: return false;
      }
    }
    return true;
  }
}
