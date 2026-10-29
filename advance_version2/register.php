<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require __DIR__ . '/includes/mail.php';

$conn = db();
$next = allowed_next($_REQUEST['next'] ?? '');
$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = post_str('name');
    $email    = post_str('email');
    $phone    = post_str('phone');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!csrf_ok()) {
        $message = 'Your session expired. Please try again.';
        $messageClass = 'error';
    } elseif ($name === '' || $email === '' || $phone === '' || $password === '') {
        $message = 'Please complete all fields.';
        $messageClass = 'error';
    } elseif (($emailError = email_error($email)) !== '') {
        $message = $emailError;
        $messageClass = 'error';
    } elseif (validate_phone($phone) !== '') {
        $message = 'Please enter a valid Singapore phone number (8 digits, starting with 8 or 9).';
        $messageClass = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
        $messageClass = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $messageClass = 'error';
    } else {
        $exists = false;
        foreach (['patients', 'doctors'] as $table) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM {$table} WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $exists = true;
            }
            mysqli_stmt_close($stmt);
            if ($exists) {
                break;
            }
        }

        if ($exists) {
            $message = 'An account with this email already exists.';
            $messageClass = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn,
                'INSERT INTO patients (name, email, phone, password_hash) VALUES (?, ?, ?, ?)');
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $hash);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $body = "Hi " . $name . ",\n\n"
                  . "Your J&B Family Clinic patient account has been created.\n\n"
                  . "Email: " . $email . "\n"
                  . "Sign in here: " . site_base() . "/login.php\n\n"
                  . "You can now book and manage appointments.\n\n"
                  . "J&B Family Clinic\n";

            send_mail($email, 'Welcome to J&B Family Clinic', $body);

            header('Location: login.php?registered=1&next=' . urlencode($next));
            exit;
        }
    }
}

mysqli_close($conn);
?>
<?php
$pageTitle = 'Patient Registration';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="page">
    <form class="booking-form" method="post" action="register.php">
      <h2>Patient Registration</h2>
      <p>Create an account before booking an appointment.</p>

      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="next" value="<?= h($next) ?>">

      <label for="name">Full Name:</label>
      <input type="text" id="name" name="name" required maxlength="100"
             placeholder="Full name" autocomplete="name"
             value="<?= h($_POST['name'] ?? '') ?>">
      <span class="input-error" data-error="Please enter your name."></span>

      <label for="email">Email:</label>
      <input type="email" id="email" name="email" required maxlength="150"
             autocomplete="email" placeholder="name@example.com"
             pattern="[A-Za-z0-9._%+\-]+@(?:[A-Za-z0-9\-]+\.)+[A-Za-z]{2,}|[A-Za-z0-9._%+\-]+@localhost"
             title="Enter an email like name@example.com."
             value="<?= h($_POST['email'] ?? '') ?>">
      <span class="input-error" data-error="Email must contain one @ and a valid domain, e.g. name@example.com."></span>

      <label for="phone">Phone (+65):</label>
      <input type="tel" id="phone" name="phone" inputmode="tel" autocomplete="tel"
             pattern="(?:\+?65)?[\s\-]?[89][0-9]{3}[\s\-]?[0-9]{4}"
             placeholder="+65 9123 4567"
             title="Enter a Singapore number: +65 followed by 8 digits starting with 8 or 9."
             required value="<?= h($_POST['phone'] ?? '') ?>">
      <span class="input-error" data-error="Enter a Singapore number: +65 and 8 digits starting with 8 or 9."></span>

      <label for="password">Password:</label>
      <input type="password" id="password" name="password" required minlength="8"
             placeholder="At least 8 characters" autocomplete="new-password">
      <span class="input-error" data-error="Password must be at least 8 characters."></span>

      <label for="confirm_password">Confirm Password:</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
             placeholder="Re-enter password" autocomplete="new-password">
      <span class="input-error" data-error="Password must be at least 8 characters."></span>

      <button type="submit">Register</button>

      <p class="form-msg <?= h($messageClass) ?>"><?= h($message) ?></p>

      <p>Already registered? <a href="login.php?next=<?= urlencode($next) ?>">Log in here</a>.</p>
    </form>
  </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
