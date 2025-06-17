<a-entity id="orbital-lights" visible="true">
    <!-- Main orbital animation container -->
    <a-entity animation="property: object3D.rotation.y; to: 360; easing: linear; dur: 15000; loop: true;">
        
        <!-- Red sweeping light -->
        <a-light id="sweep-red" 
                 type="spot" 
                 color="#ff0000" 
                 intensity="35" 
                 position="0 80 150" 
                 rotation="-30 0 0"
                 light="angle: 45; penumbra: 0.9; castShadow: false"
                 animation="property: light.intensity; from: 35; to: 45; dur: 2000; loop: true; dir: alternate"></a-light>

        <!-- Green sweeping light -->
        <a-light id="sweep-green" 
                 type="spot" 
                 color="#00ff00" 
                 intensity="35" 
                 position="150 80 0" 
                 rotation="-30 90 0"
                 light="angle: 45; penumbra: 0.9; castShadow: false"
                 animation="property: light.intensity; from: 35; to: 45; dur: 2000; loop: true; dir: alternate"></a-light>

        <!-- Blue sweeping light -->
        <a-light id="sweep-blue" 
                 type="spot" 
                 color="#0000ff" 
                 intensity="35" 
                 position="0 80 -150" 
                 rotation="-30 180 0"
                 light="angle: 45; penumbra: 0.9; castShadow: false"
                 animation="property: light.intensity; from: 35; to: 45; dur: 2000; loop: true; dir: alternate"></a-light>

        <!-- Gold accent light -->
        <a-light id="sweep-gold" 
                 type="spot" 
                 color="#ffd700" 
                 intensity="40" 
                 position="-150 80 0" 
                 rotation="-30 -90 0"
                 light="angle: 45; penumbra: 0.9; castShadow: false"
                 animation="property: light.intensity; from: 40; to: 50; dur: 2000; loop: true; dir: alternate"></a-light>
    </a-entity>

    <!-- Secondary orbital animation (opposite direction) -->
    <a-entity animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 20000; loop: true;">
        <!-- Red accent -->
        <a-light id="accent-red" 
                 type="point" 
                 color="#ff0000" 
                 intensity="20" 
                 position="0 40 100"
                 animation="property: light.intensity; from: 20; to: 25; dur: 1500; loop: true; dir: alternate"></a-light>

        <!-- Green accent -->
        <a-light id="accent-green" 
                 type="point" 
                 color="#00ff00" 
                 intensity="20" 
                 position="100 40 0"
                 animation="property: light.intensity; from: 20; to: 25; dur: 1500; loop: true; dir: alternate"></a-light>

        <!-- Blue accent -->
        <a-light id="accent-blue" 
                 type="point" 
                 color="#0000ff" 
                 intensity="20" 
                 position="0 40 -100"
                 animation="property: light.intensity; from: 20; to: 25; dur: 1500; loop: true; dir: alternate"></a-light>
    </a-entity>

    <!-- Static fill lights -->
    <a-light id="fill-top" type="directional" color="white" intensity="0.2" position="0 100 0"></a-light>
    <a-light id="fill-bottom" type="directional" color="white" intensity="0.1" position="0 -100 0"></a-light>
</a-entity> 