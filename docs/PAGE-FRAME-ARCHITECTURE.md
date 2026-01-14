# Page Frame Architecture

## Overview

The Page Frame system provides a canonical layout model for all pages in the Polys Museum theme. It standardizes structure while maintaining backward compatibility with existing templates.

## DOM Structure

```
<body>
  <div class="flex-wrapper">
    
    <!-- HEADER (global) -->
    <header class="megamenu">...</header>
    
    <!-- HERO (optional) -->
    <section class="pf-hero pf-hero--[variant]">
      <!-- Static image, parallax, slideshow, or video -->
    </section>
    
    <!-- MAIN CONTENT -->
    <main id="main-content" class="pf-main main [section-class]">
      
      <!-- MEDIA BLOCK (optional) -->
      <div class="pf-media">
        <div class="pf-media__inner">
          <iframe/video>
        </div>
      </div>
      
      <!-- CONTENT + SIDEBAR -->
      <div class="pf-content-wrap container-flex d-flex">
        <div class="pf-content col-md-7 left">
          <div class="pf-content__inner widget-container">
            <!-- Page content -->
          </div>
        </div>
        <div class="pf-sidebar col-md-5 right">
          <div class="pf-sidebar__inner sticky">
            <!-- Sidebar content -->
          </div>
        </div>
      </div>
      
    </main>
    
    <!-- FOOTER (global) -->
    <footer>...</footer>
    
  </div>
</body>
```

## Hero Variants

| Class | Description | JS Module |
|-------|-------------|-----------|
| `.pf-hero--static` | Static background image | None |
| `.pf-hero--parallax` | Parallax scroll effect | `hero-parallax.js` |
| `.pf-hero--slideshow` | Image slideshow | `hero-slideshow.js` (future) |
| `.pf-hero--video` | Background video | `video-player.js` |

### Hero Height Modifiers

| Class | Effect |
|-------|--------|
| `.pf-hero--25` | 25% viewport height |
| `.pf-hero--30` | 30% viewport height |
| `.pf-hero--40` | 40% viewport height |
| `.pf-hero--50` | 50% viewport height |
| `.pf-hero--full` | 100vh full screen |

### Hero Mobile Focal Point

| Class | Effect |
|-------|--------|
| `.pf-hero--focal-left` | Left-align on mobile |
| `.pf-hero--focal-right` | Right-align on mobile |

## Video Player

The unified video player supports:
- YouTube embeds
- Vimeo embeds  
- Self-hosted MP4
- Autoplay with accessibility safeguards
- Lazy loading

### Usage

```html
<!-- Embedded video -->
<div class="pf-video" data-video-src="https://youtube.com/embed/..." data-autoplay="false">
  <div class="pf-video__player"></div>
</div>

<!-- Self-hosted video -->
<div class="pf-video pf-video--native" data-video-src="/path/to/video.mp4">
  <video class="pf-video__player"></video>
</div>
```

### JavaScript API

```javascript
window.VideoPlayer.play('container-id');
window.VideoPlayer.pause('container-id');
window.VideoPlayer.changeSource('container-id', 'new-url');

// Legacy compatibility
window.playSessionVideo(url, title);
```

## CSS Classes Reference

### Page Frame Classes (New)

| Class | Purpose |
|-------|---------|
| `.pf-main` | Main content wrapper |
| `.pf-hero` | Hero section wrapper |
| `.pf-media` | Media block (video/slideshow) |
| `.pf-content-wrap` | Content + sidebar container |
| `.pf-content` | Main content column |
| `.pf-sidebar` | Sidebar column |
| `.pf-video` | Video player container |

### Legacy Classes (Still Supported)

| Legacy Class | Maps To |
|--------------|---------|
| `.main` | `.pf-main` |
| `.parallax` | `.pf-hero--parallax` |
| `.home-section` | `.pf-hero` |
| `.container-flex` | `.pf-content-wrap` |
| `.col-md-7.left` | `.pf-content` |
| `.col-md-5.right` | `.pf-sidebar` |
| `.widget-container` | `.pf-content__inner` |
| `.sticky` | `.pf-sidebar__inner` |
| `.video-wrap` | `.pf-video` |

## Template Audit

### Conforming Templates (Use Page Frame)

| Template | Status | Notes |
|----------|--------|-------|
| `front-page.php` | ✅ Conforming | Standard 7/5 layout |
| `page-the-polys.php` | ✅ Conforming | Standard 7/5 layout |
| `page-watch.php` | ✅ Conforming | Has video sidebar |
| `xfront-page.php` | ✅ Conforming | Backup template |
| `__front-page.php` | ✅ Conforming | Backup template |

### Non-Conforming Templates (Need Review)

| Template | Issue | Recommendation |
|----------|-------|----------------|
| `page-the-academy-of-immersive-arts-and-sciences.php` | A-Frame WebXR page, no standard layout | Keep as-is (specialized) |
| `page-webxr-summit-series.php` | Uses `.row` instead of `.container-flex` | Migrate to Page Frame |
| `page-events.php` | Extra `</div>` before `<main>`, uses `.row` | Fix HTML, migrate layout |
| `page-profiles.php` | Custom grid layout | Review for migration |
| `page-nominations.php` | Admin page with custom layout | Keep as-is (admin) |

### WebXR/A-Frame Templates (Exempt)

These templates use A-Frame for 3D/VR content and don't follow standard page layout:

- `page-the-academy-of-immersive-arts-and-sciences.php`
- `page-web-spatial.php`
- `page-cesium-dev.php`

### Admin Templates (Exempt)

These are backend/admin pages with specialized layouts:

- `page-admin.php`
- `page-admin-*.php` (all admin templates)
- `page-import.php`
- `page-ballot.php`

## Migration Guide

### Converting a Legacy Template

**Before:**
```php
<?php get_header(); ?>
<section class="home-section home-parallax" style="background:url(<?=$hero?>)">
</section>
<main role="main" class="main">
  <div class="row">
    <div class="container">
      <!-- content -->
    </div>
  </div>
</main>
<?php get_footer(); ?>
```

**After:**
```php
<?php get_header(); ?>
<section class="pf-hero pf-hero--parallax" style="background-image:url(<?=$hero?>)">
</section>
<main id="main-content" role="main" class="pf-main main">
  <div class="pf-content-wrap container-flex d-flex">
    <div class="pf-content col-md-7 left">
      <div class="pf-content__inner widget-container">
        <!-- content -->
      </div>
    </div>
    <div class="pf-sidebar col-md-5 right">
      <div class="pf-sidebar__inner sticky">
        <!-- sidebar -->
      </div>
    </div>
  </div>
</main>
<?php get_footer(); ?>
```

## JS Modules

| Module | Purpose | jQuery? |
|--------|---------|---------|
| `hero-parallax.js` | Parallax scroll effect | No |
| `video-player.js` | Unified video handling | No |
| `megamenu-controller.js` | Accessible navigation | No |

### Legacy JS (To Be Removed)

| Module | Status | Replacement |
|--------|--------|-------------|
| `megamenu.js` | Legacy | `megamenu-controller.js` |
| `app.js` | Legacy | Various modern modules |
| `runofshow.js` | Legacy | TBD |

## Accessibility

All Page Frame components support:
- `prefers-reduced-motion` media query
- Keyboard navigation
- Screen reader compatibility
- WCAG 2.1 AA color contrast

## File Locations

```
app/
├── js/
│   └── custom/
│       ├── hero-parallax.js      # NEW: Parallax module
│       ├── video-player.js       # NEW: Video module
│       ├── megamenu-controller.js # Accessible megamenu
│       ├── megamenu.js           # LEGACY: jQuery menu
│       └── app.js                # LEGACY: Init code
└── scss/
    └── partials/
        ├── _page-frame.scss      # NEW: Page Frame styles
        ├── hero.scss             # Hero styles
        ├── video.scss            # Video styles
        └── megamenu.scss         # Megamenu styles
```
