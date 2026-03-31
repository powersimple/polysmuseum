<!-- ============================================================
     POLYS 6 SHARED LIGHTING RIG — Quest-optimized
     Replaces the previous 13-light rig + per-trophy spots.
     Total active lights: 7 (key + fill + ambient + 3 color accents + gold sweep parent w/ 1 spot)
     Toggle: ?legacylights=1 restores the original lights.php rig below.
     ============================================================ -->

<?php
$use_legacy_lights = isset($_GET['legacylights']) && $_GET['legacylights'] === '1';

if ($use_legacy_lights) {
?>
<!-- BEGIN LEGACY LIGHTS.PHP RESTORE POINT -->
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

    <!-- Trophy-specific lights (moved from page template) -->
    <a-light id="light-1" color="white" position="-2.63319 0.97826 2.92914" rotation="4.99 -41.95 -17.04" light="color: #ffc800; angle: 19.82; type: spot; intensity: 100; decay: 1; distance: 15"></a-light>
    <a-light id="light--2" color="white" position="1.88137 1.75 3.51503" rotation="0 45 0" light="color: #ffc800; angle: 20; type: spot; intensity: 50; decay: 1; distance: 15"></a-light>
    <a-light id="light--3" color="white" position="4.4343 0.29826 3.72449" rotation="0.67 45 0" light="color: #ffc800; angle: 20; type: spot; intensity: 150; decay: 1; distance: 15"></a-light>
    <a-light id="trophylight" type="spot" color="#ffcc44" intensity="400" position="0.19792 14.19538 64.15327" rotation="0 -90 0" light="angle: 45; distance: 35; intensity: 22.69; penumbra: 0.6; type: directional"></a-light>

</a-entity>
<!-- END LEGACY LIGHTS.PHP RESTORE POINT -->
<?php
} else {
?>

<!-- ==============================
     NEW OPTIMIZED SHARED LIGHTING
     7 lights total, Quest-friendly
     ============================== -->
<a-entity id="lighting" visible="true" position="0 0 10" rotation="0 0 0">

    <!-- A. KEY LIGHT — warm hero spot from high front
         Warm white/gold tint, wide cone, defines metallic shape.
         Positioned high above and slightly in front of the award ring area. -->
    <a-light id="key-hero" type="spot" color="#fff5e0" intensity="900"
             position="0 110 10" rotation="-80 0 0"
             light="angle: 60; penumbra: 0.5; decay: 1; distance: 260; castShadow: false"></a-light>

    <!-- B. FILL LIGHT — cooler neutral from opposite side
         Softens contrast, separates edges without flattening. -->
    <a-light id="fill-soft" type="spot" color="#e8eeff" intensity="350"
             position="-40 60 50" rotation="-30 30 0"
             light="angle: 55; penumbra: 0.7; decay: 1; distance: 200; castShadow: false"></a-light>

    <!-- C. AMBIENT — very low, prevents crushed blacks only -->
    <a-light id="ambient-base" type="ambient" color="#1a1520" intensity="0.5"></a-light>

    <!-- D. ANIMATED COLORED ACCENTS — 3 lights (down from 7)
         Slow orbit, brand palette: magenta, blue, warm orange.
         20s rotation keeps motion subtle and theatrical. -->
    <a-entity id="color-accent-orbit"
              animation="property: object3D.rotation.y; to: 360; easing: linear; dur: 20000; loop: true;">

        <!-- Deep magenta/purple accent -->
        <a-light id="accent-magenta" type="point" color="#8b0060" intensity="120" position="80 40 0"
                 light="decay: 1.2; distance: 160"
                 animation="property: light.color; from: #8b0060; to: #cc0088; dur: 6000; loop: true; dir: alternate"></a-light>

        <!-- Blue accent -->
        <a-light id="accent-blue" type="point" color="#002277" intensity="120" position="-40 35 70"
                 light="decay: 1.2; distance: 160"
                 animation="property: light.color; from: #002277; to: #0055cc; dur: 5000; loop: true; dir: alternate"></a-light>

        <!-- Warm orange/red accent -->
        <a-light id="accent-warm" type="point" color="#993300" intensity="100" position="-40 35 -70"
                 light="decay: 1.2; distance: 140"
                 animation="property: light.color; from: #993300; to: #cc5500; dur: 7000; loop: true; dir: alternate"></a-light>

    </a-entity>

    <!-- E. GOLD SWEEP RIG — cinematic glint across trophies
         1 warm gold spot on a slowly orbiting parent.
         35s full rotation = elegant, not disco.
         The spot sweeps across the pedestal ring so trophy faces
         catch moving highlights as the video plays. -->
    <a-entity id="gold-sweep-orbit" position="0 0 -16"
              animation="property: object3D.rotation.y; to: 360; easing: linear; dur: 35000; loop: true;">

        <a-light id="gold-sweep-1" type="spot" color="#ffcc66" intensity="400"
                 position="55 50 0" rotation="-55 -90 0"
                 light="angle: 35; penumbra: 0.6; decay: 1; distance: 120; castShadow: false"></a-light>

    </a-entity>

    <!-- Trophy-specific lights (moved from page template) -->
    <a-light id="light-1" color="white" position="-2.63319 0.97826 2.92914" rotation="4.99 -41.95 -17.04" light="color: #ffc800; angle: 19.82; type: spot; intensity: 100; decay: 1; distance: 15"></a-light>
    <a-light id="light--2" color="white" position="1.88137 1.75 3.51503" rotation="0 45 0" light="color: #ffc800; angle: 20; type: spot; intensity: 50; decay: 1; distance: 15"></a-light>
    <a-light id="light--3" color="white" position="4.4343 0.29826 3.72449" rotation="0.67 45 0" light="color: #ffc800; angle: 20; type: spot; intensity: 150; decay: 1; distance: 15"></a-light>
    <a-light id="trophylight" type="spot" color="#ffcc44" intensity="400" position="0.19792 14.19538 64.15327" rotation="0 -90 0" light="angle: 45; distance: 35; intensity: 22.69; penumbra: 0.6; type: directional"></a-light>

</a-entity>

<?php
}
?>
