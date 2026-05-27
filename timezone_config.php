<?php
/**
 * TIMEZONE CONFIGURATION FOR CYCLOAN SYSTEM
 * 
 * This file handles all timezone-related configurations for the application.
 * All timestamps in the system are recorded in Philippine Time (PHT - UTC+8)
 * 
 * The system uses:
 * - PHP timezone: Asia/Manila (UTC+8)
 * - MySQL session timezone: +08:00 (UTC+8)
 * - All date/time functions automatically use Philippine Time
 */

// ========== TIMEZONE SETTINGS ==========
// Set PHP default timezone to Philippine Time
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Manila');
}

// Timezone constants
const SYSTEM_TIMEZONE = 'Asia/Manila';
const SYSTEM_TIMEZONE_OFFSET = '+08:00';

// ========== TIMEZONE HELPER FUNCTIONS ==========

/**
 * Get current Philippine time as DateTime object
 * 
 * @return DateTime Current time in Philippine timezone
 * @example
 *   $now = getPhilippineTime();
 *   echo $now->format('Y-m-d H:i:s'); // Displays current PH time
 */
function getPhilippineTime()
{
    $timezone = new DateTimeZone(SYSTEM_TIMEZONE);
    return new DateTime('now', $timezone);
}

/**
 * Get current Philippine time as formatted string
 * 
 * @param string $format PHP date format string (default: 'Y-m-d H:i:s')
 * @return string Formatted current time in Philippine timezone
 * @example
 *   echo getCurrentPHTime(); // 2025-11-02 14:30:45
 *   echo getCurrentPHTime('M d, Y'); // Nov 02, 2025
 */
function getCurrentPHTime($format = 'Y-m-d H:i:s')
{
    return getPhilippineTime()->format($format);
}

/**
 * Convert any DateTime to Philippine Time
 * 
 * @param DateTime|string $dateTime DateTime object or date string to convert
 * @param string $format Optional format to return (if null, returns DateTime object)
 * @return DateTime|string Converted time or formatted string
 * @example
 *   $dt = convertToPHTime('2025-11-02 10:00:00'); // Assumes UTC
 *   echo convertToPHTime('2025-11-02 10:00:00', 'Y-m-d H:i:s'); // String format
 */
function convertToPHTime($dateTime, $format = null)
{
    if (is_string($dateTime)) {
        // If string, assume it's UTC
        $dt = new DateTime($dateTime, new DateTimeZone('UTC'));
    } else {
        $dt = $dateTime;
    }

    $phTimezone = new DateTimeZone(SYSTEM_TIMEZONE);
    $dt->setTimezone($phTimezone);

    return $format ? $dt->format($format) : $dt;
}

/**
 * Format database timestamp to human-readable Philippine time
 * 
 * @param string $dbTimestamp Timestamp from database
 * @param string $format Output format (default: 'Y-m-d H:i:s')
 * @return string Formatted timestamp
 * @example
 *   echo formatPHTimestamp($row['created_at']); // 2025-11-02 14:30:45
 *   echo formatPHTimestamp($row['created_at'], 'M d, Y \a\t g:i A'); // Nov 02, 2025 at 2:30 PM
 */
function formatPHTimestamp($dbTimestamp, $format = 'Y-m-d H:i:s')
{
    if (empty($dbTimestamp) || $dbTimestamp === '0000-00-00 00:00:00') {
        return 'N/A';
    }

    try {
        return convertToPHTime($dbTimestamp, $format);
    } catch (Exception $e) {
        return 'Invalid Date';
    }
}

/**
 * Get time remaining until a deadline (useful for OTP expiry, payment due dates, etc.)
 * 
 * @param DateTime|string $deadline Deadline DateTime or string
 * @return array Associative array with keys: 'hours', 'minutes', 'seconds', 'expired', 'display'
 * @example
 *   $remaining = getTimeRemaining('2025-11-02 15:00:00');
 *   echo $remaining['display']; // "1 hour 30 minutes remaining"
 */
function getTimeRemaining($deadline)
{
    $now = getPhilippineTime();

    if (is_string($deadline)) {
        $deadline = new DateTime($deadline, new DateTimeZone(SYSTEM_TIMEZONE));
    }

    $diff = $now->diff($deadline);
    $expired = $now > $deadline;

    $hours = $diff->h + ($diff->days * 24);
    $minutes = $diff->i;
    $seconds = $diff->s;

    // Generate display string
    $display = '';
    if ($expired) {
        $display = 'Expired';
    } else {
        $parts = [];
        if ($hours > 0)
            $parts[] = "$hours hour" . ($hours > 1 ? 's' : '');
        if ($minutes > 0)
            $parts[] = "$minutes minute" . ($minutes > 1 ? 's' : '');
        if ($seconds > 0 && count($parts) < 2)
            $parts[] = "$seconds second" . ($seconds > 1 ? 's' : '');
        $display = implode(' ', $parts) . ' remaining';
    }

    return [
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
        'expired' => $expired,
        'display' => $display
    ];
}

/**
 * Check if a timestamp is within a specific time range
 * 
 * @param string $timestamp Timestamp to check
 * @param int $minutes Number of minutes to look back
 * @return bool True if timestamp is within the time range
 * @example
 *   if (isWithinTimeRange($row['created_at'], 60)) {
 *       echo "Created within the last hour";
 *   }
 */
function isWithinTimeRange($timestamp, $minutes = 60)
{
    $now = getPhilippineTime();
    $created = new DateTime($timestamp, new DateTimeZone(SYSTEM_TIMEZONE));
    $diff = $now->diff($created);
    $minutesElapsed = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

    return $minutesElapsed <= $minutes;
}

/**
 * Get Philippine time at start of day (00:00:00)
 * 
 * @param DateTime|string $dateTime Optional specific date (default: today)
 * @return DateTime Start of day in Philippines timezone
 */
function getStartOfDayPH($dateTime = null)
{
    if ($dateTime === null) {
        $dt = getPhilippineTime();
    } elseif (is_string($dateTime)) {
        $dt = new DateTime($dateTime, new DateTimeZone(SYSTEM_TIMEZONE));
    } else {
        $dt = $dateTime;
    }

    $dt->setTime(0, 0, 0);
    return $dt;
}

/**
 * Get Philippine time at end of day (23:59:59)
 * 
 * @param DateTime|string $dateTime Optional specific date (default: today)
 * @return DateTime End of day in Philippines timezone
 */
function getEndOfDayPH($dateTime = null)
{
    if ($dateTime === null) {
        $dt = getPhilippineTime();
    } elseif (is_string($dateTime)) {
        $dt = new DateTime($dateTime, new DateTimeZone(SYSTEM_TIMEZONE));
    } else {
        $dt = $dateTime;
    }

    $dt->setTime(23, 59, 59);
    return $dt;
}

/**
 * Calculate age based on Philippine time
 * 
 * @param string|DateTime $birthDate Birth date
 * @param DateTime|null $referenceDate Date to calculate age at (default: today)
 * @return int Age in years
 */
function calculateAgePH($birthDate, $referenceDate = null)
{
    if (is_string($birthDate)) {
        $birthDate = new DateTime($birthDate, new DateTimeZone(SYSTEM_TIMEZONE));
    }

    if ($referenceDate === null) {
        $referenceDate = getPhilippineTime();
    } elseif (is_string($referenceDate)) {
        $referenceDate = new DateTime($referenceDate, new DateTimeZone(SYSTEM_TIMEZONE));
    }

    return $birthDate->diff($referenceDate)->y;
}

/**
 * Format time difference in human-readable format
 * 
 * @param DateTime|string $olderTime Older timestamp
 * @param DateTime|string $newerTime Newer timestamp (default: now)
 * @return string Human-readable time difference (e.g., "2 hours ago", "3 days ago")
 */
function getTimeDifference($olderTime, $newerTime = null)
{
    if (is_string($olderTime)) {
        $olderTime = new DateTime($olderTime, new DateTimeZone(SYSTEM_TIMEZONE));
    }

    if ($newerTime === null) {
        $newerTime = getPhilippineTime();
    } elseif (is_string($newerTime)) {
        $newerTime = new DateTime($newerTime, new DateTimeZone(SYSTEM_TIMEZONE));
    }

    $diff = $olderTime->diff($newerTime);

    if ($diff->y > 0)
        return $diff->y . " year" . ($diff->y > 1 ? 's' : '') . " ago";
    if ($diff->m > 0)
        return $diff->m . " month" . ($diff->m > 1 ? 's' : '') . " ago";
    if ($diff->d > 0)
        return $diff->d . " day" . ($diff->d > 1 ? 's' : '') . " ago";
    if ($diff->h > 0)
        return $diff->h . " hour" . ($diff->h > 1 ? 's' : '') . " ago";
    if ($diff->i > 0)
        return $diff->i . " minute" . ($diff->i > 1 ? 's' : '') . " ago";

    return "just now";
}

/**
 * Get Philippine date only (without time)
 * 
 * @param string $dbTimestamp Timestamp from database
 * @return string Date in format 'Y-m-d'
 */
function formatPHDate($dbTimestamp)
{
    return formatPHTimestamp($dbTimestamp, 'Y-m-d');
}

/**
 * Get Philippine time only (without date)
 * 
 * @param string $dbTimestamp Timestamp from database
 * @return string Time in format 'H:i:s'
 */
function formatPHTimeOnly($dbTimestamp)
{
    return formatPHTimestamp($dbTimestamp, 'H:i:s');
}

// ========== TIMEZONE VALIDATION ==========

/**
 * Verify that the system is using correct timezone
 * For debugging purposes
 * 
 * @return array System timezone information
 */
function getTimezoneInfo()
{
    $phpTz = date_default_timezone_get();
    $currentTime = getCurrentPHTime('Y-m-d H:i:s');

    return [
        'php_timezone' => $phpTz,
        'system_timezone' => SYSTEM_TIMEZONE,
        'current_time_ph' => $currentTime,
        'timezone_offset' => date('O'),
        'timezone_correct' => $phpTz === SYSTEM_TIMEZONE
    ];
}

?>