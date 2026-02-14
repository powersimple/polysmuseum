Generate a Windsurf-ready prompt for: $ARGUMENTS

Create a detailed, self-contained prompt that can be pasted into Windsurf (Cascade AI) to implement the requested change. The prompt should:

1. **Context block** - Describe the Polys Museum theme structure:
   - WordPress theme at the current working directory
   - Key directories: `functions/` (PHP modules), `app/scss/partials/` (styles), `app/js/custom/` and `app/js/modern/` (scripts)
   - Build: Vite-based, SCSS compiled separately, dev server at obi-wan-v:3000
   - Conventions: `polys_` function prefix, BEM CSS, one feature per functions file

2. **Specific files to read** - List the exact files Windsurf should examine before making changes

3. **Step-by-step instructions** - Clear, numbered implementation steps

4. **Code patterns to follow** - Show examples from existing code that demonstrate the conventions

5. **Testing instructions** - How to verify the change works

6. **Constraints:**
   - Do NOT modify files in `node_modules/`, `build/`, `cesium/`, `app/js/vendor/`
   - Do NOT run git commands
   - Follow existing naming conventions
   - Use `@use` not `@import` for SCSS
   - New JS should go in `app/js/modern/` unless modifying legacy code

Format the output as a single copyable prompt block wrapped in a code fence.
