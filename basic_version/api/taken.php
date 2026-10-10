<?php
date_default_timezone_set("Asia/Singapore");

try {
    $conn = mysqli_connect("localhost", "root", "", "jnb_clinic");
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    die("Database connection failed");
}

$date   = $_GET['date'] ?? '';
$doctor = $_GET['doctor'] ?? '';

$sql = "SELECT slot_start FROM appointments
        WHERE doctor = ? AND DATE(slot_start) = ? AND status = 'booked'";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $doctor, $date);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$times = [];
while ($row = mysqli_fetch_assoc($result)) {
    $times[] = substr($row['slot_start'], 11, 5);   // "09:20"
}

echo implode(",", $times);
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
