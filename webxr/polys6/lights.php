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
