<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require __DIR__ . '/includes/mail.php';
require_patient('my_appointments.php');

$conn = db();

$id = (int) (post_str('id') !== '' ? post_str('id') : get_str('id'));
$error = '';
$selectedDate = get_str('date');
$selectedTime = '';

function find_appointment($conn, $id, $patientId) {
    $stmt = mysqli_prepare($conn,
        "SELECT id, patient_id, patient_name, patient_email, doctor, slot_start, status
         FROM appointments WHERE id = ? AND patient_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $patientId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

$booking = find_appointment($conn, $id, $_SESSION['user_id']);

if (!$booking) {
    http_response_code(404);
    $error = 'We could not find that appointment on your account.';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update') {
        $selectedDate = post_str('date');
        $selectedTime = post_str('start');

        if (!csrf_ok()) {
            $error = 'Your session expired. Please try again.';
        } elseif ($booking['status'] !== 'booked') {
            $error = 'This appointment can no longer be changed.';
        } else {
            $start = $selectedDate . ' ' . $selectedTime . ':00';
            $error = validate_slot($booking['doctor'], $start);

            if ($error === '') {
                $available = available_times($conn, $booking['doctor'], $selectedDate, $booking['id']);
                if (!in_array($selectedTime, $available, true)) {
                    $error = 'That appointment time is no longer available. Please choose another time.';
                }
            }

            if ($error === '') {
                $stmt = mysqli_prepare($conn,
                    "UPDATE appointments SET slot_start = ?
                     WHERE id = ? AND patient_id = ? AND status = 'booked'");
                mysqli_stmt_bind_param($stmt, 'sii', $start, $booking['id'], $_SESSION['user_id']);

                try {
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);

                    $when = date('D, j M Y, g:i A', strtotime($start));
                    $body = "Hi " . $booking['patient_name'] . ",\n\n"
                          . "Your appointment at J&B Family Clinic has been rescheduled.\n\n"
                          . "Doctor: " . $booking['doctor'] . "\n"
                          . "New date and time: " . $when . " (SGT)\n\n"
                          . "J&B Family Clinic\n";
                    $sent = send_mail($booking['patient_email'],
                        'Appointment rescheduled - ' . date('j M Y', strtotime($start)), $body);

                    $msg = 'Appointment rescheduled.';
                    if (!$sent) {
                        $msg .= ' The confirmation email could not be sent.';
                    }
                    mysqli_close($conn);
                    header('Location: my_appointments.php?message=' . urlencode($msg));
                    exit;
                } catch (mysqli_sql_exception $e) {
                    $error = $e->getCode() == 1062
                        ? 'Sorry, that time slot is already booked. Please choose another time.'
                        : 'Could not update the booking. Please try again.';
                }
            }
        }
    }

    if ($selectedDate === '') {
        $selectedDate = substr($booking['slot_start'], 0, 10);
    }

    $currentDate = substr($booking['slot_start'], 0, 10);
    if ($selectedTime === '' && $selectedDate === $currentDate) {
        $selectedTime = substr($booking['slot_start'], 11, 5);
    }

    $available = available_times($conn, $booking['doctor'], $selectedDate, $booking['id']);
}

mysqli_close($conn);

$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+2 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Booking | J&amp;B Family Clinic</title>
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
        <li><a href="doctor.php">Employee Login</a></li>
        <li><a href="logout.php">Logout</a></li>
        <li><a class="book-now" href="booking.php">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">
<?php if ($booking): ?>
    <section class="booking-form">
      <h2>Manage your booking</h2>

      <p>
        <strong>Patient:</strong> <?= h($booking['patient_name']) ?><br>
        <strong>Doctor:</strong> <?= h($booking['doctor']) ?><br>
        <strong>Current appointment:</strong>
        <?= h(date('D, j M Y, g:i A', strtotime($booking['slot_start']))) ?>
      </p>

<?php if ($booking['status'] !== 'booked'): ?>
      <p class="form-msg error">This booking has been cancelled and can no longer be changed.</p>
<?php else: ?>
<?php if ($error !== ''): ?>
      <p class="form-msg error"><?= h($error) ?></p>
<?php endif; ?>

      <form method="get" action="manage.php">
        <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">

        <label for="date">New date:</label>
        <input type="date" id="date" name="date" required
               min="<?= $minDate ?>" max="<?= $maxDate ?>"
               value="<?= h($selectedDate) ?>">
        <span class="input-error" data-error="Please choose a date."></span>

        <button type="submit">Check times</button>
      </form>

      <form method="post" action="manage.php">
        <input type="hidden" name="id" value="<?= (int) $booking['id'] ?>">
        <input type="hidden" name="date" value="<?= h($selectedDate) ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <label for="start">New time:</label>
        <select id="start" name="start" required>
          <option value="">— choose an available time —</option>
<?php foreach ($available as $time): ?>
          <option value="<?= h($time) ?>"<?= $time === $selectedTime ? ' selected' : '' ?>><?= h(time_label($time)) ?></option>
<?php endforeach; ?>
        </select>
        <span class="input-error" data-error="Please choose a time."></span>

<?php if (count($available) === 0): ?>
        <p class="form-msg error">No available slots on this date.</p>
<?php else: ?>
        <button type="submit">Save new date</button>
<?php endif; ?>
      </form>
<?php endif; ?>
    </section>
<?php else: ?>
    <section class="booking-form">
      <h2>Manage your booking</h2>
      <p class="form-msg error"><?= h($error) ?></p>
      <p>Open <a href="my_appointments.php">My Appointments</a> to choose a booking to reschedule.</p>
    </section>
<?php endif; ?>
  </main>

  <a class="book-tab" href="booking.php">Book Now</a>
</body>
</html>
