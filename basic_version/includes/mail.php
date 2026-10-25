<?php
// Mercury (XAMPP) local domains from MERCURY.INI:
// localhost, localhost.net, localhost.org, localhost.com.
// Project accounts use the @localhost.net form by convention.
const CLINIC_MAIL_FROM = "jnb@localhost.net";

function site_base() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $root = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/booking.php'));
    return rtrim("http://" . $host . $root, "/");
}

function is_local_mailbox($to) {
    $to = trim($to);
    $at = strrpos($to, '@');
    if ($at === false) {
        return false;
    }

    $domain = strtolower(substr($to, $at + 1));
    $localDomains = ['localhost', 'localhost.net', 'localhost.org', 'localhost.com'];

    return in_array($domain, $localDomains, true);
}

function send_mail($to, $subject, $body) {
    // External delivery is not allowed; only Mercury's local mailboxes.
    if (!is_local_mailbox($to)) {
        return false;
    }

    $headers = "From: \"J&B Family Clinic\" <" . CLINIC_MAIL_FROM . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $body, $headers);
}
?>
