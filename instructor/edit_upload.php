<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('instructor');

$materialId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($materialId === false || $materialId === null) {
    http_response_code(404);
    exit('Material not found.');
}
$find = $pdo->prepare('SELECT material_id, title, description FROM materials WHERE material_id = :id AND uploader_id = :uploader_id');
$find->execute(['id' => $materialId, 'uploader_id' => $_SESSION['user_id']]);
$material = $find->fetch();
if (!$material) {
    http_response_code(404);
    exit('Material not found.');
}
$errors = [];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    if ($title === '' || mb_strlen($title) > 200 || mb_strlen($description) > 5000) {
        $errors[] = 'Enter a title up to 200 characters and a description up to 5,000 characters.';
    } else {
        $update = $pdo->prepare(
            'UPDATE materials SET title = :title, description = :description, approved = NULL
             WHERE material_id = :id AND uploader_id = :uploader_id'
        );
        $update->execute([
            'title' => $title,
            'description' => $description,
            'id' => $materialId,
            'uploader_id' => $_SESSION['user_id'],
        ]);
        write_activity($pdo, (int) $_SESSION['user_id'], 'material_edit', $title);
        set_flash('Material updated and returned to pending review.');
        redirect('instructor/my_uploads.php');
    }
    $material['title'] = $title;
    $material['description'] = $description;
}

$pageTitle = 'Edit material';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title">Edit material</h1>
    <p class="text-secondary">Saving changes sends the material back for review.</p>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="title">Title</label><input class="form-control" id="title" name="title" maxlength="200" required value="<?= e($material['title']) ?>"></div>
        <div class="mb-4"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="5" maxlength="5000"><?= e($material['description'] ?? '') ?></textarea></div>
        <button class="btn btn-primary" type="submit">Save changes</button>
        <a class="btn btn-outline-secondary" href="<?= e(site_url('instructor/my_uploads.php')) ?>">Cancel</a>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>