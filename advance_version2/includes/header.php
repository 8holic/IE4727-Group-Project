<?php
require_once __DIR__ . '/site.php';

$pageTitle = $pageTitle ?? $site['brand']['name'];
$activeKey = $activeKey ?? '';

if ($activeKey === '') {
    $activeMap = [
        'index.php' => 'home',
        'doctor.php' => 'staff',
        'my_appointments.php' => 'appointments',
        'manage.php' => 'appointments',
        'cancel.php' => 'appointments',
        'confirmation.php' => 'appointments',
        'register.php' => 'login',
    ];
    $activeKey = $activeMap[$currentPage] ?? pathinfo($currentPage, PATHINFO_FILENAME);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= site_h($pageTitle) ?> | <?= site_h($site['brand']['name']) ?></title>
    <link rel="stylesheet" href="assets/tailwind.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="<?= site_h($site['brand']['home']) ?>" class="flex shrink-0 items-center gap-3">
                <img src="<?= site_h($site['brand']['logo']) ?>" alt="<?= site_h($site['brand']['alt']) ?>" class="h-12 w-auto sm:h-14">
            </a>

            <nav aria-label="Main navigation" class="flex min-w-0 flex-1 items-center justify-end gap-1 overflow-x-auto">
<?php foreach ($site['nav'] as $item): ?>
<?php $isActive = $activeKey === $item['key']; ?>
                <a href="<?= site_h($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>
                   class="whitespace-nowrap rounded-lg px-3 py-2 text-sm font-semibold transition <?= $isActive ? 'bg-teal-50 text-teal-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' ?>">
                    <?= site_h($item['label']) ?>
                </a>
<?php endforeach; ?>
                <a href="<?= site_h($site['cta']['href']) ?>" class="ml-2 whitespace-nowrap rounded-full bg-rose-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-rose-700">
                    <?= site_h($site['cta']['label']) ?>
                </a>
            </nav>
        </div>
    </header>
