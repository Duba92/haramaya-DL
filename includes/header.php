<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$siteTitle = get_setting($pdo, 'site_title', SITE_NAME);
$siteDescription = get_setting($pdo, 'site_description', 'Curriculum-aligned learning materials for Haramaya University.');
$logoFile = basename(get_setting($pdo, 'site_logo', ''));
$hasLogo = $logoFile !== '' && preg_match('/^[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|svg)$/i', $logoFile) === 1;
$flashMessage = get_flash();
$scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isAdmin = check_role('admin');
if (get_setting($pdo, 'maintenance_mode', '0') === '1' && !$isAdmin && $scriptName !== 'login.php') {
    http_response_code(503);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Maintenance</title>';
    echo '<main><h1>Library maintenance</h1><p>The library is temporarily unavailable. Please try again later.</p></main></html>';
    exit;
}

$dashboardUrl = match ($_SESSION['role'] ?? '') {
    'admin' => 'admin/index.php',
    'instructor' => 'instructor/dashboard.php',
    'student' => 'student/dashboard.php',
    default => '',
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($siteDescription) ?>">
    <title><?= e(($pageTitle ?? '') !== '' ? $pageTitle . ' | ' . $siteTitle : $siteTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(site_url('assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <nav class="navbar navbar-expand-lg" aria-label="Main navigation">
        <div class="container">
            <a class="navbar-brand" href="<?= e(site_url('index.php')) ?>">
                <?php if ($hasLogo): ?><img class="brand-mark" src="<?= e(site_url('assets/images/' . $logoFile)) ?>" alt="">
                <?php else: ?><span class="brand-mark" aria-hidden="true">HU</span><?php endif; ?>
                <span><?= e($siteTitle) ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <form class="nav-search ms-lg-auto me-lg-3" action="<?= e(site_url('search.php')) ?>" method="get" role="search">
                    <label class="visually-hidden" for="nav-search">Search materials</label>
                    <input class="form-control" id="nav-search" type="search" name="q" placeholder="Search courses and materials" autocomplete="off" aria-autocomplete="list" aria-controls="search-suggestions">
                    <button class="btn btn-link" type="submit" aria-label="Search"><i class="bi bi-search" aria-hidden="true"></i></button>
                    <div class="search-suggestions" id="search-suggestions" role="listbox" data-suggestions-url="<?= e(site_url('api/suggestions.php')) ?>" hidden></div>
                </form>
                <ul class="navbar-nav align-items-lg-center gap-lg-2">
                    <li class="nav-item"><a class="nav-link" href="<?= e(site_url('browse.php')) ?>">Browse</a></li>
                    <?php if ($dashboardUrl !== ''): ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(site_url($dashboardUrl)) ?>">Dashboard</a></li>
                        <li class="nav-item"><span class="nav-user"><?= e($_SESSION['name'] ?? '') ?></span></li>
                        <li class="nav-item"><form method="post" action="<?= e(site_url('logout.php')) ?>">
                            <?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary" type="submit">Sign out</button>
                        </form></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="<?= e(site_url('login.php')) ?>">Sign in</a></li>
                        <li class="nav-item"><a class="btn btn-sm btn-primary" href="<?= e(site_url('register.php')) ?>">Register</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><button class="btn btn-sm btn-outline-secondary theme-toggle" type="button" data-theme-toggle aria-label="Toggle dark mode" title="Toggle dark mode"><i class="bi bi-moon-stars" aria-hidden="true"></i></button></li>
                </ul>
            </div>
        </div>
    </nav>
</header>
<?php if ($flashMessage !== null): ?>
    <div class="container mt-3"><div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="status">
        <?= e($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div></div>
<?php endif; ?>
<div id="page-content">