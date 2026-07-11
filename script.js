<?php
/**
 * =============================================================================
 *  footer.php  --  Bottom of every HTML page
 * =============================================================================
 *  Closes the document, prints the footer bar, and loads the small JS file.
 * =============================================================================
 */
?>
<footer class="site-footer">
    <div class="footer-inner">
        <p>
            <strong><?php echo APP_NAME; ?></strong> &mdash; University Systems Security Project
        </p>
        <p class="footer-note">
            Demonstrating: Secure Storage in Edge Cloud (AES-256) &bull;
            Fake Data Prevention (SHA-256 Integrity) &bull;
            Federated Multi-Site Storage
        </p>
        <p class="footer-copy">&copy; <?php echo date('Y'); ?> Secure Medical Portal. For educational use.</p>
    </div>
</footer>

<!-- Small vanilla JavaScript file (no frameworks) for minor interactivity. -->
<script src="<?php echo BASE_URL; ?>js/script.js"></script>
</body>
</html>
