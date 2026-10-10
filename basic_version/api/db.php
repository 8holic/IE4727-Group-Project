<?php
date_default_timezone_set("Asia/Singapore");

try {
    $conn = mysqli_connect("localhost", "root", "", "jnb_clinic");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database connection failed.");
}

// Single source of truth for doctor schedules (booking.js reads the same file).
$config = json_decode(file_get_contents(__DIR__ . '/doctors.json'), true);
if (!$config) {
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

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = $_POST['phone'] ?? '';
$doctor   = $_POST['doctor'] ?? '';
$start    = $_POST['start'] ?? '';

// --- Validation: before insert ---
if ($name === '') {
    http_response_code(400);
    echo "Please enter your name.";
    mysqli_close($conn);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
    http_response_code(400);
    echo "Please enter a valid email address.";
    mysqli_close($conn);
    exit;
}

$today    = date("Y-m-d");
$max      = date("Y-m-d", strtotime("+2 days"));
$dateOnly = substr($start, 0, 10);
$time     = substr($start, 11, 5);

if ($dateOnly < $today || $dateOnly > $max) {
    http_response_code(400);
    echo "Date must be within the next 2 days.";
    mysqli_close($conn);
    exit;
}

if (strtotime($start) < time()) {
    http_response_code(400);
    echo "That time has already passed. Please choose a later time.";
    mysqli_close($conn);
    exit;
}

if (!isset($doctorDays[$doctor])) {
    http_response_code(400);
    echo "Please choose a doctor.";
    mysqli_close($conn);
    exit;
}

$weekday = (int) date("N", strtotime($dateOnly));

if (in_array($weekday, $closedDays)) {
    http_response_code(400);
    echo "The clinic is closed on " . $dayNames[$weekday] . ".";
    mysqli_close($conn);
    exit;
}

if (!in_array($weekday, $doctorDays[$doctor])) {
    http_response_code(400);
    echo $doctor . " is not available on " . $dayNames[$weekday] . ".";
    mysqli_close($conn);
    exit;
}

if ($time < $openStart || $time > $openEnd) {
    http_response_code(400);
    echo "Appointments must be between " . $openStart . " and " . $openEnd . ".";
    mysqli_close($conn);
    exit;
}
// --- End validation ---

$sql = "INSERT INTO appointments
        (patient_name, patient_email, phone, doctor, slot_start)
        VALUES (?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $phone, $doctor, $start);

try {
    mysqli_stmt_execute($stmt);
    echo "Booking confirmed for " . $name;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        http_response_code(409);
        echo "Sorry, that time slot is already booked. Please choose another time.";
    } else {
        http_response_code(500);
        echo "Error: " . $e->getMessage();
    }
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
