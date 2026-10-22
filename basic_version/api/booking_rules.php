<?php
// Shared between the booking API and the manage-booking page so both apply
// exactly the same rules. Booking.js reads doctors.json directly as well.
date_default_timezone_set("Asia/Singapore");

$config = json_decode(file_get_contents(__DIR__ . '/doctors.json'), true);
if (!$config) {
    http_response_code(500);
    die("Could not load doctors.json");
}

$doctorDays = [];
foreach ($config['doctors'] as $doc) {
    $doctorDays[$doc['name']] = $doc['days'];
}
$closedDays = $config['closed'];
$openStart  = $config['hours']['start'];
$openEnd    = $config['hours']['end'];

$dayNames = [
    1 => "Mondays", 2 => "Tuesdays", 3 => "Wednesdays", 4 => "Thursdays",
    5 => "Fridays", 6 => "Saturdays", 7 => "Sundays"
];

function db() {
    try {
        return mysqli_connect("localhost", "root", "", "jnb_clinic");
    } catch (mysqli_sql_exception $e) {
        http_response_code(500);
        die("Database connection failed.");
    }
}

function post_str($key) {
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function get_str($key) {
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function validate_patient($name, $email) {
    if ($name === '') {
        return "Please enter your name.";
    }
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        return "Please enter a valid email address.";
    }
    return "";
}

function validate_phone($country, $phone) {
    $phone = trim($phone);
    if ($phone === '') {
        return "";
    }

    $digits = preg_replace('/[\s\-().]/', '', $phone);

    if ($country === 'SG') {
        if (!preg_match('/^(?:\+?65)?[3689]\d{7}$/', $digits)) {
            return "Please enter a valid Singapore phone number.";
        }
        return "";
    }

    if ($country === 'MY') {
        if (!preg_match('/^(?:\+?60|0)(?:1\d{8,9}|[3-9]\d{7,8})$/', $digits)) {
            return "Please enter a valid Malaysia phone number.";
        }
        return "";
    }

    return "Please choose a country.";
}

// $start is "YYYY-MM-DD HH:MM:SS". Returns an error message, or "" if valid.
function validate_slot($doctor, $start) {
    global $doctorDays, $closedDays, $openStart, $openEnd, $dayNames;

    $today    = date("Y-m-d");
    $max      = date("Y-m-d", strtotime("+2 days"));
    $dateOnly = substr($start, 0, 10);
    $time     = substr($start, 11, 5);

    if ($dateOnly < $today || $dateOnly > $max) {
        return "Date must be within the next 2 days.";
    }

    if (strtotime($start) < time()) {
        return "That time has already passed. Please choose a later time.";
    }

    if (!isset($doctorDays[$doctor])) {
        return "Please choose a doctor.";
    }

    $weekday = (int) date("N", strtotime($dateOnly));

    if (in_array($weekday, $closedDays)) {
        return "The clinic is closed on " . $dayNames[$weekday] . ".";
    }

    if (!in_array($weekday, $doctorDays[$doctor])) {
        return $doctor . " is not available on " . $dayNames[$weekday] . ".";
    }

    if ($time < $openStart || $time > $openEnd) {
        return "Appointments must be between " . $openStart . " and " . $openEnd . ".";
    }

    return "";
}
