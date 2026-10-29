<?php
$site = json_decode(file_get_contents(__DIR__ . '/site.json'), true);

if (!is_array($site)) {
    $site = [
        'brand' => ['name' => 'J&B Family Clinic', 'home' => 'index.php', 'logo' => 'assets/logo.png', 'alt' => 'J&B Family Clinic logo'],
        'nav' => [],
        'cta' => ['label' => 'Book Now', 'href' => 'booking.php'],
        'footer' => ['address' => '', 'hours' => '', 'links' => []],
    ];
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');

if (!function_exists('site_h')) {
    function site_h($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
