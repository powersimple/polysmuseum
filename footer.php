<footer class="footer" role="contentinfo">
    <?php echo render_footer_navigation(); ?>
</footer>
</div><!-- #flex-wrapper -->

<?php
/**
 * Template Debug Overlay
 * Only shown when:
 * 1. POLYSMUSEUM_SHOW_TEMPLATE_DEBUG constant is true (default: false)
 * 2. WP_DEBUG is enabled
 * 3. Current user is admin (manage_options capability)
 */
if ( 
    defined('POLYSMUSEUM_SHOW_TEMPLATE_DEBUG') && POLYSMUSEUM_SHOW_TEMPLATE_DEBUG 
    && defined('WP_DEBUG') && WP_DEBUG 
    && current_user_can('manage_options') 
) : ?>
<pre class="polys-template-debug" style="position:fixed;bottom:0;left:0;z-index:999999;background:#000;color:#0f0;padding:8px;font:12px/1.4 monospace;max-width:50vw;overflow:auto;" aria-hidden="true">
TEMPLATE: <?php echo esc_html( basename( get_page_template() ) ); ?>
</pre>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>