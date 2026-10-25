<?php
require __DIR__ . '/includes/auth.php';
logout_user();
header('Location: index.html');
exit;
?>
