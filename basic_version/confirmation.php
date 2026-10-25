<?php
require __DIR__ . '/api/booking_rules.php';

$conn = db();

$code = strtoupper(get_str('code'));
$booking = null;

if ($code !== '') {
    $stmt = mysqli_prepare($conn,
        "SELECT patient_name, patient_email, doctor, slot_start, status
         FROM appointments WHERE manage_token = ?");
    mysqli_stmt_bind_param($stmt, "s", $code);
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
        <li><a href="manage.php">Manage Booking</a></li>
        <li><a href="doctor.php">Doctor Login</a></li>
        <li><a class="book-now" href="booking.html">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <section class="booking-form">

<?php if ($booking): ?>
      <h2>Booking confirmed</h2>

      <p>
        Thank you, <?= htmlspecialchars($booking['patient_name']) ?>.
        Your appointment is booked.
      </p>

<?php if (($_GET['sent'] ?? '') === '1'): ?>
      <p class="form-msg success">
        A confirmation email has been sent to
        <?= htmlspecialchars($booking['patient_email']) ?>.
      </p>
<?php elseif (($_GET['sent'] ?? '') === '0'): ?>
      <p class="form-msg error">
        Your booking is saved, but the confirmation email could not be sent.
        Please keep the code below.
      </p>
<?php endif; ?>

      <p><strong>Doctor:</strong> <?= htmlspecialchars($booking['doctor']) ?></p>
      <p><strong>Date and time:</strong>
        <?= htmlspecialchars(date("D, j M Y, g:i A", strtotime($booking['slot_start']))) ?>
      </p>

      <p><strong>Your booking code</strong></p>
      <p class="booking-code"><?= htmlspecialchars($code) ?></p>

      <p>
        Keep this code. Use it on the Manage Booking page to change the date
        of your appointment.
      </p>

      <a class="book-now" href="manage.php?code=<?= urlencode($code) ?>">Manage this booking</a>

<?php else: ?>
      <h2>Booking not found</h2>
      <p>We could not find a booking for that code. Please check the code and try again.</p>
      <a class="book-now" href="manage.php">Enter a booking code</a>
<?php endif; ?>

    </section>
  </main>

  <a class="book-tab" href="booking.html">Book Now</a>

</body>
</html>
