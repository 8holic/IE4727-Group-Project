<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require_patient('my_appointments.php');

$conn = db();

$message = get_str('message');
$messageClass = $message !== '' ? 'success' : '';

// Load current patient profile
$stmt = mysqli_prepare(
    $conn,
    'SELECT name, email, phone FROM patients WHERE id = ?'
);

mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);

$patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

mysqli_stmt_close($stmt);

if (!$patient) {
    logout_user();
    header('Location: login.php');
    exit;
}


// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'update_profile') {

    $name = post_str('name');
    $phone = post_str('phone');

    if (!csrf_ok()) {

        $message = 'Your session expired. Please refresh the page and try again.';
        $messageClass = 'error';

    } elseif ($name === '') {

        $message = 'Please enter your full name.';
        $messageClass = 'error';

    } elseif (strlen($name) < 2 || strlen($name) > 100) {

        $message = 'Name must contain between 2 and 100 characters.';
        $messageClass = 'error';

    } elseif ($phone === '') {

        $message = 'Please enter your mobile number.';
        $messageClass = 'error';

    } elseif (($phoneError = validate_phone($phone)) !== '') {

        $message =
            'Please enter a valid Singapore mobile number (8 digits, starting with 8 or 9).';

        $messageClass = 'error';

    } else {

        try {

            mysqli_begin_transaction($conn);

            // Update patient account
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE patients SET name = ?, phone = ? WHERE id = ?'
            );

            mysqli_stmt_bind_param(
                $stmt,
                'ssi',
                $name,
                $phone,
                $_SESSION['user_id']
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);


            // Update existing appointments so doctor dashboard shows latest details
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE appointments
                 SET patient_name = ?, phone = ?
                 WHERE patient_id = ?'
            );

            mysqli_stmt_bind_param(
                $stmt,
                'ssi',
                $name,
                $phone,
                $_SESSION['user_id']
            );

            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);

            $_SESSION['user_name'] = $name;

            $patient['name'] = $name;
            $patient['phone'] = $phone;

            $message = 'Profile updated successfully.';
            $messageClass = 'success';

        } catch (mysqli_sql_exception $e) {

            mysqli_rollback($conn);

            $message = 'Profile could not be updated. Please try again.';
            $messageClass = 'error';
        }
    }

    if ($messageClass === 'error') {
        $patient['name'] = $name;
        $patient['phone'] = $phone;
    }
}


// Existing appointment query
$stmt = mysqli_prepare(
    $conn,
    'SELECT id, doctor, slot_start, status
     FROM appointments
     WHERE patient_id = ?
     ORDER BY slot_start DESC'
);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $_SESSION['user_id']
);

mysqli_stmt_execute($stmt);

$appointments = mysqli_stmt_get_result($stmt);

$message = get_str('message');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments | J&amp;B Family Clinic</title>
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
    <div class="staff-head">
      <div>
        <p class="eyebrow">PATIENT ACCOUNT</p>
        <h1>My Appointments</h1>
        <p>Logged in as <?= h($_SESSION['user_name']) ?>.</p>
      </div>
      <a class="book-now" href="booking.php">New Appointment</a>
    </div>

    <?php if ($message !== ''): ?>
      <p class="form-msg success"><?= h($message) ?></p>
    <?php endif; ?>

    <div class="table-wrap">
      <table class="appointments">
        <thead>
          <tr><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
<?php if (mysqli_num_rows($appointments) === 0): ?>
          <tr><td colspan="5">You do not have any appointments yet.</td></tr>
<?php else: ?>
<?php while ($row = mysqli_fetch_assoc($appointments)): ?>
          <tr>
            <td><?= h($row['doctor']) ?></td>
            <td><?= h(date('D, j M Y', strtotime($row['slot_start']))) ?></td>
            <td><?= h(date('g:i A', strtotime($row['slot_start']))) ?></td>
            <td><span class="status status-<?= h($row['status']) ?>"><?= h($row['status']) ?></span></td>
            <td>
<?php if ($row['status'] === 'booked' && strtotime($row['slot_start']) >= time()): ?>
              <a href="manage.php?id=<?= (int) $row['id'] ?>">Reschedule</a> |
              <a href="cancel.php?id=<?= (int) $row['id'] ?>">Cancel</a>
<?php else: ?>
              —
<?php endif; ?>
            </td>
          </tr>
<?php endwhile; ?>
<?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <a class="book-tab" href="booking.php">Book Now</a>
</body>
</html>
<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
