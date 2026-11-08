<?php
if (!function_exists('rrms_footer_brand')) {
    function rrms_footer_brand($value)
    {
        // Configuration is text. Escape it before adding the fixed, trusted wordmark.
        $value = preg_replace('/\bSmart\s+Access\s+Management\b/i', 'reQuest', (string) $value);
        $text = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return preg_replace('/\b(?:SAM|eoa)\b/i', '<span class="eoa-wordmark"><span class="brand-accent">e</span>oa</span>', $text);
    }
}
?>
<footer id="footer" class="footer">
  <div class="copyright">
    &copy; <?php echo date('Y'); ?> <strong><?php echo rrms_footer_brand($rowconfig['systemname'] ?? 'reQuest'); ?></strong><span class="footer-copyright-note">. All Rights Reserved</span>
  </div>
  <div class="credits">
    <?php if (trim($rowconfig['systemcopyright'] ?? '') !== ''): ?>
    <span class="footer-powered">Powered by <?php echo rrms_footer_brand($rowconfig['systemcopyright']); ?> &middot;</span>
    <?php endif; ?>
    <span class="footer-managed">Managed by <a href="https://www.facebook.com/breeve.antonio/"><span class="eoa-wordmark"><span class="brand-accent">e</span>oa</span></a></span>
  </div>
</footer>
