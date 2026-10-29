<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id'], $_SESSION['user_role']);
}

function is_patient() {
    return is_logged_in() && $_SESSION['user_role'] === 'patient';
}

function allowed_next($next) {
    $allowed = ['booking.php', 'my_appointments.php'];
    return in_array($next, $allowed, true) ? $next : 'my_appointments.php';
}

function require_patient($next = 'my_appointments.php') {
    if (!is_patient()) {
        header('Location: login.php?next=' . urlencode(allowed_next($next)) . '&required=1');
        exit;
    }
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

function logout_user() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}
?>
