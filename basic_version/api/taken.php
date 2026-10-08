<?php
$conn = mysqli_connect("localhost", "root", "", "jnb_clinic");
if (!$conn) { die("Connection failed"); }

$date     = $_GET['date'];
$location = $_GET['location'];

$sql = "SELECT slot_start FROM appointments 
        WHERE location = '$location' AND DATE(slot_start) = '$date'";
$result = mysqli_query($conn, $sql);

$times = [];
while ($row = mysqli_fetch_assoc($result)) {
    $times[] = substr($row['slot_start'], 11, 5);   // "09:20"
}

echo implode(",", $times);
mysqli_close($conn);
?>