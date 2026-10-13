<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('instructor');

$departmentId = (int) ($_SESSION['department_id'] ?? 0);
$instructorId = (int) $_SESSION['user_id'];
$courseQuery = $pdo->prepare(
    'SELECT course_id, course_code, course_title FROM courses
     WHERE department_id = :department_id
       AND (instructor_id IS NULL OR instructor_id = :instructor_id)
     ORDER BY course_code'
);
$courseQuery->execute(['department_id' => $departmentId, 'instructor_id' => $instructorId]);
$courses = $courseQuery->fetchAll();
$errors = [];
$title = '';
$description = '';
$selectedCourse = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $selectedCourse = sanitize($_POST['course_id'] ?? '');
    $courseId = filter_var($selectedCourse, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'Enter a title no longer than 200 characters.';
    }
    if (mb_strlen($description) > 5000) {
        $errors[] = 'Description must be no longer than 5,000 characters.';
    }
    if ($courseId === false || !array_filter($courses, static fn (array $course): bool => (int) $course['course_id'] === $courseId)) {
        $errors[] = 'Choose a course from your department.';
    }

    $file = $_FILES['material'] ?? null;
    $fileError = is_array($file) && is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE;
    $fileFieldsValid = is_array($file)
        && $fileError === UPLOAD_ERR_OK
        && is_string($file['name'] ?? null)
        && is_string($file['tmp_name'] ?? null)
        && is_numeric($file['size'] ?? null);
    if (!$fileFieldsValid) {
        $errors[] = match ($fileError) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected file exceeds the upload size limit.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            default => 'The upload did not complete. Try again.',
        };
    }

    $extension = '';
    if ($fileFieldsValid) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $configuredTypes = array_filter(array_map('trim', explode(',', get_setting($pdo, 'allowed_file_types', 'pdf,docx,pptx,mp4'))));
        $maximumSize = min(MAX_FILE_SIZE, max(1, (int) get_setting($pdo, 'max_file_size', (string) MAX_FILE_SIZE)));
        if (!in_array($extension, $configuredTypes, true) || !isset(ALLOWED_TYPES[$extension])) {
            $errors[] = 'Allowed file types are: ' . implode(', ', array_intersect($configuredTypes, array_keys(ALLOWED_TYPES))) . '.';
        }
        if ((int) $file['size'] < 1 || (int) $file['size'] > $maximumSize) {
            $errors[] = 'The file must be smaller than ' . format_file_size($maximumSize) . '.';
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The uploaded file could not be verified.';
        } elseif (isset(ALLOWED_TYPES[$extension])) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            $mimeValid = in_array($mimeType, ALLOWED_TYPES[$extension], true);
            if (in_array($extension, ['docx', 'pptx'], true)) {
                $mimeValid = false;
                if (class_exists(ZipArchive::class)) {
                    $archive = new ZipArchive();
                    if ($archive->open($file['tmp_name']) === true) {
                        $requiredEntry = $extension === 'docx' ? 'word/document.xml' : 'ppt/presentation.xml';
                        $isOfficeArchive = $archive->locateName('[Content_Types].xml') !== false
                            && $archive->locateName($requiredEntry) !== false;
                        $archive->close();
                        $mimeValid = $isOfficeArchive
                            && ($mimeType === 'application/zip'
                                || $mimeType === 'application/octet-stream'
                                || in_array($mimeType, ALLOWED_TYPES[$extension], true));
                    }
                }
            }
            if (!$mimeValid) {
                $errors[] = 'The file contents do not match the selected file type.';
            }
        }
    }

    if ($errors === []) {
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = 'uploads/materials/' . $storedName;
        $destination = UPLOAD_DIR . DIRECTORY_SEPARATOR . $storedName;
        try {
            if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
                throw new RuntimeException('Unable to move uploaded material into storage.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO materials (title, description, file_path, file_type, file_size, course_id, uploader_id)
                 VALUES (:title, :description, :file_path, :file_type, :file_size, :course_id, :uploader_id)'
            );
            $insert->execute([
                'title' => $title,
                'description' => $description,
                'file_path' => $relativePath,
                'file_type' => $extension,
                'file_size' => (int) $file['size'],
                'course_id' => $courseId,
                'uploader_id' => $instructorId,
            ]);
            write_activity($pdo, $instructorId, 'material_upload', $title);
            set_flash('Material uploaded and sent for administrator review.');
            redirect('instructor/my_uploads.php');
        } catch (Throwable $exception) {
            if (is_file($destination)) {
                unlink($destination);
            }
            error_log('Material upload failed: ' . $exception->getMessage());
            $errors[] = 'The material could not be saved. Please try again.';
        }
    }
}

$pageTitle = 'Upload material';
require __DIR__ . '/../includes/header.php';
?>
<main class="container page-shell narrow-shell">
    <div class="page-toolbar"><div><h1 class="page-title">Upload course material</h1><p class="text-secondary mb-0">New uploads remain hidden until approved.</p></div>
        <a class="btn btn-outline-secondary" href="<?= e(site_url('instructor/my_uploads.php')) ?>">My uploads</a></div>
    <?php if ($errors !== []): ?><div class="alert alert-danger" role="alert"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" action="<?= e(site_url('instructor/upload.php')) ?>" class="form-panel" data-upload-form>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="title">Material title</label><input class="form-control" id="title" name="title" maxlength="200" required value="<?= e($title) ?>"></div>
        <div class="mb-3"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="4" maxlength="5000"><?= e($description) ?></textarea></div>
        <div class="mb-3"><label class="form-label" for="course_id">Course</label><select class="form-select" id="course_id" name="course_id" required><option value="">Choose a course</option>
            <?php foreach ($courses as $course): ?><option value="<?= (int) $course['course_id'] ?>" <?= (string) $course['course_id'] === $selectedCourse ? 'selected' : '' ?>><?= e($course['course_code'] . ' - ' . $course['course_title']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="mb-3"><label class="form-label" for="material">File</label><input class="form-control" id="material" name="material" type="file" accept=".pdf,.docx,.pptx,.mp4" required><div class="form-text">PDF, DOCX, PPTX, or MP4. Maximum 50 MB.</div></div>
        <div class="progress upload-progress mb-3" aria-label="Upload progress"><div class="progress-bar" role="progressbar" style="width: 0" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>
        <button class="btn btn-primary" type="submit" <?= $courses === [] ? 'disabled' : '' ?>>Upload for review</button>
        <?php if ($courses === []): ?><p class="text-danger mt-3 mb-0">No courses are assigned to your department yet. Contact an administrator.</p><?php endif; ?>
    </form>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>