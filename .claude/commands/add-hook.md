Add a WordPress hook for: $ARGUMENTS

Follow these Polys Museum theme conventions:

1. **Determine the correct file** in `functions/` based on the feature:
   - Events -> `functions-events.php`
   - Profiles -> `functions-profiles.php`
   - Awards -> `functions-awards.php`
   - Navigation/Menus -> `functions-navigation.php` or `functions-megamenu.php`
   - REST API -> `functions-rest-endpoints.php` or `functions-rest-register.php`
   - Enqueuing -> `functions-enqueue.php`
   - Post types -> `functions-post-types.php`
   - Meta boxes -> `functions-metabox.php`
   - General -> `functions.php`

2. **Naming convention:**
   - Callback function: `polys_{action_description}` in snake_case
   - Example: `polys_add_custom_body_class`, `polys_register_event_meta`

3. **Hook format:**
   ```php
   // Action
   add_action('hook_name', 'polys_callback_name', 10, 1);
   function polys_callback_name($arg) {
       // implementation
   }

   // Filter
   add_filter('hook_name', 'polys_callback_name', 10, 1);
   function polys_callback_name($value) {
       // modify and return
       return $value;
   }
   ```

4. **Place the hook registration** near related hooks in the target file.

5. **Document** with a brief inline comment explaining purpose.

Show the complete code and exactly where to add it in the file.
