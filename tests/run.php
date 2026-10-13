<?php
declare(strict_types=1);

define('BASE_URL', '/haramaya-DL');
define('UPLOAD_DIR', sys_get_temp_dir());
session_start();
require_once __DIR__ . '/../includes/functions.php';

$checks = 0;
$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$checks): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    $checks++;
};

$assertSame('Haramaya Library', sanitize(" \0Haramaya Library\0 "), 'sanitize trims input and removes null bytes');
$assertSame('', sanitize(['unexpected' => 'array']), 'sanitize safely rejects array input');
$assertSame('&lt;script&gt;x&lt;/script&gt;', e('<script>x</script>'), 'HTML output is escaped');
$assertSame('', e(['unexpected' => 'array']), 'output escaping safely rejects array input');
$assertSame('/haramaya-DL/admin/index.php', site_url('admin/index.php'), 'site URL respects a project subdirectory');
$assertSame('1.0 KB', format_file_size(1024), 'file sizes format in kilobytes');
$assertSame('1.0 MB', format_file_size(1048576), 'file sizes format in megabytes');
$assertSame('bi-file-earmark-pdf', get_file_icon('PDF'), 'file icon lookup is case-insensitive');

$_SESSION = [];
$token = csrf_token();
$assertSame(64, strlen($token), 'CSRF token has 256 bits of hex data');
$assertSame($token, csrf_token(), 'CSRF token is stable for the session');
$assertSame(true, str_contains(csrf_field(), 'name="csrf_token"'), 'CSRF field helper emits a hidden input');
$downloadForm = download_form(42, 'Download test');
$assertSame(true, str_contains($downloadForm, 'method="post"') && str_contains($downloadForm, 'name="material_id" value="42"'), 'download controls use a POST form');

set_flash('Saved', 'success');
$assertSame(['message' => 'Saved', 'type' => 'success'], get_flash(), 'flash message is returned');
$assertSame(null, get_flash(), 'flash message is consumed once');
$_SESSION['role'] = 'student';
$assertSame(true, check_role('student'), 'role check accepts the current role');
$assertSame(false, check_role('admin'), 'role check rejects a different role');

echo "Passed {$checks} helper checks.\n";