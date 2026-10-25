<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require_patient('my_appointments.php');

$conn = db();

$code = strtoupper(get_str('code'));
$booking = null;

if ($code !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT id, patient_name, patient_email, doctor, slot_start, status
         FROM appointments WHERE manage_token = ? AND patient_id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $code, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt);
    $booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking Confirmed | J&amp;B Family Clinic</title>
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
        <li><a href="my_appointments.php">My Appointments</a></li>
        <li><a href="logout.php">Logout</a></li>
        <li><a class="book-now" href="booking.php">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <section class="booking-form">

<?php if ($booking): ?>
      <h2>Booking confirmed</h2>

      <p>
        Thank you, <?= h($booking['patient_name']) ?>.
        Your appointment is booked.
      </p>

<?php if (($_GET['sent'] ?? '') === '1'): ?>
      <p class="form-msg success">
        A confirmation email has been sent to
        <?= h($booking['patient_email']) ?>.
      </p>
<?php elseif (($_GET['sent'] ?? '') === '0'): ?>
      <p class="form-msg error">
        Your booking is saved, but the confirmation email could not be sent.
        Please keep the code below.
      </p>
<?php endif; ?>

      <p><strong>Doctor:</strong> <?= h($booking['doctor']) ?></p>
      <p><strong>Date and time:</strong>
        <?= h(date("D, j M Y, g:i A", strtotime($booking['slot_start']))) ?>
      </p>

      <p><strong>Your booking code</strong></p>
      <p class="booking-code"><?= h($code) ?></p>

      <p>
        Open the My Appointments page to view or change the date of your appointment.
      </p>

      <a class="book-now" href="manage.php?id=<?= (int) $booking['id'] ?>">Manage this booking</a>

<?php else: ?>
      <h2>Booking not found</h2>
      <p>We could not find that booking on your account. Please check the code and try again.</p>
      <a class="book-now" href="my_appointments.php">Go to My Appointments</a>
<?php endif; ?>

    </section>
  </main>

  <a class="book-tab" href="booking.php">Book Now</a>
</body>
</html>
