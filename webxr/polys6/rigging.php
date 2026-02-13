<?php

$speed = "5";
if(@$_GET['speed']){
    $speed = $_GET['speed'];    
}
$cam_x =0;
$cam_y =1.6;
$cam_z =-3.5;

$fov = 50; // default FOV
if(@$_GET['fov']){
    $fov = 50;

}



if(@$_GET['camera']){
    $cam_coords =  explode("~",$_GET['camera']);
    if(count($cam_coords) == 3){
      $cam_x = $cam_coords[0];
      $cam_y = $cam_coords[1];
      $cam_z = $cam_coords[2];
    }
  
}
$camera = "$cam_x $cam_y $cam_z";
?>



<a-entity>
            <a-text id="GL-VR" visible="false" position="2.55 -0.1 0.01" value="" color="white" width="4"
                line-height="50" text="wrapCount: 30"></a-text>
            <a-text id="GL-PC" visible="false" position="2.55 -0.1 0.1" value="" color="black" width="5"
                line-height="50" text="wrapCount: 30"></a-text>
            <a-text id="GL-SP" visible="false" position="2.55 -0.1 0.1" value="" color="black" width="5"
                line-height="50" text="wrapCount: 30"></a-text>


            <a-text id="SMH-VR" visible="false" position="-6.65 -0.57 0.01" value="" color="black" width="5"
                line-height="60" text="wrapCount: 30"></a-text>
            <a-text id="SMH-PC" visible="false" position="-6.65 -0.75 0.01" value="" color="black" width="5"
                line-height="40" text="wrapCount: 30"></a-text>
            <a-text id="SMH-SP" visible="false" position="-6.65 -0.6 0.01" value="" color="black" width="5"
                line-height="50" text="wrapCount: 25"></a-text>
        </a-entity>

<script>
AFRAME.registerComponent('thumbstick-move', {
    schema: {
        speed: {type: 'number', default: 5},
        fly: {type: 'boolean', default: true},
        turnSpeed: {type: 'number', default: 2}
    },
    init: function () {
        this.leftAxis = {x: 0, y: 0};
        this.rightAxis = {x: 0, y: 0};
        this.moveVector = new THREE.Vector3();
        this.direction = new THREE.Vector3();
        this.rotation = new THREE.Euler();

        var self = this;
        this.el.sceneEl.addEventListener('loaded', function () {
            var leftHand = document.getElementById('left-hand');
            var rightHand = document.getElementById('right-hand');
            if (leftHand) {
                leftHand.addEventListener('thumbstickmoved', function (evt) {
                    self.leftAxis.x = evt.detail.x;
                    self.leftAxis.y = evt.detail.y;
                });
            }
            if (rightHand) {
                rightHand.addEventListener('thumbstickmoved', function (evt) {
                    self.rightAxis.x = evt.detail.x;
                    self.rightAxis.y = evt.detail.y;
                });
            }
        });
    },
    tick: function (t, dt) {
        if (dt > 100) dt = 100;
        var seconds = dt / 1000;
        var speed = this.data.speed;
        var turnSpeed = this.data.turnSpeed;
        var lx = this.leftAxis.x;
        var ly = this.leftAxis.y;
        var rx = this.rightAxis.x;

        var deadzone = 0.15;
        if (Math.abs(lx) < deadzone) lx = 0;
        if (Math.abs(ly) < deadzone) ly = 0;
        if (Math.abs(rx) < deadzone) rx = 0;

        if (lx === 0 && ly === 0 && rx === 0) return;

        var camera = document.getElementById('camera');
        if (!camera) return;
        var camObj = camera.object3D;

        // Yaw rotation from left stick X or right stick X
        if (lx !== 0) {
            this.el.object3D.rotation.y -= lx * turnSpeed * seconds;
        }
        if (rx !== 0) {
            this.el.object3D.rotation.y -= rx * turnSpeed * seconds;
        }

        // Forward/backward from left stick Y
        if (ly !== 0) {
            camObj.getWorldDirection(this.direction);
            if (!this.data.fly) {
                this.direction.y = 0;
            }
            this.direction.normalize();
            this.direction.multiplyScalar(-ly * speed * seconds);
            this.el.object3D.position.add(this.direction);
        }
    }
});
</script>

<a-entity id="rig"
    movement-controls="speed: <?=$speed?>; fly: true; constrainToNavMesh: false;"
    thumbstick-move="speed: <?=$speed?>; fly: true; turnSpeed: 2"
    position="0 0.1 1">

    <a-entity id="camera" camera="fov: <?=$fov?>"
        look-controls="pointerLockEnabled: false"
        wasd-controls="fly: true; acceleration: 20"
        raycaster="far: 5; objects: .clickable"
        cursor="rayOrigin: mouse"
        super-hands="colliderEvent: raycaster-intersection;
                     colliderEventProperty: els;
                     colliderEndEvent: raycaster-intersection-cleared;
                     colliderEndEventProperty: clearedEls;"
        position="<?=$camera?>"
        rotation="0 0 0">
        <a-entity id="crosshair" position="0 0 -0.2"
            geometry="primitive: ring; radiusInner: 0.002; radiusOuter: 0.003"
            material="shader: flat" visible="false"></a-entity>
    </a-entity>

    <a-entity id="left-hand"
        hand-tracking-controls="hand: left; modelColor: #0055ff"
        meta-touch-controls="hand: left"
        physics-collider
        static-body="shape: sphere; sphereRadius: 0.05"
        super-hands="colliderEvent: collisions;
                     colliderEventProperty: els;
                     colliderEndEvent: collisions;
                     colliderEndEventProperty: clearedEls;
                     grabStartButtons: gripdown, triggerdown;
                     grabEndButtons: gripup, triggerup;">
    </a-entity>

    <a-entity id="right-hand"
        hand-tracking-controls="hand: right; modelColor: #0055ff"
        meta-touch-controls="hand: right"
        physics-collider
        static-body="shape: sphere; sphereRadius: 0.05"
        super-hands="colliderEvent: collisions;
                     colliderEventProperty: els;
                     colliderEndEvent: collisions;
                     colliderEndEventProperty: clearedEls;
                     grabStartButtons: gripdown, triggerdown;
                     grabEndButtons: gripup, triggerup;"
        blink-controls="cameraRig: #rig; teleportOrigin: #camera;
                        collisionEntities: #ring1, #ring2, #ring3, #ring4, #ring5, #ring6, #floor;
                        hitCylinderColor: #FF0; interval: 10;
                        curveHitColor: #e9974c; curveNumberPoints: 40;
                        curveShootingSpeed: 8;">
    </a-entity>
</a-entity>

