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
<?php
$pageTitle = 'Booking Confirmed';
require_once __DIR__ . '/includes/header.php';
?>

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
