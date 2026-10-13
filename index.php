<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$stats = $pdo->query(
    'SELECT (SELECT COUNT(*) FROM materials WHERE approved = 1) AS materials,
            (SELECT COUNT(*) FROM downloads) AS downloads,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) AS users'
)->fetch();
$colleges = $pdo->query(
    'SELECT c.college_id, c.name, COUNT(DISTINCT d.department_id) AS departments,
            COUNT(DISTINCT co.course_id) AS courses
     FROM colleges AS c LEFT JOIN departments AS d ON d.college_id = c.college_id
     LEFT JOIN courses AS co ON co.department_id = d.department_id
     GROUP BY c.college_id, c.name ORDER BY c.name'
)->fetchAll();
$recent = $pdo->query(
    'SELECT m.material_id, m.title, m.file_type, m.file_size, m.upload_date,
            c.course_code, c.course_title
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.approved = 1 ORDER BY m.upload_date DESC LIMIT 6'
)->fetchAll();
$popular = $pdo->query(
    'SELECT m.material_id, m.title, m.file_type, m.file_size, m.download_count,
            c.course_code, c.course_title
     FROM materials AS m JOIN courses AS c ON c.course_id = m.course_id
     WHERE m.approved = 1 ORDER BY m.download_count DESC, m.upload_date DESC LIMIT 6'
)->fetchAll();
$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<main class="container page-shell" id="main-content">
    <section class="library-intro mb-5">
        <p class="eyebrow">Haramaya University</p>
        <h1 class="page-title">Find learning materials<br>for your curriculum.</h1>
        <p class="intro-copy">Search lecture notes, presentations, readings, and recordings by course or department.</p>
        <form class="home-search" action="<?= e(site_url('search.php')) ?>" method="get" role="search">
            <label class="visually-hidden" for="home-query">Search learning materials</label>
            <input class="form-control form-control-lg" id="home-query" type="search" name="q" placeholder="Course code, title, or keyword">
            <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Search library</button>
        </form>
        <a class="browse-link" href="<?= e(site_url('browse.php')) ?>">Browse all colleges and departments <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </section>

    <section class="row g-3 mb-5" aria-label="Library statistics">
        <?php foreach ([['Approved materials', $stats['materials']], ['Downloads', $stats['downloads']], ['Active accounts', $stats['users']]] as [$label, $value]): ?>
            <div class="col-4"><div class="stat-strip"><strong><?= number_format((int) $value) ?></strong><span><?= e($label) ?></span></div></div>
        <?php endforeach; ?>
    </section>

    <section class="mb-5"><div class="section-topline"><h2 class="section-heading">Browse by college</h2><a href="<?= e(site_url('browse.php')) ?>">All colleges</a></div>
        <div class="row g-3"><?php foreach ($colleges as $college): ?><div class="col-12 col-md-6 col-xl-4"><a class="college-link" href="<?= e(site_url('browse.php?college_id=' . (int) $college['college_id'])) ?>"><span class="college-index" aria-hidden="true"><i class="bi bi-building"></i></span><span class="college-info"><strong><?= e($college['name']) ?></strong><small><?= (int) $college['departments'] ?> departments · <?= (int) $college['courses'] ?> courses</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div><?php endforeach; ?></div>
    </section>

    <section class="mb-5"><div class="section-topline"><h2 class="section-heading">Recently added</h2><a href="<?= e(site_url('search.php?sort=date')) ?>">View all</a></div>
        <div class="row g-3"><?php foreach ($recent as $materialCard): ?><div class="col-12 col-md-6 col-lg-4"><?php require __DIR__ . '/includes/material_card.php'; ?></div><?php endforeach; ?>
            <?php if ($recent === []): ?><p class="text-secondary">Approved learning materials will appear here.</p><?php endif; ?></div>
    </section>

    <section class="mb-5"><div class="section-topline"><h2 class="section-heading">Most downloaded</h2><a href="<?= e(site_url('search.php?sort=downloads')) ?>">View all</a></div>
        <div class="row g-3"><?php foreach ($popular as $materialCard): ?><div class="col-12 col-md-6 col-lg-4"><?php require __DIR__ . '/includes/material_card.php'; ?></div><?php endforeach; ?>
            <?php if ($popular === []): ?><p class="text-secondary">No downloads have been recorded yet.</p><?php endif; ?></div>
    </section>

    <section class="instructor-invite" id="about"><div><p class="eyebrow">Share your course expertise</p><h2 class="section-heading">Help students find the right material.</h2><p class="mb-0">Instructors can submit course resources for administrator review.</p></div><a class="btn btn-light" href="<?= e(site_url('register.php')) ?>">Register as instructor</a></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>