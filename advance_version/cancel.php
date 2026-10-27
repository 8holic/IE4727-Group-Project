<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require __DIR__ . '/includes/mail.php';
require_patient('my_appointments.php');

$conn = db();
$id = (int) (post_str('id') !== '' ? post_str('id') : get_str('id'));
$error = '';

function find_booking($conn, $id, $patientId) {
    $stmt = mysqli_prepare($conn,
        "SELECT id, patient_name, patient_email, doctor, slot_start, status
         FROM appointments WHERE id = ? AND patient_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $patientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

$booking = find_booking($conn, $id, $_SESSION['user_id']);

if (!$booking) {
    http_response_code(404);
    $error = 'We could not find that appointment on your account.';
} elseif ($booking['status'] !== 'booked') {
    $error = 'This appointment is no longer booked.';
} elseif (strtotime($booking['slot_start']) < time()) {
    $error = 'Past appointments cannot be cancelled.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'cancel') {
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $stmt = mysqli_prepare($conn,
            "UPDATE appointments SET status = 'cancelled'
             WHERE id = ? AND patient_id = ? AND status = 'booked'");
        mysqli_stmt_bind_param($stmt, 'ii', $booking['id'], $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($changed === 1) {
            $when = date('D, j M Y, g:i A', strtotime($booking['slot_start']));
            $body = "Hi " . $booking['patient_name'] . ",\n\n"
                  . "Your appointment at J&B Family Clinic has been cancelled.\n\n"
                  . "Doctor: " . $booking['doctor'] . "\n"
                  . "Date and time: " . $when . " (SGT)\n\n"
                  . "If this was a mistake, you can book again here:\n"
                  . site_base() . "/booking.php\n\n"
                  . "J&B Family Clinic\n";
            $sent = send_mail($booking['patient_email'],
                'Appointment cancelled - ' . date('j M Y', strtotime($booking['slot_start'])), $body);

            $msg = 'Appointment cancelled.';
            if (!$sent) {
                $msg .= ' The cancellation email could not be sent.';
            }
            mysqli_close($conn);
            header('Location: my_appointments.php?message=' . urlencode($msg));
            exit;
        }

        $error = 'This appointment is no longer booked.';
    }
}

$cancellable = $booking
    && $booking['status'] === 'booked'
    && strtotime($booking['slot_start']) >= time();

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cancel Appointment | J&amp;B Family Clinic</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header class="top-header">
    <a class="logo-link" href="index.html" aria-label="J&B Family Clinic Home">
      <img class="clinic-logo" src="assets/logo.png" alt="J&B Family Clinic logo">
    </a>
    <nav class="top-nav" aria-label="Main navigation">
      <ul>
        <li><a href="index.html">Home</a></li>
        <li><a href="about.html">About Us</a></li>
        <li><a href="service.html">Services</a></li>
        <li><a href="location.html">Location</a></li>
        <li><a class="active" href="my_appointments.php">My Appointments</a></li>
        <li><a href="doctor.php">Employee Login</a></li>
        <li><a href="logout.php">Logout</a></li>
        <li><a class="book-now" href="booking.php">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">
<?php if ($cancellable): ?>
    <section class="booking-form">
      <h2>Cancel Appointment</h2>

      <p><strong>Patient:</strong> <?= h($booking['patient_name']) ?></p>
      <p><strong>Doctor:</strong> <?= h($booking['doctor']) ?></p>
      <p><strong>Date and time:</strong>
        <?= h(date('D, j M Y, g:i A', strtotime($booking['slot_start']))) ?>
      </p>
      <p>Are you sure you want to cancel this appointment?</p>

<?php if ($error !== ''): ?>
      <p class="form-msg error"><?= h($error) ?></p>
<?php endif; ?>

      <form method="post" action="cancel.php">
        <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <button type="submit">Cancel Appointment</button>
      </form>

      <p><a href="my_appointments.php">Keep this appointment</a></p>
    </section>
<?php else: ?>
    <section class="booking-form">
      <h2>Cancel Appointment</h2>
      <p class="form-msg error"><?= h($error) ?></p>
      <p><a href="my_appointments.php">Back to My Appointments</a></p>
    </section>
<?php endif; ?>
  </main>

  <a class="book-tab" href="booking.php">Book Now</a>
</body>
</html>
