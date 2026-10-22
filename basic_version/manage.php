<?php
require __DIR__ . '/api/booking_rules.php';

$conn = db();

$code = post_str('code');
if ($code === '') {
    $code = get_str('code');
}
$code = strtoupper($code);

function find_booking($conn, $code) {
    if ($code === '') {
        return null;
    }
    $stmt = mysqli_prepare($conn,
        "SELECT id, patient_name, doctor, slot_start, status
         FROM appointments WHERE manage_token = ?");
    mysqli_stmt_bind_param($stmt, "s", $code);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

$booking = find_booking($conn, $code);
$error = "";
$notice = "";
$dateValue = "";
$timeValue = "";

if ($code !== '' && !$booking) {
    $error = "We could not find a booking for that code.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update') {
    if (!$booking) {
        $error = "We could not find a booking for that code.";
    } elseif ($booking['status'] !== 'booked') {
        $error = "This booking can no longer be changed.";
    } else {
        $dateValue = post_str('date');
        $timeValue = post_str('start');
        $start = $dateValue . ' ' . $timeValue . ':00';

        $error = validate_slot($booking['doctor'], $start);

        if ($error === '') {
            $stmt = mysqli_prepare($conn,
                "UPDATE appointments SET slot_start = ?
                 WHERE id = ? AND manage_token = ? AND status = 'booked'");
            mysqli_stmt_bind_param($stmt, "sis", $start, $booking['id'], $code);

            try {
                mysqli_stmt_execute($stmt);
                $notice = "Your appointment has been updated.";
                $booking = find_booking($conn, $code);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $error = "Sorry, that time slot is already booked. Please choose another time.";
                } else {
                    $error = "Could not update the booking. Please try again.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}

if ($dateValue === '' && $booking) {
    $dateValue = substr($booking['slot_start'], 0, 10);
}
if ($timeValue === '' && $booking) {
    $timeValue = substr($booking['slot_start'], 11, 5);
}

$minDate = date("Y-m-d");
$maxDate = date("Y-m-d", strtotime("+2 days"));

$startHour = (int) substr($openStart, 0, 2);
$endHour   = (int) substr($openEnd, 0, 2);
$endMin    = (int) substr($openEnd, 3, 2);

mysqli_close($conn);
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
        <li><a class="active" href="manage.php">Manage Booking</a></li>
        <li><a class="book-now" href="booking.html">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">

<?php if ($booking): ?>
    <section class="booking-form">
      <h2>Manage your booking</h2>

      <p>
        <strong>Patient:</strong>
        <?= htmlspecialchars($booking['patient_name']) ?><br>
        <strong>Doctor:</strong>
        <?= htmlspecialchars($booking['doctor']) ?><br>
        <strong>Current appointment:</strong>
        <?= htmlspecialchars(date("D, j M Y, g:i A", strtotime($booking['slot_start']))) ?><br>
        <strong>Booking code:</strong>
        <span class="booking-code"><?= htmlspecialchars($code) ?></span>
      </p>

<?php if ($booking['status'] !== 'booked'): ?>
      <p class="form-msg error">This booking has been cancelled and can no longer be changed.</p>
<?php else: ?>
      <form method="post" novalidate>
        <input type="hidden" name="code" value="<?= htmlspecialchars($code) ?>">
        <input type="hidden" name="action" value="update">

        <label for="date">New date:</label>
        <input type="date" id="date" name="date" required
               min="<?= $minDate ?>" max="<?= $maxDate ?>"
               value="<?= htmlspecialchars($dateValue) ?>">

        <label for="start">New time:</label>
        <select id="start" name="start" required>
          <option value="">— choose a time —</option>
<?php
    for ($hour = $startHour; $hour <= $endHour; $hour++) {
        for ($min = 0; $min < 60; $min += 20) {
            if ($hour === $endHour && $min > $endMin) {
                break;
            }
            $value = sprintf("%02d:%02d", $hour, $min);
            $selected = $value === $timeValue ? ' selected' : '';
            echo '          <option value="' . $value . '"' . $selected . '>' . $value . '</option>' . "\n";
        }
    }
?>
        </select>

        <p class="form-msg <?= $error !== '' ? 'error' : '' ?>"><?= htmlspecialchars($error) ?></p>
        <p class="form-msg <?= $notice !== '' ? 'success' : '' ?>"><?= htmlspecialchars($notice) ?></p>

        <button type="submit">Save new date</button>
      </form>

      <script>
        // Grey out slots already booked for this doctor and date.
        (function () {
          const dateInput = document.getElementById("date");
          const timeSelect = document.getElementById("start");
          const doctor = <?= json_encode($booking['doctor']) ?>;
          const ownDate = <?= json_encode(substr($booking['slot_start'], 0, 10)) ?>;
          const ownTime = <?= json_encode(substr($booking['slot_start'], 11, 5)) ?>;

          function refreshTaken() {
            const date = dateInput.value;

            for (const opt of timeSelect.options) {
              opt.disabled = false;
            }
            if (!date) {
              return;
            }

            fetch("api/taken.php?date=" + encodeURIComponent(date) +
                  "&doctor=" + encodeURIComponent(doctor))
              .then(r => r.text())
              .then(text => {
                const taken = text ? text.split(",") : [];

                for (const opt of timeSelect.options) {
                  const mine = date === ownDate && opt.value === ownTime;
                  if (opt.value && !mine && taken.includes(opt.value)) {
                    opt.disabled = true;
                  }
                }

                const selected = timeSelect.selectedOptions[0];
                if (selected && selected.disabled) {
                  timeSelect.value = "";
                }
              });
          }

          dateInput.addEventListener("change", refreshTaken);
          refreshTaken();
        })();
      </script>
<?php endif; ?>
    </section>

<?php else: ?>
    <section class="booking-form">
      <h2>Manage your booking</h2>
      <p>Enter the booking code from your confirmation to change your appointment date.</p>

      <form method="post">
        <label for="code">Booking code:</label>
        <input type="text" id="code" name="code" required
               value="<?= htmlspecialchars($code) ?>">

        <p class="form-msg <?= $error !== '' ? 'error' : '' ?>"><?= htmlspecialchars($error) ?></p>

        <button type="submit">Find my booking</button>
      </form>
    </section>
<?php endif; ?>

  </main>

  <a class="book-tab" href="booking.html">Book Now</a>

</body>
</html>
