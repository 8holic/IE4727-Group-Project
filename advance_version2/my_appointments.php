<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';
require_patient('my_appointments.php');

$conn = db();
$stmt = mysqli_prepare($conn,
    'SELECT id, doctor, slot_start, status FROM appointments
     WHERE patient_id = ? ORDER BY slot_start DESC');
mysqli_stmt_bind_param($stmt, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$appointments = mysqli_stmt_get_result($stmt);

$message = get_str('message');
?>
<?php
$pageTitle = 'My Appointments';
require_once __DIR__ . '/includes/header.php';
?>

  <main class="page">
    <div class="staff-head">
      <div>
        <p class="eyebrow">PATIENT ACCOUNT</p>
        <h1>My Appointments</h1>
        <p>Logged in as <?= h($_SESSION['user_name']) ?>.</p>
        <a class="edit-profile-btn" href="edit_profile.php">
          Edit Profile
        </a>
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
              <a class="font-semibold text-teal-700 hover:underline" href="manage.php?id=<?= (int) $row['id'] ?>">Reschedule</a> |
              <a class="font-semibold text-rose-700 hover:underline" href="cancel.php?id=<?= (int) $row['id'] ?>">Cancel</a>
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
