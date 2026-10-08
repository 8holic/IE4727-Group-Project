<?php
date_default_timezone_set("Asia/Singapore");

$conn = mysqli_connect("localhost", "root", "", "jnb_clinic");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$name     = $_POST['name'];
$email    = $_POST['email'];
$location = $_POST['location'];
$start    = str_replace('T', ' ', $_POST['start']);

// --- Validation: before insert ---
$today = date("Y-m-d");
$max   = date("Y-m-d", strtotime("+7 days"));
$dateOnly = substr($start, 0, 10);

if ($dateOnly < $today || $dateOnly > $max) {
    echo "Date must be within the next 7 days.";
    mysqli_close($conn);
    exit;
}

if (strtotime($start) < time()) {
    echo "That time has already passed.";
    mysqli_close($conn);
    exit;
}
// --- End validation ---

$sql = "INSERT INTO appointments 
        (patient_name, patient_email, location, slot_start)
        VALUES ('$name', '$email', '$location', '$start')";

try {
    mysqli_query($conn, $sql);
    echo "Booking confirmed for " . $name;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() == 1062) {
        echo "Sorry, that slot is already taken.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}

mysqli_close($conn);
?>