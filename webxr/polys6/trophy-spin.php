<?php if( @$_GET['winner'] == 1 ){
  // WINNER MODE — handled first so it takes priority
  ?>
  <!-- WINNER MODE — clean gold presentation lighting, no orbit rig -->
  <!-- Gold key light at 45° front-right -->
  <a-light id="winner-gold-key" type="spot" color="#ffc800" intensity="80"
           position="20 15 -5"
           rotation="-30 -45 0"
           light="angle: 55; penumbra: 0.7; decay: 0.4; distance: 120"></a-light>
  <!-- Warm fill from opposite side -->
  <a-light id="winner-fill" type="point" color="#ffdd88" intensity="40"
           position="-18 8 -15"
           light="decay: 0.4; distance: 100"></a-light>
  <!-- Front fill to lift shadows -->
  <a-light id="winner-front" type="point" color="#fff0d0" intensity="30"
           position="0 5 0"
           light="decay: 0.4; distance: 80"></a-light>
  <!-- Gold side fill — lights the right side -->
  <a-light id="winner-side" type="point" color="#ffc800" intensity="45"
           position="20 3 -20"
           light="decay: 0.4; distance: 100"></a-light>
  <!-- Ambient base so nothing goes black -->
  <a-light id="winner-ambient" type="ambient" color="#332200" intensity="0.8"></a-light>

  <a-entity id="rotating trophy-wrapper" position="-6.09832 -1.24377 -17.32069" rotation="0 180 0" scale="1 1 1" visible="true">
       <a-entity id="golden-gizmo-wrapper" position="0 25 0" rotation="0 30 0" scale=".04 .04 .04" visible="true">
       <a-entity id="golden-gizmo-ring-x" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1" rotation="0 15 0" position="0 0 0"
                animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>
                <a-entity id="golden-gizmo-ring-y" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1" rotation="0 90 90" position="0 0 0"
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>
                <a-entity id="golden-gizmo-ring-z" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1" rotation="0 0 90" position="0 0 0"
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>
</a-entity><!-- golden gizmo ring -->
 <a-entity id="decadron" class="center-obj-zone"
                gltf-model="#decahedron" visible="true"
                scale="10 10 10" position="0 0 0" rotation="0 60 0"
               animation="property: object3D.rotation.y; to: 360; easing: linear; dur:64000; loop: true;"
           > </a-entity>
            <a-entity id="trophy-model" class="center-obj-zone"
                gltf-model="#trophy" visible="true"
                scale="10 10 10" position="0 0 0" rotation="0 60 0"
               animation="property: object3D.rotation.y; to: -360; easing: linear; dur:64000; loop: true;"
           > </a-entity>
           </a-entity>
<?php
} elseif( @$_GET['mode'] == 'base'){
  ?>

   <!-- ============================================================
        ORBIT LIGHT RIG — pivot at midpoint of #logo and
        #rotating trophy-wrapper: (-0.8, -6.9, -57.8)
        Uses point lights for reliable omnidirectional sweep on gold.
        ============================================================ -->

    <!-- Primary Orbit — clockwise 20s, 3 point lights on arms -->
    <a-entity id="orbit-primary" position="-0.8 -1.5 -57.8"
              animation="property: object3D.rotation.y; to: 360; easing: linear; dur: 20000; loop: true;">

        <!-- SWEEP A — warm amber, wide front arm -->
        <a-light id="sweep-amber" type="point" color="#ffaa33" intensity="35"
                 position="50 4 0"
                 light="decay: 0.3; distance: 250"
                 animation="property: light.color; from: #ffaa33; to: #ffcc66; dur: 8000; loop: true; dir: alternate"></a-light>

        <!-- SWEEP B — magenta, wide rear arm (180° opposite) -->
        <a-light id="sweep-magenta" type="point" color="#aa22cc" intensity="30"
                 position="-50 4 0"
                 light="decay: 0.3; distance: 250"
                 animation="property: light.color; from: #aa22cc; to: #cc55ff; dur: 9000; loop: true; dir: alternate"></a-light>

        <!-- SWEEP C — gold highlight, wide offset arm -->
        <a-light id="sweep-gold" type="point" color="#ffd700" intensity="25"
                 position="0 6 -48"
                 light="decay: 0.3; distance: 240"
                 animation="property: light.color; from: #ffd700; to: #ffeeaa; dur: 7000; loop: true; dir: alternate"></a-light>
    </a-entity>

    <!-- Counter Orbit — counter-clockwise 28s, 2 rim point lights -->
    <a-entity id="orbit-counter" position="-0.8 -1.5 -57.8"
              animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 28000; loop: true;">

        <!-- RIM A — deep blue, wider arm -->
        <a-light id="rim-blue" type="point" color="#1144bb" intensity="30"
                 position="45 5 30"
                 light="decay: 0.3; distance: 240"
                 animation="property: light.color; from: #1144bb; to: #3366ff; dur: 10000; loop: true; dir: alternate"></a-light>

        <!-- RIM B — crimson/rose, wider arm -->
        <a-light id="rim-rose" type="point" color="#bb1144" intensity="30"
                 position="-45 5 -30"
                 light="decay: 0.3; distance: 240"
                 animation="property: light.color; from: #bb1144; to: #ff3366; dur: 11000; loop: true; dir: alternate"></a-light>
    </a-entity>

    <!-- Ambient fill — subtle warm tone, dialed back -->
  
    <a-light id="ambient-gold" type="point" color="#ffc860" intensity="15"
             position="-0.8 25 -57.8"
             light="decay: 0.5; distance: 160"></a-light>
    <!-- Saturated color accents — static, broad, add richness -->
    <a-light id="accent-blue" type="point" color="#0044ff" intensity="45"
             position="25 4 -75"
             light="decay: 0.3; distance: 220"
             animation="property: light.color; from: #0044ff; to: #2266ff; dur: 9000; loop: true; dir: alternate"
             animation__intensity="property: light.intensity; from: 15; to: 45; dur: 6000; loop: true; dir: alternate; easing: easeInOutSine"
             animation__pos="property: position; from: 25 4 -75; to: -25 4 -42; dur: 14000; loop: true; dir: alternate; easing: easeInOutSine"></a-light>

    <a-light id="accent-red" type="point" color="#cc0022" intensity="45"
             position="-25 10 -42"
             light="decay: 0.5; distance: 160"
             animation="property: light.color; from: #cc0022; to: #ff2244; dur: 10000; loop: true; dir: alternate"></a-light>
  <?php   if(@$_GET['logo']==1){?>
  
             <a-entity id="logo" class="center-obj-zone" gltf-model="/assets/models/polys/6th/6thPolysLogoGoldTextOnly.glb" scale="10 10 10" position="19.82386 -1.74762 -58.61296" rotation="0 1.8426322691407275 0"> </a-entity>
<?php } ?>
  <a-entity id="rotating trophy-wrapper" position="-21.5 -12 -57" rotation="0 45 0" scale="1 1 1" visible="true">
       <a-entity id="golden-gizmo-wrapper" position="0 25 0" rotation="0 45 0" scale=".04 .04 .04" visible="true">
       <a-entity id="golden-gizmo-ring-x" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1"
                rotation="0 15 0"
                position="0 0 0"
                animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>

                <a-entity id="golden-gizmo-ring-y" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1"
                rotation="0 90 90"
                position="0 0 0"
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>


                <a-entity id="golden-gizmo-ring-z" class="center-obj-zone collision"
                gltf-model="#golden-gizmo-ring" gold-ring-tune visible="true"
                scale="1 1 1"
                rotation="0 0 90"
                position="0 0 0"
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"
                ></a-entity>


</a-entity><!-- golden gizmo ring -->

 <a-entity id="decadron" class="center-obj-zone" 
                gltf-model="#decahedron"  visible="true"
                scale="10 10 10"
                position="0 0 0"
                rotation="0 60 0" 
               animation="property: object3D.rotation.y; to: 360; easing: linear; dur:64000; loop: true;"
           > </a-entity>

            <a-entity id="trophy-model" class="center-obj-zone" 
                gltf-model="#trophy"  visible="true"
                scale="10 10 10 "
                position="0 0 0"
                rotation="0 60 0" 
               animation="property: object3D.rotation.y; to: -360; easing: linear; dur:64000; loop: true;"
           > </a-entity>
           </a-entity>
<?php
  }  else  {

    
?>


<a-entity id="trophy-rotation" class="center-obj-zone" 
                visible="true"
                scale="1 1 1"
                position="0 -14.855 0"
                rotation="0 0 0" 
               >
              

<?php

  } 
?>