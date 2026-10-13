<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Contact';
$supportEmail = get_setting($pdo, 'support_email');
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell narrow-shell"><h1 class="page-title">Contact the library team</h1><?php if ($supportEmail !== ''): ?><p>For account or material questions, email <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p><?php else: ?><p class="text-secondary">Contact details have not been configured. Please contact the university library directly.</p><?php endif; ?></main>
<?php require __DIR__ . '/includes/footer.php'; ?>