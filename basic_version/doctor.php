<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require __DIR__ . '/includes/booking_rules.php';
require __DIR__ . '/includes/mail.php';

$conn = db();

$error = "";
$notice = $_SESSION['flash'] ?? "";
unset($_SESSION['flash']);

function doctor_logged_in() {
    return isset($_SESSION['doctor_id']);
}

function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_ok() {
    return is_string($_POST['csrf'] ?? null)
        && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

function send_cancel_email($row, $reason) {
    $base = site_base();
    $when = date("D, j M Y, g:i A", strtotime($row['slot_start']));

    $body = "Dear " . $row['patient_name'] . ",\n\n"
          . "Your appointment at J&B Family Clinic has been cancelled.\n\n"
          . "Doctor: " . $row['doctor'] . "\n"
          . "Date and time: " . $when . "\n"
          . "Reason: " . $reason . "\n\n"
          . "To book another appointment, visit:\n"
          . $base . "/booking.php\n\n"
          . "J&B Family Clinic\n";

    return send_mail(
        $row['patient_email'],
        "Appointment cancelled - " . date("j M Y", strtotime($row['slot_start'])),
        $body
    );
}

function send_reschedule_email($row, $newStart) {
    $base = site_base();
    $when = date("D, j M Y, g:i A", strtotime($newStart));

    $body = "Dear " . $row['patient_name'] . ",\n\n"
          . "Your appointment at J&B Family Clinic has been rescheduled.\n\n"
          . "Doctor: " . $row['doctor'] . "\n"
          . "New date and time: " . $when . "\n\n";

    if (!empty($row['manage_token'])) {
        $body .= "To view or change your appointment, log in and open:\n"
               . $base . "/my_appointments.php\n\n"
               . "Your booking code: " . $row['manage_token'] . "\n\n";
    }

    $body .= "We look forward to seeing you.\n"
           . "J&B Family Clinic\n";

    return send_mail(
        $row['patient_email'],
        "Appointment rescheduled - " . date("j M Y", strtotime($newStart)),
        $body
    );
}

$action = post_str('action');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $email = post_str('email');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if (!csrf_ok()) {
        $error = "Your session expired. Please try again.";
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, name, password_hash FROM doctors WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $doctor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($doctor && password_verify($password, $doctor['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'], $_SESSION['user_role']);
            $_SESSION['doctor_id'] = (int) $doctor['id'];
            $_SESSION['doctor_name'] = $doctor['name'];
            header("Location: doctor.php");
            exit;
        }

        $error = "Invalid email or password.";
    }
}
elseif (get_str('logout') === '1') {
    $_SESSION = [];
    session_destroy();
    header("Location: doctor.php");
    exit;
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'cancel' && doctor_logged_in()) {
    if (!csrf_ok()) {
        $error = "Your session expired. Please try again.";
    } else {
        $id = (int) post_str('id');
        $reason = post_str('reason');

        if ($id <= 0) {
            $error = "That appointment could not be found.";
        } elseif ($reason === '') {
            $error = "Please enter a reason for the cancellation.";
        } elseif (strlen($reason) > 500) {
            $error = "The reason must be 500 characters or fewer.";
        } else {
            $stmt = mysqli_prepare($conn,
                "SELECT id, patient_name, patient_email, doctor, slot_start
                 FROM appointments WHERE id = ? AND status = 'booked'");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$row) {
                $error = "That appointment is no longer booked.";
            } else {
                $stmt = mysqli_prepare($conn,
                    "UPDATE appointments SET status = 'cancelled'
                     WHERE id = ? AND status = 'booked'");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                $changed = mysqli_stmt_affected_rows($stmt);
                mysqli_stmt_close($stmt);

                if ($changed === 1) {
                    $sent = send_cancel_email($row, $reason);
                    $_SESSION['flash'] = "Appointment for " . $row['patient_name'] . " cancelled."
                        . ($sent ? " The patient has been emailed." : " The cancellation email could not be sent.");
                    header("Location: doctor.php");
                    exit;
                }

                $error = "That appointment is no longer booked.";
            }
        }
    }
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reschedule' && doctor_logged_in()) {
    if (!csrf_ok()) {
        $error = "Your session expired. Please try again.";
    } else {
        $id = (int) post_str('id');
        $date = post_str('date');
        $time = post_str('start');
        $newStart = $date . ' ' . $time . ':00';

        if ($id <= 0) {
            $error = "That appointment could not be found.";
        } elseif ($date === '' || $time === '') {
            $error = "Please choose a date and time.";
        } else {
            $stmt = mysqli_prepare($conn,
                "SELECT id, patient_name, patient_email, doctor, slot_start, manage_token
                 FROM appointments WHERE id = ? AND status = 'booked'");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if (!$row) {
                $error = "That appointment is no longer booked.";
            } else {
                $error = validate_slot($row['doctor'], $newStart);

                if ($error === '') {
                    if ($newStart === $row['slot_start']) {
                        $error = "That is already the appointment time.";
                    } else {
                        $stmt = mysqli_prepare($conn,
                            "UPDATE appointments SET slot_start = ?
                             WHERE id = ? AND status = 'booked'");
                        mysqli_stmt_bind_param($stmt, "si", $newStart, $id);

                        try {
                            mysqli_stmt_execute($stmt);
                            $changed = mysqli_stmt_affected_rows($stmt);
                            mysqli_stmt_close($stmt);

                            if ($changed === 1) {
                                $sent = send_reschedule_email($row, $newStart);
                                $_SESSION['flash'] = "Appointment for " . $row['patient_name']
                                    . " moved to " . date("D, j M Y, g:i A", strtotime($newStart)) . "."
                                    . ($sent ? " The patient has been emailed." : " The update email could not be sent.");
                                header("Location: doctor.php");
                                exit;
                            }

                            $error = "That appointment is no longer booked.";
                        } catch (mysqli_sql_exception $e) {
                            mysqli_stmt_close($stmt);
                            if ($e->getCode() == 1062) {
                                $error = "That time slot is already booked. Please choose another time.";
                            } else {
                                $error = "Could not reschedule the appointment. Please try again.";
                            }
                        }
                    }
                }
            }
        }
    }
}

if (!doctor_logged_in()) {
    mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Doctor Login | J&amp;B Family Clinic</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header class="top-header">
    <a class="logo-link" href="index.html" aria-label="J&B Family Clinic Home">
      <img class="clinic-logo" src="assets/logo.png" alt="J&B Family Clinic logo">
    </a>
  </header>

  <main class="page">
    <section class="booking-form">
      <h2>Doctor Login</h2>
      <p>Clinic staff sign in here.</p>

      <form method="post" action="doctor.php">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required
               autocomplete="email" placeholder="name@example.com"
               pattern="[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9\-]+\.)+[A-Za-z]{2,}|[A-Za-z0-9._%+\-]+@localhost"
               title="Enter an email like name@example.com.">
        <span class="input-error" data-error="Email must contain one @ and a valid domain, e.g. name@example.com."></span>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required
               placeholder="Password" autocomplete="current-password">
        <span class="input-error" data-error="Enter your password."></span>

        <p class="form-msg <?= $error !== '' ? 'error' : '' ?>"><?= h($error) ?></p>

        <button type="submit">Log In</button>
      </form>

      <p>Patient? <a href="login.php">Patient login</a>.</p>
    </section>
  </main>

</body>
</html>
<?php
    exit;
}

$result = mysqli_query($conn,
    "SELECT id, patient_name, patient_email, phone, doctor, slot_start, status
     FROM appointments ORDER BY slot_start");
$appointments = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_free_result($result);
mysqli_close($conn);

$minDate = date("Y-m-d");
$maxDate = date("Y-m-d", strtotime("+2 days"));

$openHour = (int) substr($openStart, 0, 2);
$closeHour = (int) substr($openEnd, 0, 2);
$closeMin = (int) substr($openEnd, 3, 2);

$slotTimes = [];
for ($hour = $openHour; $hour <= $closeHour; $hour++) {
    for ($min = 0; $min < 60; $min += 20) {
        if ($hour === $closeHour && $min > $closeMin) {
            break;
        }
        $slotTimes[] = sprintf("%02d:%02d", $hour, $min);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments | J&amp;B Family Clinic</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <header class="top-header">
    <a class="logo-link" href="index.html" aria-label="J&B Family Clinic Home">
      <img class="clinic-logo" src="assets/logo.png" alt="J&B Family Clinic logo">
    </a>

    <div class="staff-bar">
      <span>Signed in as <?= h($_SESSION['doctor_name'] ?? '') ?></span>
      <a class="book-now" href="doctor.php?logout=1">Log out</a>
    </div>
  </header>

  <main class="page">

    <div class="staff-head">
      <div>
        <p class="eyebrow">STAFF</p>
        <h1>Appointments</h1>
      </div>
    </div>

    <p class="form-msg <?= $notice !== '' ? 'success' : '' ?>"><?= h($notice) ?></p>
    <p class="form-msg <?= $error !== '' ? 'error' : '' ?>"><?= h($error) ?></p>

    <div class="table-wrap">
      <table class="appointments">
        <thead>
          <tr>
            <th>Date and time</th>
            <th>Patient</th>
            <th>Contact</th>
            <th>Doctor</th>
            <th>Status</th>
            <th>Reschedule</th>
            <th>Cancel</th>
          </tr>
        </thead>
        <tbody>
<?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= h(date("D, j M Y, g:i A", strtotime($a['slot_start']))) ?></td>
            <td><?= h($a['patient_name']) ?></td>
            <td>
              <?= h($a['patient_email']) ?>
<?php if ($a['phone'] !== ''): ?><br><?= h($a['phone']) ?><?php endif; ?>
            </td>
            <td><?= h($a['doctor']) ?></td>
            <td><span class="status status-<?= h($a['status']) ?>"><?= h($a['status']) ?></span></td>
            <td>
<?php if ($a['status'] === 'booked' && strtotime($a['slot_start']) >= time()): ?>
              <form method="post" class="reschedule-form" action="doctor.php">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="date" name="date" required
                       min="<?= $minDate ?>" max="<?= $maxDate ?>"
                       value="<?= h(substr($a['slot_start'], 0, 10)) ?>">
                <select name="start" required>
                  <option value="">Time</option>
<?php foreach ($slotTimes as $t): ?>
                  <option value="<?= $t ?>"<?= $t === substr($a['slot_start'], 11, 5) ? ' selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
                </select>
                <button type="submit">Save</button>
              </form>
<?php endif; ?>
            </td>
            <td>
<?php if ($a['status'] === 'booked'): ?>
              <form method="post" class="cancel-form" action="doctor.php">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="text" name="reason" maxlength="500" placeholder="Reason" required>
                <button type="submit">Cancel</button>
              </form>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </main>

</body>
</html>
