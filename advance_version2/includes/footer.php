<?php require_once __DIR__ . '/site.php'; ?>
    <footer class="mt-16 border-t border-teal-900/20 bg-teal-900 text-teal-50">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3 lg:px-8">
            <div>
                <p class="text-lg font-bold"><?= site_h($site['brand']['name']) ?></p>
                <p class="mt-2 text-sm text-teal-100"><?= site_h($site['footer']['address']) ?></p>
            </div>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-teal-200">Opening hours</p>
                <p class="mt-2 text-sm text-teal-100"><?= site_h($site['footer']['hours']) ?></p>
            </div>
            <div>
                <p class="text-sm font-bold uppercase tracking-wide text-teal-200">Quick links</p>
                <ul class="mt-2 space-y-1 text-sm">
<?php foreach ($site['footer']['links'] as $link): ?>
                    <li><a class="text-teal-100 underline-offset-4 hover:text-white hover:underline" href="<?= site_h($link['href']) ?>"><?= site_h($link['label']) ?></a></li>
<?php endforeach; ?>
                </ul>
            </div>
        </div>
    </footer>

    <a class="book-tab" href="<?= site_h($site['cta']['href']) ?>"><?= site_h($site['cta']['label']) ?></a>
</body>
</html>
