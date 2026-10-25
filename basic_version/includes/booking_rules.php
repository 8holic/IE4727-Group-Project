<?php
date_default_timezone_set("Asia/Singapore");

$doctors = [
    'Dr Bryan'  => ['label' => 'Dr Bryan (Mon-Fri)',  'days' => [1, 2, 3, 4, 5]],
    'Dr Javier' => ['label' => 'Dr Javier (Tue-Sat)', 'days' => [2, 3, 4, 5, 6]],
];

$doctorDays = [];
foreach ($doctors as $name => $doc) {
    $doctorDays[$name] = $doc['days'];
}

$closedDays = [7];
$openStart  = '09:00';
$openEnd    = '17:40';

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

function h($value) {
    return htmlspecialchars((string) $value);
}

function email_error($email) {
    if ($email === '') {
        return "Please enter your email.";
    }
    if (preg_match('/\s/', $email)) {
        return "Email cannot contain spaces.";
    }

    $at = strpos($email, '@');
    if ($at === false) {
        return "Email must contain an @ symbol.";
    }
    if (strpos($email, '@', $at + 1) !== false) {
        return "Email can only contain one @ symbol.";
    }

    $local = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    if ($local === '') {
        return "Enter the part before the @ symbol.";
    }
    if ($domain === '') {
        return "Enter the part after the @ symbol.";
    }
    if (!preg_match('/^[a-zA-Z0-9._%+-]+$/', $local)) {
        return "Use only letters, numbers, dots, and _ % + - before the @ symbol.";
    }
    if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
        return "Check the domain after the @ symbol.";
    }

    if (strcasecmp($domain, 'localhost') !== 0) {
        if (strpos($domain, '.') === false) {
            return "Add a domain after the @ symbol (e.g. example.com).";
        }
        if (!preg_match('/\.[a-zA-Z]{2,}$/', $domain)) {
            return "Check the ending of the email domain.";
        }
    }

    return "";
}

function validate_phone($phone) {
    $phone = trim($phone);
    if ($phone === '') {
        return "";
    }

    $digits = preg_replace('/[\s\-().]/', '', $phone);

    if (!preg_match('/^(?:\+?65)?[89]\d{7}$/', $digits)) {
        return "Please enter a valid Singapore phone number.";
    }

    return "";
}

function valid_date($date) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    list($year, $month, $day) = array_map('intval', explode('-', $date));
    return checkdate($month, $day, $year);
}

function slot_times() {
    global $openStart, $openEnd;

    $start = (int) substr($openStart, 0, 2) * 60 + (int) substr($openStart, 3, 2);
    $end   = (int) substr($openEnd, 0, 2) * 60 + (int) substr($openEnd, 3, 2);

    $times = [];
    for ($m = $start; $m <= $end; $m += 20) {
        $times[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    return $times;
}

function time_label($hm) {
    $hour = (int) substr($hm, 0, 2);
    $suffix = $hour < 12 ? 'AM' : 'PM';
    $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;
    return $hour12 . ':' . substr($hm, 3, 2) . ' ' . $suffix;
}

function doctor_days_text($doctor) {
    global $doctorDays, $dayNames;

    if (!isset($doctorDays[$doctor])) {
        return '';
    }

    $labels = [];
    foreach ($doctorDays[$doctor] as $day) {
        $labels[] = $dayNames[$day];
    }
    return implode(', ', $labels);
}

function schedule_error($doctor, $date) {
    global $doctorDays, $closedDays, $dayNames;

    if (!isset($doctorDays[$doctor])) {
        return "Please choose a doctor.";
    }
    if (!valid_date($date)) {
        return "Please choose a valid date.";
    }

    $today = date('Y-m-d');
    $max   = date('Y-m-d', strtotime('+2 days'));

    if ($date < $today || $date > $max) {
        return "Date must be within the next 2 days.";
    }

    $weekday = (int) date('N', strtotime($date));

    if (in_array($weekday, $closedDays)) {
        return "The clinic is closed on " . $dayNames[$weekday] . ".";
    }

    if (!in_array($weekday, $doctorDays[$doctor])) {
        return $doctor . " is not available on " . $dayNames[$weekday] . ".";
    }

    return "";
}

function validate_slot($doctor, $start) {
    $date = substr($start, 0, 10);
    $time = substr($start, 11, 5);

    $error = schedule_error($doctor, $date);
    if ($error !== '') {
        return $error;
    }

    if (!in_array($time, slot_times(), true)) {
        return "Please choose a valid appointment time.";
    }

    if (strtotime($date . ' ' . $time . ':00') < time()) {
        return "That time has already passed. Please choose a later time.";
    }

    return "";
}

function taken_times($conn, $doctor, $date, $excludeId = 0) {
    $sql = "SELECT TIME_FORMAT(slot_start, '%H:%i') AS slot_time
            FROM appointments
            WHERE doctor = ? AND DATE(slot_start) = ? AND status = 'booked'";

    if ($excludeId > 0) {
        $sql .= " AND id <> ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssi', $doctor, $date, $excludeId);
    } else {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $doctor, $date);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $taken = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $taken[] = $row['slot_time'];
    }
    mysqli_stmt_close($stmt);

    return $taken;
}

function available_times($conn, $doctor, $date, $excludeId = 0) {
    if (schedule_error($doctor, $date) !== '') {
        return [];
    }

    $taken = taken_times($conn, $doctor, $date, $excludeId);
    $now = time();
    $available = [];

    foreach (slot_times() as $time) {
        if (in_array($time, $taken, true)) {
            continue;
        }
        if (strtotime($date . ' ' . $time . ':00') <= $now) {
            continue;
        }
        $available[] = $time;
    }

    return $available;
}
?>
