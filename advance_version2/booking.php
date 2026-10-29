<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require __DIR__ . '/includes/mail.php';
require_patient('booking.php');

$conn = db();

$stmt = mysqli_prepare($conn, 'SELECT name, email, phone FROM patients WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$patient) {
    mysqli_close($conn);
    logout_user();
    header('Location: login.php');
    exit;
}

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

function send_booking_email($name, $email, $doctor, $start, $code) {
    $base = site_base();
    $when = date("D, j M Y, g:i A", strtotime($start)) . " (SGT)";

    $body = "Hi " . $name . ",\n\n"
          . "Your appointment at J&B Family Clinic is confirmed.\n\n"
          . "Doctor: " . $doctor . "\n"
          . "Date and time: " . $when . "\n"
          . "Booking code: " . $code . "\n\n"
          . "To change the date of your appointment, log in and open:\n"
          . $base . "/my_appointments.php\n\n"
          . "See you soon,\n"
          . "J&B Family Clinic\n";

    return send_mail($email, "Booking confirmed - " . $code, $body);
}

$error = '';
$selectedDoctor = get_str('doctor');
$selectedDate = get_str('date');
$selectedTime = '';
$phoneValue = $patient['phone'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDoctor = post_str('doctor');
    $selectedDate = post_str('date');
    $selectedTime = post_str('start');
    $phoneValue = post_str('phone');

    if (!csrf_ok()) {
        $error = 'Your session expired. Please reload the page and try again.';
    } elseif ($phoneValue === '') {
        $error = 'Please enter your phone number.';
    } else {
        $error = validate_phone($phoneValue);
    }

    if ($error === '') {
        $start = $selectedDate . ' ' . $selectedTime . ':00';
        $error = validate_slot($selectedDoctor, $start);
    }

    if ($error === '') {
        $available = available_times($conn, $selectedDoctor, $selectedDate);
        if (!in_array($selectedTime, $available, true)) {
            $error = 'That appointment time is no longer available. Please choose another time.';
        }
    }

    if ($error === '') {
        $start = $selectedDate . ' ' . $selectedTime . ':00';
        $code = new_booking_code($conn);

        $stmt = mysqli_prepare($conn,
            "INSERT INTO appointments
             (patient_id, patient_name, patient_email, phone, doctor, slot_start, manage_token)
             VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issssss',
            $_SESSION['user_id'], $patient['name'], $patient['email'],
            $phoneValue, $selectedDoctor, $start, $code);

        try {
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $update = mysqli_prepare($conn, "UPDATE patients SET phone = ? WHERE id = ?");
            mysqli_stmt_bind_param($update, 'si', $phoneValue, $_SESSION['user_id']);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);

            $sent = send_booking_email($patient['name'], $patient['email'], $selectedDoctor, $start, $code);
            mysqli_close($conn);

            header('Location: confirmation.php?code=' . urlencode($code) . '&sent=' . ($sent ? '1' : '0'));
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() == 1062
                ? 'Sorry, that time slot is already booked. Please choose another time.'
                : 'Could not save the appointment. Please try again.';
        }
    }
}

$scheduleError = '';
$available = [];

if ($selectedDoctor !== '' && $selectedDate !== '') {
    $scheduleError = schedule_error($selectedDoctor, $selectedDate);
    if ($scheduleError === '') {
        $available = available_times($conn, $selectedDoctor, $selectedDate);
    }
}

mysqli_close($conn);

$displayError = $error !== '' ? $error : $scheduleError;
?>
<?php
$pageTitle = 'Book an Appointment';
require_once __DIR__ . '/includes/header.php';
?>

<section class="relative isolate overflow-hidden bg-teal-900" aria-label="Book an appointment">
  <img src="assets/indexbanner.jpg" alt="" class="absolute inset-0 h-full w-full object-cover opacity-60">
  <div class="relative mx-auto flex min-h-[260px] max-w-7xl flex-col items-center justify-center px-4 py-16 text-center sm:px-6 lg:px-8">
    <p class="text-sm font-bold uppercase tracking-[0.2em] text-teal-100">J&amp;B FAMILY CLINIC</p>
    <h1 class="mt-3 text-4xl font-bold text-white sm:text-5xl">Ready to book?</h1>
    <p class="mt-3 text-lg text-teal-50">Your health is our priority.</p>
  </div>
</section>

  <main class="page">
    <form class="booking-form" method="get" action="booking.php">
      <h2>1. Check Doctor Schedule</h2>

      <label for="doctor">Doctor:</label>
      <select id="doctor" name="doctor" required>
        <option value="">— choose a doctor —</option>
<?php foreach ($doctors as $name => $doc): ?>
        <option value="<?= h($name) ?>"<?= $name === $selectedDoctor ? ' selected' : '' ?>><?= h($doc['label']) ?></option>
<?php endforeach; ?>
      </select>
      <span class="input-error" data-error="Please choose a doctor."></span>

      <label for="date">Date:</label>
      <input type="date" id="date" name="date" required
             min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+2 days')) ?>"
             value="<?= h($selectedDate) ?>">
      <span class="input-error" data-error="Please choose a date."></span>

      <button type="submit">Show Available Times</button>

<?php if ($selectedDoctor !== '' && isset($doctorDays[$selectedDoctor])): ?>
      <p>
        <?= h($selectedDoctor) ?> consults on <?= h(doctor_days_text($selectedDoctor)) ?>,
        <?= h(time_label($openStart)) ?>–<?= h(time_label($openEnd)) ?> (SGT).
      </p>
<?php endif; ?>

<?php if ($displayError !== ''): ?>
      <p class="form-msg error"><?= h($displayError) ?></p>
<?php endif; ?>
    </form>

<?php if ($selectedDoctor !== '' && $selectedDate !== '' && $scheduleError === ''): ?>
<?php if (count($available) === 0): ?>
    <section class="booking-form">
      <h2>2. Confirm Appointment</h2>
      <p class="form-msg error">No available slots for this doctor on this date.</p>
    </section>
<?php else: ?>
    <form class="booking-form" method="post" action="booking.php">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="doctor" value="<?= h($selectedDoctor) ?>">
      <input type="hidden" name="date" value="<?= h($selectedDate) ?>">

      <h2>2. Confirm Appointment</h2>

      <p><strong>Patient:</strong> <?= h($patient['name']) ?></p>
      <p><strong>Doctor:</strong> <?= h($selectedDoctor) ?></p>
      <p><strong>Date:</strong> <?= h(date('D, j M Y', strtotime($selectedDate))) ?></p>

      <label for="start">Time:</label>
      <select id="start" name="start" required>
        <option value="">— choose an available time —</option>
<?php foreach ($available as $time): ?>
        <option value="<?= h($time) ?>"<?= $time === $selectedTime ? ' selected' : '' ?>><?= h(time_label($time)) ?></option>
<?php endforeach; ?>
      </select>
      <span class="input-error" data-error="Please choose a time."></span>

      <label for="phone">Phone (+65):</label>
      <input type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel"
             pattern="(?:\+?65)?[\s\-]?[89][0-9]{3}[\s\-]?[0-9]{4}"
             placeholder="+65 9123 4567"
             title="Enter a Singapore number: +65 followed by 8 digits starting with 8 or 9."
             required value="<?= h($phoneValue) ?>">
      <span class="input-error" data-error="Enter a Singapore number: +65 and 8 digits starting with 8 or 9."></span>

      <button type="submit">Confirm Booking</button>
    </form>
<?php endif; ?>
<?php endif; ?>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
