</div>
<footer class="site-footer">
    <div class="container footer-inner">
        <span>&copy; <?= date('Y') ?> Haramaya University</span>
        <nav aria-label="Footer navigation" class="footer-links">
            <a href="<?= e(site_url('about.php')) ?>">About</a>
            <a href="<?= e(site_url('contact.php')) ?>">Contact</a>
            <a href="<?= e(site_url('help.php')) ?>">Help</a>
        </nav>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="<?= e(site_url('assets/js/main.js')) ?>" defer></script>
</body>
</html>