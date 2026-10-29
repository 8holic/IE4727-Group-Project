<?php
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/booking_rules.php';

require_patient('edit_profile.php');

$conn = db();

$message = '';
$messageClass = '';


/* =========================================================
   LOAD CURRENT PATIENT INFORMATION
   ========================================================= */

$stmt = mysqli_prepare(
    $conn,
    'SELECT name, email, phone
     FROM patients
     WHERE id = ?'
);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $_SESSION['user_id']
);

mysqli_stmt_execute($stmt);

$patient = mysqli_fetch_assoc(
    mysqli_stmt_get_result($stmt)
);

mysqli_stmt_close($stmt);


/* Patient account not found */
if (!$patient) {

    logout_user();

    header('Location: login.php');
    exit;
}


/* =========================================================
   UPDATE PROFILE
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = post_str('name');
    $phone = post_str('phone');


    /* CSRF protection */
    if (!csrf_ok()) {

        $message =
            'Your session expired. Please refresh the page and try again.';

        $messageClass = 'error';


    /* Check name */
    } elseif ($name === '') {

        $message = 'Please enter your full name.';
        $messageClass = 'error';


    } elseif (strlen($name) < 2 || strlen($name) > 100) {

        $message =
            'Name must contain between 2 and 100 characters.';

        $messageClass = 'error';


    /* Check phone */
    } elseif ($phone === '') {

        $message = 'Please enter your mobile number.';
        $messageClass = 'error';


    } elseif (validate_phone($phone) !== '') {

        $message =
            'Please enter a valid Singapore mobile number.';

        $messageClass = 'error';


    } else {

        try {

            mysqli_begin_transaction($conn);


            /* Update patient account */
            $stmt = mysqli_prepare(
                $conn,
                'UPDATE patients
                 SET name = ?, phone = ?
                 WHERE id = ?'
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


            /*
             * Existing appointments store copies of
             * patient name and phone.
             *
             * Update them so the doctor dashboard
             * also shows the latest information.
             */
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


            /* Update session name immediately */
            $_SESSION['user_name'] = $name;


            /* Update values shown in the form */
            $patient['name'] = $name;
            $patient['phone'] = $phone;


            $message = 'Profile updated successfully.';
            $messageClass = 'success';


        } catch (mysqli_sql_exception $e) {

            mysqli_rollback($conn);

            $message =
                'Profile could not be updated. Please try again.';

            $messageClass = 'error';
        }
    }


    /*
     * Keep entered values when validation fails
     */
    if ($messageClass === 'error') {

        $patient['name'] = $name;
        $patient['phone'] = $phone;
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >

  <title>
    Edit Profile | J&amp;B Family Clinic
  </title>

  <link
    rel="stylesheet"
    href="style.css"
  >

</head>


<body>


<header class="top-header">

  <a
    class="logo-link"
    href="index.html"
    aria-label="J&B Family Clinic Home"
  >

    <img
      class="clinic-logo"
      src="assets/logo.png"
      alt="J&B Family Clinic logo"
    >

  </a>


  <nav
    class="top-nav"
    aria-label="Main navigation"
  >

    <ul>

      <li>
        <a href="index.html">
          Home
        </a>
      </li>

      <li>
        <a href="about.html">
          About Us
        </a>
      </li>

      <li>
        <a href="service.html">
          Services
        </a>
      </li>

      <li>
        <a href="location.html">
          Location
        </a>
      </li>

      <li>
        <a href="my_appointments.php">
          My Appointments
        </a>
      </li>

      <li>
        <a href="logout.php">
          Logout
        </a>
      </li>

      <li>
        <a
          class="book-now"
          href="booking.php"
        >
          Book Now
        </a>
      </li>

    </ul>

  </nav>

</header>



<main class="page">


  <section class="booking-form edit-profile-form">

    <p class="eyebrow">
      PATIENT ACCOUNT
    </p>

    <h1>
      Edit Profile
    </h1>

    <p>
      Update your personal details below.
    </p>


    <?php if ($message !== ''): ?>

      <p class="form-msg <?= h($messageClass) ?>">
        <?= h($message) ?>
      </p>

    <?php endif; ?>


    <form
      method="post"
      action="edit_profile.php"
    >


      <!-- CSRF TOKEN -->

      <input
        type="hidden"
        name="csrf"
        value="<?= h(csrf_token()) ?>"
      >


      <!-- NAME -->

      <label for="name">
        Full Name:
      </label>

      <input
        type="text"
        id="name"
        name="name"
        minlength="2"
        maxlength="100"
        autocomplete="name"
        required
        value="<?= h($patient['name']) ?>"
      >


      <!-- EMAIL -->

      <label for="email">
        Email:
      </label>

      <input
        type="email"
        id="email"
        value="<?= h($patient['email']) ?>"
        readonly
      >

      <small>
        Email is used for login and cannot be changed here.
      </small>


      <!-- PHONE -->

      <label for="phone">
        Phone (+65):
      </label>

      <input
        type="tel"
        id="phone"
        name="phone"
        inputmode="tel"
        autocomplete="tel"
        pattern="(?:\+?65)?[\s\-]?[89][0-9]{3}[\s\-]?[0-9]{4}"
        placeholder="+65 9123 4567"
        title="Enter an 8-digit Singapore mobile number starting with 8 or 9."
        required
        value="<?= h($patient['phone']) ?>"
      >


      <button type="submit">
        Save Changes
      </button>


      <a
        class="profile-back-link"
        href="my_appointments.php"
      >
        Back to My Appointments
      </a>


    </form>

  </section>


</main>


</body>

</html>

<?php

mysqli_close($conn);

?>