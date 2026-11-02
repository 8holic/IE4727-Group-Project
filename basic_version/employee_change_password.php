<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';

if (empty($_SESSION['doctor_id'])) {
    header('Location: doctor.php');
    exit;
}

$conn = db();
$message = '';
$messageClass = '';

$stmt = mysqli_prepare($conn, 'SELECT name, password_hash FROM doctors WHERE id = ?');
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['doctor_id']);
mysqli_stmt_execute($stmt);
$doctor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$doctor) {
    logout_user();
    header('Location: doctor.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $newPassword = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    if (!csrf_ok()) {
        $message = 'Your session expired. Please refresh the page and try again.';
        $messageClass = 'error';
    } elseif ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $message = 'Please complete all password fields.';
        $messageClass = 'error';
    } elseif (!password_verify($currentPassword, $doctor['password_hash'])) {
        $message = 'Your current password is incorrect.';
        $messageClass = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters.';
        $messageClass = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'New password and confirmation do not match.';
        $messageClass = 'error';
    } elseif (password_verify($newPassword, $doctor['password_hash'])) {
        $message = 'New password must be different from your current password.';
        $messageClass = 'error';
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, 'UPDATE doctors SET password_hash = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $newHash, $_SESSION['doctor_id']);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($changed === 1) {
            $doctor['password_hash'] = $newHash;
            $message = 'Password changed successfully.';
            $messageClass = 'success';
        } else {
            $message = 'Password could not be changed. Please try again.';
            $messageClass = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Change Password | J&amp;B Family Clinic</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="top-header">
  <a class="logo-link" href="index.html" aria-label="J&B Family Clinic Home">
    <img class="clinic-logo" src="assets/logo.png" alt="J&B Family Clinic logo">
  </a>
  <div class="staff-bar">
    <span>Signed in as <?= h($_SESSION['doctor_name'] ?? $doctor['name']) ?></span>
    <a class="staff-account-link" href="doctor.php">Back to Dashboard</a>
    <a class="book-now" href="doctor.php?logout=1">Log out</a>
  </div>
</header>

<main class="page">
  <section class="booking-form password-form">
    <p class="eyebrow">STAFF ACCOUNT</p>
    <h1>Change Password</h1>
    <p>Enter your current employee password before choosing a new one.</p>

<?php if ($message !== ''): ?>
    <p class="form-msg <?= h($messageClass) ?>"><?= h($message) ?></p>
<?php endif; ?>

    <form method="post" action="employee_change_password.php">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <label for="current_password">Current Password:</label>
      <input type="password" id="current_password" name="current_password"
             autocomplete="current-password" required>

      <label for="new_password">New Password:</label>
      <input type="password" id="new_password" name="new_password"
             minlength="8" autocomplete="new-password" required>
      <small>Use at least 8 characters.</small>

      <label for="confirm_password">Confirm New Password:</label>
      <input type="password" id="confirm_password" name="confirm_password"
             minlength="8" autocomplete="new-password" required>

      <button type="submit">Change Password</button>
      <a class="profile-back-link" href="doctor.php">Back to Dashboard</a>
    </form>
  </section>
</main>
</body>
</html>
<?php
mysqli_close($conn);
?>
