<?php
/**
 * =============================================================================
 *  error.php  --  Friendly error page
 * =============================================================================
 *  Shown when access is denied (wrong role) or a page is not found. Keeping a
 *  single, clean error page avoids leaking internal details to the user.
 * =============================================================================
 */
require_once __DIR__ . '/config/config.php';
$page_title = 'Error';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <div class="card" style="text-align:center;max-width:520px;margin:2rem auto;">
        <div style="font-size:3rem;color:var(--red);">&#9888;</div>
        <h1>Something went wrong</h1>
        <p class="text-muted mb">
            You may not have permission to view that page, or it does not exist.
        </p>
        <a class="btn" href="<?php echo BASE_URL; ?>index.php">Back to Home</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
