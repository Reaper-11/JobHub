<?php require_once __DIR__ . '/includes/shared_footer.php'; ?>
</main>
<?php if (!empty($hasSidebarLayout)) : ?>
    <?php if (empty($hideSharedFooter)) : ?>
        <?php require_once __DIR__ . '/includes/shared_footer.php'; ?>
        <?php jobhub_render_site_footer(); ?>
    <?php endif; ?>
    </div>
</div>
<?php else: ?>
    <?php if (empty($hideSharedFooter)) : ?>
        <?php require_once __DIR__ . '/includes/shared_footer.php'; ?>
        <?php jobhub_render_site_footer(); ?>
    <?php endif; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function eyeIconMarkup(isVisible) {
        if (isVisible) {
            return '<svg class="password-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"></path><path d="M10.58 10.58a2 2 0 0 0 2.83 2.83"></path><path d="M9.88 5.09A10.94 10.94 0 0 1 12 4.91c5.05 0 9.27 3.11 10.5 7.09a11.8 11.8 0 0 1-3.04 4.95"></path><path d="M6.61 6.61A11.84 11.84 0 0 0 1.5 12c.77 2.49 2.5 4.6 4.79 5.92"></path><path d="M14.12 14.12A4 4 0 0 1 9.88 9.88"></path></svg>';
        }

        return '<svg class="password-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 12s3.82-7.09 10.5-7.09S22.5 12 22.5 12 18.68 19.09 12 19.09 1.5 12 1.5 12z"></path><circle cx="12" cy="12" r="3.2"></circle></svg>';
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.innerHTML = eyeIconMarkup(false);

        button.addEventListener('click', function () {
            var group = button.closest('.password-toggle-group');
            var input = group ? group.querySelector('input[type="password"], input[type="text"]') : null;
            if (!input) {
                return;
            }

            var shouldShow = input.type === 'password';
            input.type = shouldShow ? 'text' : 'password';
            button.innerHTML = eyeIconMarkup(shouldShow);
            button.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
        });
    });
});
</script>
</body>
</html>
