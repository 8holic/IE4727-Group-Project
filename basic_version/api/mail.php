<?php
// Sender mailbox configured in Mercury (XAMPP). Local mailboxes are @localhost.
// Without a relay host, Mercury only delivers to local addresses.
const CLINIC_MAIL_FROM = "deepseek@localhost";

function site_base() {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // dirname() returns backslashes on Windows, so normalise to URL slashes.
    $root = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/db.php')));
    return rtrim("http://" . $host . $root, "/");
}

function send_mail($to, $subject, $body) {
    $headers = "From: \"J&B Family Clinic\" <" . CLINIC_MAIL_FROM . ">\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($to, $subject, $body, $headers);
}
