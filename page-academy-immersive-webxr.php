<?php
    get_header();
    require_once "functions/functions-awards.php";
      $pedestals = get_pedestals('polys5');
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
 //  include "webxr/academy/drawer-experiences.php";
 //   include "webxr/polys2/drawer-nominations.php";
?>

   

<a-scene grab-panels item-grab device-set nomination-link anti-drop device-orientation-permission-ui physics="iterations: 30" renderer="antialias: true;
                   colorManagement: true;
                   sortObjects: true;
                   maxCanvasWidth: 8000;
                   maxCanvasHeight: 1800;"" gltf-model="dracoDecoderPath: assets/draco/;" 
    device-orientation-permission-ui physics="iterations: 30;"
    inspector="https://cdn.jsdelivr.net/gh/aframevr/aframe-inspector@master/dist/aframe-inspector.min.js"
    loading-screen="backgroundColor: #12171a" renderer="colorManagement: true; foveationLevel: 0;maxCanvasWidth:5600;
                   maxCanvasHeight: 3200;"
    background="color: #000000">

    <a-assets timeout="80000"> <a-entity tracked-controls="controller: 0; idPrefix: OpenVR"></a-entity>
    <a-entity tracked-controls="controller: 1; idPrefix: OpenVR"></a-entity>
        <!-- Loads assets -->
        <?php
            include "webxr/academy/assets.php";
            include "webxr/academy/mixins.php";
            
        ?>

    </a-assets>
    <a-sky src="#sky" animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 1200000; loop: true;"></a-sky>

    <?php
            include "webxr/academy/rigging.php";
           
    $showplatform="true";        
    if(@$_GET['showplatform']){
        $showplatform = "false";
     
    }


?><!--
<a-entity id="golden-gizmo-wrapper" position="-2 -7 -3" rotation="0 45 0" scale="6 6 6" visible="true">
       <a-entity id="golden-gizmo-ring-x" class="center-obj-zone" static-body
                gltf-model="#golden-gizmo-ring" class="collision" visible="true"
                scale="25 25 25"
                rotation="0 15 0"
                position="0 0 0" static-body="shape: box;" 
                animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 24000; loop: true;"

                ></a-entity>

               
                <a-entity id="golden-gizmo-ring-y" class="center-obj-zone" static-body
                gltf-model="#golden-gizmo-ring" class="collision" visible="true"
                scale="25 25 25"
                rotation="0 90 90"
                position="0 0 0" static-body="shape: box;" 
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"

                ></a-entity>
             
            
                <a-entity id="golden-gizmo-ring-z" class="center-obj-zone" static-body
                gltf-model="#golden-gizmo-ring" class="collision" visible="true"
                scale="25 25 25"
                rotation="0 0 90"
                position="0 0 0" static-body="shape: box;" 
                animation="property: object3D.rotation.x; to: 360; easing: linear; dur: 24000; loop: true;"

                ></a-entity>
</a-entity> golden gizmo ring -->
  

<?php
             include "webxr/academy/lights.php"; 
          
            ?>


 

<a-entity id="trophy-rotation" class="center-obj-zone" 
                visible="true"
                scale="1 1 1"
                position="-0.77 -3.88154 -6.23"
                rotation="0 0 0" 
                animation="property: object3D.rotation.y; to: -360; easing: linear; dur: 24000; loop: true;">
            </a-entity> 
        <a-entity id="academy-logo-model" class="center-obj-zone" 
                gltf-model="#AcademyLogo"  visible="true"
                scale="20 20 20"
                position="0 0 0"
                rotation="0 0 0" 
           >
          <a-entity id="presentedby" troika-text='value:A Presentation of;  align:center; color:#fff; fontSize:10;align:center;maxWidth:10;font:/wp-content/themes/polysmuseum/fonts/Raleway-Regular.ttf'
               material="shader: standard; metalness: 0.8;" position="0 0 0" rotation="0 0 0">
           </a-entity>
       
       <a-light id="light-p5c-3" color="white" light="color: #fffff; angle: 59.94; intensity: 1.74; distance: 10.57" visible="">
</a-light>
    <!-- Left Angle Light (45 degrees) -->
<a-light id="light-p5c-2" color="white" light="color: #ffffff; angle: 45; type: spot; intensity: 0.76; distance: 17.71" visible="" rotation="0 -25.4 0">
    </a-light>

    <!-- Right Angle Light (-45 degrees) -->
   <a-light id="light-p5c-3" color="white" light="color: #ffffff; angle: 23.95; intensity: 11.41; distance: 10.29" visible="" rotation="0 18.05 0">
    </a-light>
   


    </a-entity>
       
             
        
















</a-scene>
<main role="main" class="main <?=$section_class ?? ''?>">


  </main>
<?php
     get_footer();
?>