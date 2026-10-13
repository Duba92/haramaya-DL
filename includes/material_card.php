<article class="material-card h-100">
    <div class="d-flex gap-3">
        <i class="bi <?= e(get_file_icon($material['file_type'])) ?> card-file-icon" aria-hidden="true"></i>
        <div class="flex-grow-1 min-w-0">
            <a class="material-title" href="<?= e(site_url('material.php?id=' . (int) $materialCard['material_id'])) ?>"><?= e($materialCard['title']) ?></a>
            <p class="small text-secondary mb-2"><?= e($materialCard['course_code'] . ' · ' . $materialCard['course_title']) ?></p>
            <div class="d-flex align-items-center justify-content-between gap-2 small text-secondary">
                <span><?= e(strtoupper($materialCard['file_type'])) ?> · <?= e(format_file_size($materialCard['file_size'])) ?></span>
                <?php if (isset($materialCard['download_count'])): ?><span><i class="bi bi-download" aria-hidden="true"></i> <?= (int) $materialCard['download_count'] ?></span><?php endif; ?>
            </div>
        </div>
        <button class="bookmark-button" type="button" data-bookmark="<?= (int) $materialCard['material_id'] ?>" aria-label="Save <?= e($materialCard['title']) ?>" aria-pressed="false" title="Save material"><i class="bi bi-bookmark" aria-hidden="true"></i></button>
    </div>
</article>