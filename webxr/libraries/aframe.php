<?php
//hacks
    $speed = "0.2";
    if(@$_GET['speed']){
        $speed = $_GET['speed'];    
    }
    if($post->ID == 13){ 
      $_GET['event_menu'] = 'bizsummit21';
  
    }
    $menu = 'bizsummit21';
    $model='';

    if(@$_GET['event_menu']){
        $menu = $_GET['event_menu'];
    }
 
    $summit_square_model = 'business-summmit-square';
    if(@$_GET['summit_model']){
        $summit_square_model = $_GET['summit_model'];
    }

  $default_version = '1.7.0';
$aframe_version = $default_version;

$meta_version = get_post_meta($post->ID, 'aframe_version', true);
if ($meta_version !== '' && $meta_version !== null) {
    $aframe_version = $meta_version;
}

if (!empty($_GET['aframe-version'])) {
    $aframe_version = $_GET['aframe-version'];
}

?>




</head>


<script src="https://aframe.io/releases/<?=$aframe_version?>/aframe.min.js"></script>
<script>
// Polyfill: Three.js r125+ removed *BufferGeometry aliases
(function() {
    if (typeof THREE === 'undefined') return;
    var map = {
        PlaneBufferGeometry: 'PlaneGeometry',
        BoxBufferGeometry: 'BoxGeometry',
        SphereBufferGeometry: 'SphereGeometry',
        CylinderBufferGeometry: 'CylinderGeometry',
        ConeBufferGeometry: 'ConeGeometry',
        CircleBufferGeometry: 'CircleGeometry',
        RingBufferGeometry: 'RingGeometry',
        TorusBufferGeometry: 'TorusGeometry',
        TorusKnotBufferGeometry: 'TorusKnotGeometry',
        ExtrudeBufferGeometry: 'ExtrudeGeometry',
        ShapeBufferGeometry: 'ShapeGeometry',
        LatheBufferGeometry: 'LatheGeometry',
        TubeBufferGeometry: 'TubeGeometry',
        IcosahedronBufferGeometry: 'IcosahedronGeometry',
        OctahedronBufferGeometry: 'OctahedronGeometry',
        TetrahedronBufferGeometry: 'TetrahedronGeometry',
        DodecahedronBufferGeometry: 'DodecahedronGeometry'
    };
    for (var old in map) {
        if (!THREE[old] && THREE[map[old]]) {
            THREE[old] = THREE[map[old]];
        }
    }
})();
</script>
<script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-extras@7.5.0/dist/aframe-extras.controls.min.js"></script>
<script>
// Remove aframe-extras grabbable so super-hands can register its own version
if (AFRAME.components['grabbable']) { delete AFRAME.components['grabbable']; }
if (AFRAME.components['hoverable']) { delete AFRAME.components['hoverable']; }
if (AFRAME.components['clickable']) { delete AFRAME.components['clickable']; }
</script>
<script src="https://cdn.jsdelivr.net/npm/aframe-event-set-component@5.0.0/dist/aframe-event-set-component.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/c-frame/aframe-physics-system@v4.2.2/dist/aframe-physics-system.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aframe-physics-extras@0.1.2/dist/aframe-physics-extras.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/super-hands@3.0.3/dist/super-hands.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aframe-hand-tracking-controls-extras@0.4.0/dist/aframe-hand-tracking-controls-extras.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aframe-aabb-collider-component@3.1.0/dist/aframe-aabb-collider-component.min.js"></script>
<script src="<?php echo get_stylesheet_directory_uri();?>/webxr/libraries/simple-navmesh-constraint.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aframe-blink-controls@0.4.0/dist/aframe-blink-controls.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/aframe-troika-text@0.12.0/dist/aframe-troika-text.min.js"></script>

<style>
  .a-enter-ar-button{
           display: none !important;/* */
            
        }       
            <?php
              /// 
              //DISAPPEARS THE HEADER AND FOOTER FOR PURE AFRAME
              //USED IN VIRTUALPRODUCTION
              ///

            if(@$_GET['disappear']==1){
            ?>
              
                    .a-enter-ar-button, .a-enter-vr-button, .toggle-edit, .sidedrawer
                  {
                        display: none !important;
                        
                    } 
                    header, footer {
                        display: none !important;
                        
                    } 

            <?php
              } 
            ?>
</style>
<?php