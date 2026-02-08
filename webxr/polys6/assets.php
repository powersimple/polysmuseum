

<a-asset-item id="platform" response-type="arraybuffer" position="0 0 -5" src="/assets/models/2022Summits/9SidedPlatform.glb">
</a-asset-item>
<a-asset-item id="brandtownhall" response-type="arraybuffer" src="/assets/models/2022Summits/2022_WebXR_Brand_Summit_TownHall_Long.glb"></a-asset-item>
<a-asset-item id="emblem" response-type="arraybuffer" src="/assets/models/emblem2022.glb"></a-asset-item>
<a-asset-item id="chair" response-type="arraybuffer" src="/assets/models/chair.glb"></a-asset-item>


<a-asset-item id="golden-gizmo-ring" response-type="arraybuffer" src="/assets/models/polys/6th/GoldenGizmoRing.glb"></a-asset-item>





<a-asset-item id="The6thPolysLogo" response-type="arraybuffer" src="/assets/models/polys/6th/6thPolysLogo-Trophy.glb"></a-asset-item>

<a-asset-item id="ring" response-type="arraybuffer" position="0 0 -5" src="/assets/models/polys/6th/RedRing.glb">


<?php if(@$_GET['leadsponsor']){

?>
<a-asset-item id="lead-sponsor" response-type="arraybuffer" src="/assets/models/sponsors/<?=$_GET['leadsponsor']?>.glb"></a-asset-item>
<?php
}
?>
<?php
    if(@$_GET['skybox']){
        ?>
        <img id="sky" src="/assets/images/skybox/<?=$_GET['skybox']?>.jpg">

   
    <?php
    } else {


    $skybox_id = get_post_meta($post->ID,"skybox",true);
    //var_dump($skybox_id);
$skybox = str_replace("-scaled","",getThumbnail($skybox_id));
   if($skybox_id != ""){

?>
   <img crossorigin="anonymous" id="sky" src="<?=$skybox?>">
<?php
    } else {
        ?>
        <img id="sky" src="/wp-content/uploads/2022/11/skyboxTemplate-Red6k.jpg">
   <?php
    }
}//no blue or greenscreen

if(@$asset_list){// this var is created in panels.php
    foreach($asset_list as $slug => $src){
        ?>
<!-- ASSET <?=$slug?> -->
    <a-asset-item id="<?=$slug?>-logo3D" response-type="arraybuffer" src="<?=$src?>"></a-asset-item>

            <?php
        }
    }
/*
   if(@$assets3D){// this var is created in panels.php
    foreach($assets3D as $key => $asset3D){
        ?>
<!-- ASSET <?=$asset3D['slug']?> -->
    <a-asset-item id="<?=$asset3D['slug']?>-logo3D" response-type="arraybuffer" src="<?=$asset3D['asset']?>"></a-asset-item>

            <?php
        }
    }
*/
    ?>






<?php
    if(is_array(@$assets)){
        foreach($assets as $key => $asset){
         extract($asset);
            ?>
<a-asset-item id="<?=$slug?>" response-type="arraybuffer" src="<?=$path?>"></a-asset-item>
            <?php
        }
    }
?>

<?php
 $trophy = '2025-Hosts';//default
 if(@$_GET['trophy']){
     $trophy = @$_GET['trophy'];
 }
?>


<a-asset-item id="trophy" response-type="arraybuffer" src="/assets/models/polys/6th/<?=$trophy?>.glb"></a-asset-item>




<a-asset-item id="pedestal" response-type="arraybuffer" src="/assets/models/polys/pedestal.glb"></a-asset-item>

