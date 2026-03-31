<?php
    get_header();
    
    require_once "functions/functions-awards.php";
      $pedestals = get_pedestals('polys6');
    //    var_dump($pedestals);
      $assets = [];
     

   if(@$_GET['dump'] == 'awards'){
     
?>

<div id="dump" style="position:absolute;top:0px; height:100vh;width:20%;background-color:rgba(10,10,10,0.4);color:#fff;z-index:100;overflow-y:scroll;">
    <?php 
    

    
    
    
    ?>
   </div>
<?php
}
?>

   

<a-scene  renderer="antialias: true;
                   colorManagement: true;
                   sortObjects: true;
                   maxCanvasWidth: 5600;
                   maxCanvasHeight: 2750;"" gltf-model="dracoDecoderPath: assets/draco/;" grab-panels item-grab device-set nomination-link anti-drop
    device-orientation-permission-ui physics="iterations: 30;"
    inspector="https://cdn.jsdelivr.net/gh/aframevr/aframe-inspector@master/dist/aframe-inspector.min.js"
    loading-screen="backgroundColor: #12171a" renderer="colorManagement: true; foveationLevel: 0;maxCanvasWidth:5600;
                   maxCanvasHeight: 3200;"
    background="color: #000000">

    <a-assets timeout="80000">
        <!-- Loads assets -->
        <?php
            include "webxr/polys6/assets.php";
            include "webxr/polys6/mixins.php";
            
        ?>

    </a-assets>
    <a-sky src="#sky" animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 1200000; loop: true;"></a-sky>

    <?php
            //include "webxr/polys6/credits-rigging.php";
            include "webxr/polys6/rigging.php";
          
    $showplatform="true";        
    if(@$_GET['showplatform']){
        $showplatform = "false";
     
    }

$z_start = 600;
if(@$_GET['z_start']){
  $z_start = $_GET['z_start'];
  $trophy_offset = $z_start+280;
 
}
$z_offset = 10;
if(@$_GET['z_offset']){
  $z_offset = $_GET['z_offset'];
  
}

?><!--
<a-entity id="trophy-wrap" class="clickable center-obj-zone" static-body="shape: box; mass: 2" position="0 -225 -400" >


    <a-entity id="trophy-model" class="center-obj-zone" static-body
                gltf-model="/assets/models/polys/4th/2023-Award200.glb" class="collision" visible="true"
                position="0 0 0" mixin="obj" rotation="0 90 0" scale="1 1 1" 
                animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 24000; loop: true;"></a-entity>
        
        </a-entity>
       -->
       

<?php
  include "webxr/polys6/trophy-spin.php";
  //  
?>
<?php
  include "webxr/polys6/lights.php";
?>

    <!-- Right Angle Light (-45 degrees) -->
<a-light id="light-p6c-3" color="#ffc800;" position="-20.35664 0 23.01157" rotation="0.67 45 0" light="intensity: 60">
    </a-light>
 
    <!--
     <a-light id="light-p6c-1" color="white" position="5.71453 1.27885 -12.33844" rotation="0.3901842584840906 46.77340960550475 0.4199780638308934" light="angle: 20; color: #ffc800; distance: 3.39; intensity: 60">
</a-light>

   
<a-light id="light-p6c-2" color="white" position="7.89394 4.43214 7.49816" rotation="0 45 0" light="angle: 20; color: #ffc800; distance: 2.5; intensity: 60">
    </a-light>

    <a-light id="light-p6c-4" color="#ffc800;" position="25 0 -6" rotation="0.67 45 0" light="intensity: 60">
    </a-light>
 <a-light id="light-p6c-5" color="#ffc800;" position="25.39257 -14.38285 -4.11565" rotation="0.67 45 0" light="intensity: 60; type: point">
    </a-light>
  <a-light id="light-p6c-6" color="#ffc800;" position="0.94408 19.43879 -10.5703" rotation="0.67 45 0" light="intensity: 60; type: point">
    </a-light>-->
        </a-entity>





</a-scene>
<script>
AFRAME.registerComponent('qz-keyboard-controls', {
  // ...

  getVelocityDelta: function () {
    var data = this.data,
        keys = this.getKeys();

    this.dVelocity.set(0, 0, 0);
    if (data.enabled) {
      if (keys.KeyW || keys.ArrowUp)    { this.dVelocity.z -= 1; }
      if (keys.KeyA || keys.ArrowLeft)  { this.dVelocity.x -= 1; }
      if (keys.KeyS || keys.ArrowDown)  { this.dVelocity.z += 1; }
      if (keys.KeyD || keys.ArrowRight) { this.dVelocity.x += 1; }

      // NEW STUFF HERE
      if (keys.KeyQ)  { this.dVelocity.y += 1; }
      if (keys.KeyZ) { this.dVelocity.y -= 1; }
    }

    return this.dVelocity.clone();
  },

  // ...
});
</script>
<?php
     get_footer();
?>`