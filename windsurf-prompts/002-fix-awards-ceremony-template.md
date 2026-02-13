# Windsurf Prompt 002: Fix Awards Ceremony Template — Menu Hierarchy Rendering

## Context

This is a WordPress theme site for The Polys WebXR Awards. The ceremony page uses a WordPress nav menu hierarchy to structure award categories, presenters, nominees, and credits. The page loads the menu via `functions/functions-navigation.php` → `get_menu_array()`, which returns a nested array with `children` at each level. The rendering pipeline is:

```php
// In the page template (e.g. page-the-polys-6th-annual-immersive-awards.php):
if(@$section_class == 'ceremony'){
  if(@$section_menu){
    require_once "functions/functions-awards.php";
    $awards = get_menu_array($section_menu);
    require_once('templates/awards.php');
  }
}
```

### Menu Hierarchy (5 Levels)

- **Level 1** = Ceremony (e.g. "The 6th Polys"). This is the top-level menu item. The `$awards` array iterates these.
- **Level 2** = Award Category (e.g. "Game of the Year", "Community Honor"). Has CSS class `nomination` or `honor`. These are `$award['children']`.
- **Level 3** = Presenter + Nominees. Children of each award category.
  - If CSS class is `presenter`, it renders first as "Presented by [Name]"
  - Otherwise it's a nominee (usually a `resource` post type for a project, or `profile` for a person)
  - **Thumbnails should ONLY be shown at this level** (Level 3, counter == 0)
- **Level 4** = Person or Company under each nominee. These are `$child['children']` of Level 3 items.
- **Level 5** = Team members at a company. Children of Level 4 items.
- **Social links** (twitter, linkedin, instagram, github, website) should display at ALL levels via `get_nominee_meta()`.

### Reference: Working Production Output

See https://thepolys.com/awards/the-4th-polys-webxr-awards/4th-polys/#p4c-community-honor for the correct visual output. The HTML pattern is:

```html
<!-- Level 2: Award Category -->
<h3 class="nomination-category" id="slug">Category Title</h3>
<ul class="nominee-list">
  <!-- Level 3: Presenter (first) -->
  <div class="col-12 col-sm-6">
    <h4 class="presenter">
      <img src="thumbnail.jpg" class="nomination-thumbnail">
      Presented by <span class="presented-by">Name <a href="twitter">@handle</a></span>
    </h4>
  </div>
  <hr>
  <!-- Level 3: Nominee -->
  <li class="nominee">
    <a href="resource_url" target="_blank" class="nominee-image">
      <img src="thumbnail.jpg" class="nomination-thumbnail">
    </a>
    <span class="winner"></span> <!-- if winner -->
    <a href="resource_url" target="_blank" class="nominee"><span>Title</span></a>
    <a href="twitter">@handle</a> <a href="linkedin">icon</a>
    <!-- Level 4: Credits (person/company) -->
    <ul>
      <li class="nominee-credit">
        <span>Person Name</span> <a href="twitter">@handle</a>
        <!-- Level 5: Team -->
        <ul>
          <li class="nominee-credit"><span>Team Member</span> <a href="twitter">@handle</a></li>
        </ul>
      </li>
    </ul>
  </li>
</ul>
```

## Files to Modify

### 1. `functions/functions-awards.php` — Rewrite `get_nominees_and_winners()`

The current function (starting around line 955) has critical bugs:

**Bug A: `extract($child)` overwrites the loop variable.** The function signature is `get_nominees_and_winners($children, $counter)` and the loop is `foreach($children as $c => $child)`. Calling `extract($child)` sets `$children` to `$child['children']`, destroying the foreach loop's iteration variable. **Remove `extract($child)` entirely** and access child data via `$child['key']` instead.

**Bug B: Broken HTML nesting in the default (nominee) branch.** Starting around line 1044, inside the `if($thumbnail_src != '' && $counter == 0)` block, the closing braces are mismatched — the `</span>` for the non-resource-url case is missing, a stray `,` is printed, and the recursive call `get_nominees_and_winners($children, $counter)` uses the (now corrupted) `$children` variable instead of `$child['children']` with an incremented counter.

**Bug C: Missing `</li>` tags.** The honoree and default nominee branches open `<li>` tags but don't close them in all code paths.

Replace the entire `get_nominees_and_winners` function (from `function get_nominees_and_winners($children,$counter){` through its closing `}`) with:

```php
/**
 * Recursively render nominees/winners from the menu hierarchy.
 * Level 3 (counter=0): Presenter first, then nominees (show thumbnail)
 * Level 4 (counter=1): Person or company (no thumbnail)
 * Level 5 (counter=2): Team members (no thumbnail)
 * Socials displayed at all levels.
 */
function get_nominees_and_winners($items, $counter){
  if(!is_array($items) || empty($items)) return;

  // Sort so presenters come first at each level
  $sorted = [];
  $rest = [];
  foreach($items as $key => $item){
    if(@$item['classes'][0] == 'presenter'){
      $sorted[$key] = $item;
    } else {
      $rest[$key] = $item;
    }
  }
  $sorted = $sorted + $rest;

  foreach($sorted as $c => $child){
    $meta = @$child['meta'];
    $classes = @$child['classes'];
    $title = @$child['title'];
    $thumbnail_src = getThumbnail(@$meta['_thumbnail_id'][0], "thumbnail");

    // Track participants for contact mode
    if(!array_key_exists($child['post']->ID, @$GLOBALS['participants'])){
      $GLOBALS['participants'][$child['post']->ID] = [
        "name" => $title,
        "email" => @$meta['email'][0],
      ];
    }

    if(@$classes[0] == 'presenter'){
      // Presenter block — not inside <li>, uses <h4>
      print "<div class='col-12 col-sm-6'>";
      print "<h4 class='presenter'>";
      if($thumbnail_src != '' && $counter == 0){
        print "<img src='$thumbnail_src' alt='".esc_attr($title)."' title='".esc_attr($title)."' class='nomination-thumbnail'>";
      }
      if($child['attr_title'] != ''){
        print esc_html($child['attr_title']). " ";
      } else {
        print "Presented by ";
      }
      print "<span class='presented-by'>";
      print esc_html($title);
      get_nominee_meta($meta);
      print "</span>";
      print "</h4>";
      print "</div>";
      print "<hr>";

    } else {
      // Nominee / honoree / winner / credit
      if($counter == 0){
        $item_class = (@$classes[0] == 'honoree') ? 'honoree' : 'nominee';
      } else {
        $item_class = 'nominee-credit';
      }

      print "<li class='$item_class'>";

      // Only show thumbnail at Level 3 (counter == 0)
      if($thumbnail_src != '' && $counter == 0){
        $resource_url = @$meta['resource_url'][0];
        if($resource_url != ''){
          print "<a href='".esc_url($resource_url)."' target='_blank' class='nominee-image'>";
        } else {
          print "<span class='nominee-image'>";
        }
        print "<img src='$thumbnail_src' class='nomination-thumbnail'>";
        if($resource_url != ''){
          print "</a>";
        } else {
          print "</span>";
        }

        // Badge spans for winner/honoree
        if(@$classes[0] == 'winner'){
          print "<span class='winner'></span>";
        } else if(@$classes[0] == 'honoree'){
          print "<span class='honoree'></span>";
        }
      }

      // Nominee info (title + resource link) and social links
      get_nominee_info($child, $counter);

      // Recurse into children (Level 4 → Level 5)
      if(!empty($child['children'])){
        print "<ul>";
        get_nominees_and_winners($child['children'], $counter + 1);
        print "</ul>";
      }

      print "</li>";
    }
  }
}
```

**Key changes:**
- No `extract()` — all data accessed via `$child['key']` to avoid variable collision
- Presenter sorting ensures presenters render before nominees
- Clean HTML: every `<li>` gets a matching `</li>`, every `<ul>` gets `</ul>`
- Thumbnails only at `$counter == 0` (Level 3)
- Proper recursion: `$child['children']` with `$counter + 1`
- Social links via `get_nominee_meta()` at all levels (called inside `get_nominee_info()`)
- `esc_attr()` / `esc_url()` for XSS safety on user-supplied content

### 2. `templates/awards.php` — Minor Safety Fixes

Add `@` error suppression on class checks to prevent PHP notices when classes array is empty:

**Line 7:** Change:
```php
if($child['classes'][0] == 'nomination' || $child['classes'][0] == 'honor'){
```
To:
```php
if(@$child['classes'][0] == 'nomination' || @$child['classes'][0] == 'honor'){
```

**Line 29:** Same change — the second occurrence of the same condition in the EVENTS loop.

No other changes needed in `templates/awards.php` — the Level 1/Level 2 loop structure is correct.

## Helper Functions Reference (do NOT modify these)

These existing functions are used by the rewritten code and should NOT be changed:

- `get_nominee_meta($meta)` in `functions/functions-awards.php` line 917 — prints social links (email for admins, twitter, linkedin, instagram, github, website) using `wrapMeta()`
- `get_nominee_info($nominee, $counter)` in `functions/functions-awards.php` line 934 — prints nominee title (linked if resource_url exists) then calls `get_nominee_meta()`
- `getThumbnail($id, $size)` in `functions/media.php` — returns thumbnail URL
- `wrapMeta()` in `functions/functions-profiles.php` — wraps meta values in appropriate HTML tags with Font Awesome icons
- `get_menu_array()` / `populate_children()` in `functions/functions-navigation.php` — builds the nested menu array from WordPress nav menus

## Testing

After applying changes:
1. Load the ceremony page and verify award categories appear with anchor navigation at top
2. Each award category should show: presenter first (with thumbnail + "Presented by"), then nominees in `<li>` tags
3. Nominees at Level 3 should have thumbnails; Level 4/5 credits should NOT have thumbnails
4. Social links should appear at all levels (twitter, linkedin, etc.)
5. Winners should have the `.winner` span badge
6. "Back to top" links should work between categories
7. Check PHP error log for no notices/warnings about undefined indexes
