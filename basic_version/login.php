<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';

$conn = db();
$next = allowed_next($_GET['next'] ?? '');
$required = ($_GET['required'] ?? '') === '1';
$message = '';
$messageClass = '';

if (is_patient()) {
    header('Location: ' . $next);
    exit;
}

if (isset($_GET['registered'])) {
    $message = 'Registration successful. Please sign in.';
    $messageClass = 'success';
} elseif ($required && $next === 'booking.php') {
    $message = 'Please sign in or create an account to book an appointment.';
    $messageClass = 'info';
} elseif ($required && $next === 'my_appointments.php') {
    $message = 'Please sign in to view or manage your appointments.';
    $messageClass = 'info';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = post_str('email');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $next = allowed_next($_POST['next'] ?? '');

    if (!csrf_ok()) {
        $message = 'Your session expired. Please try again.';
        $messageClass = 'error';
    } elseif ($email === '' || $password === '') {
        $message = 'Please enter your email and password.';
        $messageClass = 'error';
    } else {
        $stmt = mysqli_prepare($conn,
            'SELECT id, name, email, password_hash FROM patients WHERE email = ?');
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($patient && password_verify($password, $patient['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['doctor_id'], $_SESSION['doctor_name']);
            $_SESSION['user_id'] = (int) $patient['id'];
            $_SESSION['user_name'] = $patient['name'];
            $_SESSION['user_email'] = $patient['email'];
            $_SESSION['user_role'] = 'patient';

            header('Location: ' . $next);
            exit;
        }

        $message = 'Incorrect email or password.';
        $messageClass = 'error';
    }
}

mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Login | J&amp;B Family Clinic</title>
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
        <li><a class="active" href="login.php">Patient Login</a></li>
        <li><a href="doctor.php">Employee Login</a></li>
        <li><a class="book-now" href="booking.php">Book Now</a></li>
      </ul>
    </nav>
  </header>

  <main class="page">
    <form class="booking-form" method="post" action="login.php">
      <h2>Patient Login</h2>
      <p>Log in to book and manage your appointments.</p>

      <p class="form-msg <?= h($messageClass) ?>"><?= h($message) ?></p>

      <input type="hidden" name="next" value="<?= h($next) ?>">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required
             autocomplete="email" placeholder="name@example.com"
             pattern="[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9\-]+\.)+[A-Za-z]{2,}|[A-Za-z0-9._%+\-]+@localhost"
             title="Enter an email like name@example.com."
             value="<?= h($_POST['email'] ?? '') ?>">
      <span class="input-error" data-error="Email must contain one @ and a valid domain, e.g. name@example.com."></span>

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required
             placeholder="Password" autocomplete="current-password">
      <span class="input-error" data-error="Enter your password."></span>

      <button type="submit">Log In</button>

      <p>New patient? <a href="register.php?next=<?= urlencode($next) ?>">Create an account</a>.</p>
    </form>
  </main>

  <a class="book-tab" href="booking.php">Book Now</a>
</body>
</html>
