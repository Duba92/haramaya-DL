<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$errors = [];
$settings = $pdo->query('SELECT setting_key, setting_value FROM site_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $title = sanitize($_POST['site_title'] ?? '');
    $description = sanitize($_POST['site_description'] ?? '');
    $logo = basename(sanitize($_POST['site_logo'] ?? ''));
    $allowedTypes = array_values(array_unique(array_filter(array_map('trim', explode(',', strtolower(sanitize($_POST['allowed_file_types'] ?? '')))))));
    $maximumMb = filter_var($_POST['max_file_size_mb'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
    $supportEmail = sanitize($_POST['support_email'] ?? '');
    $maintenanceMode = isset($_POST['maintenance_mode']) ? '1' : '0';

    if ($title === '' || mb_strlen($title) > 120) {
        $errors[] = 'Site title is required and must not exceed 120 characters.';
    }
    if (mb_strlen($description) > 500) {
        $errors[] = 'Description must not exceed 500 characters.';
    }
    if ($logo !== '' && preg_match('/^[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp|svg)$/i', $logo) !== 1) {
        $errors[] = 'Logo must be an image filename placed in assets/images/.';
    }
    if ($allowedTypes === [] || array_diff($allowedTypes, array_keys(ALLOWED_TYPES)) !== []) {
        $errors[] = 'Choose one or more supported types: pdf, docx, pptx, mp4.';
    }
    if ($maximumMb === false) {
        $errors[] = 'Maximum upload size must be between 1 and 50 MB.';
    }
    if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid support email or leave it blank.';
    }

    if ($errors === []) {
        $values = [
            'site_title' => $title,
            'site_description' => $description,
            'site_logo' => $logo,
            'allowed_file_types' => implode(',', $allowedTypes),
            'max_file_size' => (string) ($maximumMb * 1024 * 1024),
            'support_email' => $supportEmail,
            'maintenance_mode' => $maintenanceMode,
        ];
        try {
            $pdo->beginTransaction();
            $save = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
            foreach ($values as $key => $value) {
                $save->execute(['setting_key' => $key, 'setting_value' => $value]);
            }
            $pdo->commit();
            write_activity($pdo, (int) $_SESSION['user_id'], 'settings_update');
            set_flash('Site settings saved.');
            redirect('admin/settings.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Settings update failed: ' . $exception->getMessage());
            $errors[] = 'Settings could not be saved.';
        }
    }
    $settings = array_merge($settings, [
        'site_title' => $title, 'site_description' => $description, 'site_logo' => $logo,
        'allowed_file_types' => implode(',', $allowedTypes),
        'max_file_size' => $maximumMb === false ? '' : (string) ($maximumMb * 1024 * 1024),
        'support_email' => $supportEmail, 'maintenance_mode' => $maintenanceMode,
    ]);
}
$pageTitle = 'Site settings';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <h1 class="page-title">Site settings</h1>
    <p class="text-secondary">Place logo image files in <code>assets/images/</code> and enter only the filename.</p>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" class="form-panel">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="site_title">Site title</label><input class="form-control" id="site_title" name="site_title" maxlength="120" required value="<?= e($settings['site_title'] ?? SITE_NAME) ?>"></div>
        <div class="mb-3"><label class="form-label" for="site_description">Description</label><textarea class="form-control" id="site_description" name="site_description" rows="3" maxlength="500"><?= e($settings['site_description'] ?? '') ?></textarea></div>
        <div class="mb-3"><label class="form-label" for="site_logo">Logo filename</label><input class="form-control" id="site_logo" name="site_logo" maxlength="120" value="<?= e($settings['site_logo'] ?? '') ?>" placeholder="university-logo.png"></div>
        <div class="mb-3"><label class="form-label" for="allowed_file_types">Allowed file types</label><input class="form-control" id="allowed_file_types" name="allowed_file_types" required value="<?= e($settings['allowed_file_types'] ?? 'pdf,docx,pptx,mp4') ?>"><div class="form-text">Comma-separated values from pdf, docx, pptx, mp4.</div></div>
        <div class="mb-3"><label class="form-label" for="max_file_size_mb">Maximum upload size (MB)</label><input class="form-control" id="max_file_size_mb" name="max_file_size_mb" type="number" min="1" max="50" required value="<?= (int) ((int) ($settings['max_file_size'] ?? MAX_FILE_SIZE) / 1048576) ?>"></div>
        <div class="mb-3"><label class="form-label" for="support_email">Support email</label><input class="form-control" id="support_email" name="support_email" type="email" value="<?= e($settings['support_email'] ?? '') ?>"></div>
        <div class="form-check mb-4"><input class="form-check-input" id="maintenance_mode" name="maintenance_mode" type="checkbox" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="maintenance_mode">Enable maintenance mode for non-administrators</label></div>
        <button class="btn btn-primary" type="submit">Save settings</button>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>