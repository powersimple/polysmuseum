# Windsurf Prompt 001: Polys 6 — Modernize for A-Frame 1.7 + Ring Layout + Lighting Fix

## Context
This is a WebXR awards ceremony site using A-Frame. The main page is `page-the-polys-6th-annual-immersive-awards.php` which includes files from `webxr/polys6/`. The A-Frame version is set via WordPress post meta `aframe_version` and loaded in `webxr/libraries/aframe.php`. This site must work in VR (Quest) and AR passthrough mode.

**A-Frame 1.7 uses Three.js r173 which permanently uses physically-correct light units. There is NO legacy lights mode — `physicallyCorrectLights` and `useLegacyLights` have been completely removed from Three.js r165+.** All light intensities tuned for A-Frame 1.4 will appear drastically darker. Point/Spot lights use candela; to approximate old behavior, multiply old intensities by PI (≈3.14159). For dramatic stage lighting on metallic surfaces, go significantly higher.

## Tasks

### 1. Fix `page-the-polys-6th-annual-immersive-awards.php`

**A. Fix the `<a-scene>` tag (lines 30-39).** There are duplicate `renderer`, `physics`, and malformed attributes. Replace the entire `<a-scene>` opening tag with a single clean declaration:

```html
<a-scene grab-panels item-grab device-set nomination-link anti-drop device-orientation-permission-ui
    physics="iterations: 30"
    renderer="antialias: true;
             colorManagement: true;
             sortObjects: true;
             maxCanvasWidth: 5600;
             maxCanvasHeight: 3200;
             foveationLevel: 0;"
    gltf-model="dracoDecoderPath: assets/draco/;"
    inspector="https://cdn.jsdelivr.net/gh/aframevr/aframe-inspector@master/dist/aframe-inspector.min.js"
    loading-screen="backgroundColor: #12171a"
    background="color: #000000; transparent: true"
    webxr="requiredFeatures: local-floor; optionalFeatures: bounded-floor,hand-tracking,layers,mesh-detection,plane-detection;">
```

Key changes:
- Single `renderer` attribute (no duplicates)
- Single `physics` attribute
- Added `transparent: true` to background for AR passthrough support
- Added `webxr` component with Quest passthrough features (mesh-detection, plane-detection, hand-tracking)
- Removed duplicate `""` at end of old renderer line

**B. Fix logo model reference (line 151).** Change `gltf-model="#The5thPolysLogo"` to `gltf-model="#The6thPolysLogo"`.

**C. Fix duplicate `id="trophy-rotation"` (lines 130 and 202).** Rename the first one to `id="trophy-rotation-inner"`.

**D. Fix ring entity `class` attributes.** HTML elements can only have one `class` attribute — the second overwrites the first. On ring entities that have both `class="center-obj-zone"` and `class="collision"`, merge them: `class="center-obj-zone collision"`. Apply to rings 1, 3, 4, 5 and the golden-gizmo-ring entities.

**E. Add ring6.** After ring5 (line 197), add ring6. See the ring layout section below for positions.

**F. Arrange rings in a hexagonal two-tier layout.** Replace the positions of rings 1-6 with this hexagonal pattern. The rings sit inside the golden gizmo wrapper (radius ~25 units). Use alternating heights for a staggered crown effect:

```
ring1: position="21.65 3 12.5"    (60° high tier)
ring2: position="0 0 25"          (0° low tier)
ring3: position="-21.65 3 12.5"   (300° high tier)
ring4: position="-21.65 0 -12.5"  (240° low tier)
ring5: position="0 3 -25"         (180° high tier)
ring6: position="21.65 0 -12.5"   (120° low tier)
```

This creates a hexagon at radius 25, with alternating rings raised by 3 units, forming a crown pattern visible from the center.

### 2. Fix `webxr/polys6/credits-rigging.php`

**A. Replace deprecated `oculus-touch-controls` (lines 73, 77).** Change to `meta-touch-controls`:
- Line 73: `oculus-touch-controls="hand: left"` → `meta-touch-controls="hand: left"`
- Line 77: `oculus-touch-controls="hand: right"` → `meta-touch-controls="hand: right"`

**B. Fix duplicate `id="cam-light-front"` (lines 59, 61).** Rename the second one to `id="cam-light-left"`.

**C. Scale up camera-attached light intensities for physically-correct mode.** These are spot lights at intensity 3. Multiply by PI and boost for metallic pop:
- `cam-light-front`: intensity 3 → intensity 15
- `cam-light-left`: intensity 3 → intensity 15
- `cam-light-right`: intensity 3 → intensity 15

### 3. Fix `webxr/polys6/lights.php` — Physically-Correct Lighting Overhaul

This is the most critical file. All intensities must be retuned for A-Frame 1.7's physically-correct lighting. The goal is dramatic, colorful stage lighting that makes high-metalness trophy models pop.

Replace the active lighting section (lines 1-45) with:

```html
<a-entity id="lighting" visible="true" position="0 0 10" rotation="0 0 0">

    <!-- Key Light: Bright overhead spot -->
    <a-light id="spot-main-1" type="spot" color="#ffffff" intensity="800"
             position="0 120 0" rotation="-90 0 0"
             light="angle: 55; penumbra: 0.5; decay: 1; distance: 250; castShadow: false"></a-light>

    <!-- Fill Spot from front-above -->
    <a-light id="spot-main-2" type="spot" color="#ffffff" intensity="600"
             position="2 77 72" rotation="-20 0 -3"
             light="angle: 50; penumbra: 0.4; decay: 1; distance: 200; castShadow: false"></a-light>

    <!-- Directional Fill Lights -->
    <a-light id="directional-front" type="directional" color="#ffffff" intensity="4" position="300 150 300"></a-light>
    <a-light id="directional-back" type="directional" color="#ffffff" intensity="3" position="-300 150 -300"></a-light>

    <!-- Ambient Fill -->
    <a-light id="ambient" type="ambient" color="#ffffff" intensity="0.8"></a-light>

    <!-- Logo Highlight -->
    <a-light id="logo-highlight" type="spot" color="#ffffff" intensity="500"
             position="0 55 10" rotation="-60 0 0"
             light="angle: 80; penumbra: 0.8; decay: 1; distance: 150; castShadow: false"></a-light>

    <!-- Animated Colored Lights - Rotating Ring -->
    <a-entity animation="property: object3D.rotation.y; to: 360; easing: linear; dur: 12000; loop: true;">

        <!-- Corner Accent Point Lights -->
        <a-light id="red-corner" type="point" color="#990000" intensity="200" position="100 60 100"
                 light="decay: 1; distance: 200"
                 animation="property: light.color; from: #990000; to: #ff4500; dur: 4000; loop: true; dir: alternate"></a-light>
        <a-light id="blue-corner" type="point" color="#000099" intensity="200" position="-100 60 100"
                 light="decay: 1; distance: 200"
                 animation="property: light.color; from: #000099; to: #0080ff; dur: 4000; loop: true; dir: alternate"></a-light>
        <a-light id="purple-corner" type="point" color="#800080" intensity="200" position="100 60 -100"
                 light="decay: 1; distance: 200"
                 animation="property: light.color; from: #800080; to: #ff00ff; dur: 4000; loop: true; dir: alternate"></a-light>

        <!-- Side Spotlights -->
        <a-light id="side-light-1" type="spot" color="#800080" intensity="500" position="120 20 0" rotation="0 -90 0"
                 light="angle: 40; penumbra: 0.6; decay: 1; distance: 200"
                 animation="property: light.color; from: #800080; to: #ff00ff; dur: 4000; loop: true; dir: alternate"></a-light>
        <a-light id="side-light-2" type="spot" color="#000099" intensity="500" position="-120 20 0" rotation="0 90 0"
                 light="angle: 40; penumbra: 0.6; decay: 1; distance: 200"
                 animation="property: light.color; from: #000099; to: #0080ff; dur: 4000; loop: true; dir: alternate"></a-light>
        <a-light id="side-light-3" type="spot" color="#ff4500" intensity="500" position="0 20 120" rotation="0 180 0"
                 light="angle: 40; penumbra: 0.6; decay: 1; distance: 200"
                 animation="property: light.color; from: #ff4500; to: #ff9900; dur: 4000; loop: true; dir: alternate"></a-light>
        <a-light id="side-light-4" type="spot" color="#990000" intensity="500" position="0 20 -120" rotation="0 0 0"
                 light="angle: 40; penumbra: 0.6; decay: 1; distance: 200"
                 animation="property: light.color; from: #990000; to: #ff0000; dur: 4000; loop: true; dir: alternate"></a-light>

    </a-entity>

</a-entity>
```

Key changes from the old version:
- Spot/point intensities boosted from 5-30 → 200-800 (physically-correct candela units)
- Added `decay: 1` and `distance` to all point/spot lights (physically-correct falloff)
- Added `penumbra` to spots for softer edges
- Directional lights boosted from 1.8 → 3-4 (these use lux, smaller multiplier)
- Ambient boosted from 0.5 → 0.8
- All `castShadow: false` to reduce GPU overhead
- Removed all old commented-out code blocks (lines 48-103)

### 4. Fix `webxr/polys6/pedestals.php` — Per-Pedestal Lights

**A. Fix duplicate light IDs (lines 86-94).** The three per-pedestal trophy lights all use hardcoded IDs (`light-p5c-2`, `light-p5c-3` twice). Since this runs in a PHP loop for every pedestal, every pedestal creates duplicate IDs. Make them unique using the pedestal slug:

```php
<a-light id="light-<?=$pedestal['slug']?>-1" ...
<a-light id="light-<?=$pedestal['slug']?>-2" ...
<a-light id="light-<?=$pedestal['slug']?>-3" ...
```

**B. Scale up per-pedestal light intensities.** These gold spot lights illuminate the trophies:
- Light 1: intensity 20 → intensity 100
- Light 2: intensity 5 → intensity 50
- Light 3: intensity 31.12 → intensity 150

Also add `decay: 1; distance: 15` to each to limit their range and reduce GPU cost (they only need to light their own trophy).

### 5. Verify `webxr/polys6/assets.php`

No changes needed — the asset IDs are correct. Just confirm:
- `id="The6thPolysLogo"` (line 16) — this is the correct ID that the main page should reference
- `id="ring"` (line 18) — used by all ring entities
- `id="golden-gizmo-ring"` (line 10)
- `id="trophy"` (line 99)

### 6. Do NOT include `orbital-lights.php`

This file exists but is not included anywhere. Leave it excluded — it adds 9 more lights (red, green, blue, gold sweeping spots + 3 accent points + 2 directional fills) which would push the total light count too high for VR performance. The main `lights.php` already provides the colored rotating light rig.

## Files Modified (in order)
1. `page-the-polys-6th-annual-immersive-awards.php` — scene tag, logo ref, ring layout, duplicate IDs
2. `webxr/polys6/credits-rigging.php` — deprecated components, duplicate IDs, light intensities
3. `webxr/polys6/lights.php` — full lighting overhaul for physically-correct mode
4. `webxr/polys6/pedestals.php` — unique light IDs, intensity scaling

## Testing
After making changes, test with `?aframe-version=1.7.0` in the URL to confirm A-Frame 1.7 loads. Check:
1. Scene is visibly lit (not dark)
2. Metallic trophies show colored reflections from the rotating lights
3. The 6 rings form a visible hexagonal crown pattern inside the gold gizmo rings
4. AR passthrough works on Quest (enter AR mode, background should be transparent)
5. No console errors about deprecated components
