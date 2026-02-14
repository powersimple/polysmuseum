Review the WordPress template file: $ARGUMENTS

Perform a thorough review checking:

1. **WordPress Standards Compliance**
   - Proper use of `get_header()`, `get_footer()`, `get_template_part()`
   - Output escaping: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`
   - Proper use of The Loop and WP_Query
   - Translation-ready strings with `__()` or `_e()` using 'polysmuseum' text domain

2. **Theme Conventions** (per CLAUDE.md)
   - Function naming: `polys_` prefix, snake_case
   - Consistent hero/section structure matching `page.php` pattern
   - Proper use of post meta keys (use_aframe, hero, section_class, etc.)
   - Brand detection via `polys_get_current_brand()` where relevant

3. **Security**
   - No unescaped user input in output
   - Proper nonce verification for forms
   - No direct database queries without `$wpdb->prepare()`
   - No `eval()` or unsafe includes

4. **Performance**
   - No queries inside loops
   - Proper use of transients for expensive operations
   - Assets conditionally loaded (only when needed)

5. **Accessibility**
   - Semantic HTML elements
   - ARIA attributes where appropriate
   - Alt text on images
   - Keyboard navigation support

Provide specific line-by-line feedback with suggested fixes.
