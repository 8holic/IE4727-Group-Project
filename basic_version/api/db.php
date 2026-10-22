<?php
require __DIR__ . '/booking_rules.php';

// Sender mailbox configured in Mercury (XAMPP). Local mailboxes are @localhost.
// Without a relay host, Mercury only delivers to local addresses such as
// deepseek@localhost.net (a plain @localhost is rejected by the form's email check).
const CLINIC_MAIL_FROM = "deepseek@localhost";

$conn = db();

$name     = trim($_POST['name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = $_POST['phone'] ?? '';
$doctor   = $_POST['doctor'] ?? '';
$start    = $_POST['start'] ?? '';

// --- Validation: before insert ---
$error = validate_patient($name, $email);
if ($error === '') {
    $error = validate_slot($doctor, $start);
}
if ($error !== '') {
    http_response_code(400);
    echo $error;
    mysqli_close($conn);
    exit;
}
// --- End validation ---

// Short, typable code handed to the patient. It doubles as the manage token:
// whoever has the code can view/change the booking.
function new_booking_code($conn) {
    $alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";   // no 0/O/1/I
    do {
        $code = "";
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $check = mysqli_prepare($conn, "SELECT 1 FROM appointments WHERE manage_token = ?");
        mysqli_stmt_bind_param($check, "s", $code);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);
        $exists = mysqli_stmt_num_rows($check) > 0;
        mysqli_stmt_close($check);
    } while ($exists);

    return $code;
}

$code = new_booking_code($conn);

function send_booking_email($name, $email, $doctor, $start, $code) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // dirname() returns backslashes on Windows, so normalise to URL slashes.
    $root = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/db.php')));
    $base = rtrim("http://" . $host . $root, "/");
    $manage = $base . "/manage.php?code=" . urlencode($code);

    $when = date("D, j M Y, g:i A", strtotime($start)) . " (SGT)";

    $body = "Hi " . $name . ",\n\n"
          . "Your appointment at J&B Family Clinic is confirmed.\n\n"
          . "Doctor: " . $doctor . "\n"
          . "Date and time: " . $when . "\n"
          . "Booking code: " . $code . "\n\n"
          . "To change the date of your appointment, open:\n"
          . $manage . "\n\n"
          . "Or enter your booking code on the Manage Booking page:\n"
          . $base . "/manage.php\n\n"
          . "See you soon,\n"
          . "J&B Family Clinic\n";

    $headers = "From: \"J&B Family Clinic\" <" . CLINIC_MAIL_FROM . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, "Booking confirmed - " . $code, $body, $headers);
}

$sql = "INSERT INTO appointments
        (patient_name, patient_email, phone, doctor, slot_start, manage_token)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $phone, $doctor, $start, $code);

try {
    mysqli_stmt_execute($stmt);
    $sent = send_booking_email($name, $email, $doctor, $start, $code);
    echo $code . "|" . ($sent ? "1" : "0");
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
