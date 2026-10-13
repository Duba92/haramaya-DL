<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell narrow-shell"><h1 class="page-title">About the digital library</h1><p><?= e(get_setting($pdo, 'site_description', 'A curriculum-aligned collection of learning materials for Haramaya University.')) ?></p><p>Materials are organized by college, department, year, semester, and course. Instructor submissions are reviewed before they become visible to students.</p></main>
<?php require __DIR__ . '/includes/footer.php'; ?>