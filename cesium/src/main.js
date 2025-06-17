import * as Cesium from 'cesium';
import 'cesium/Build/Cesium/Widgets/widgets.css';

// Initialize Cesium ion access token
Cesium.Ion.defaultAccessToken = 'your_access_token_here';

// Initialize the Cesium Viewer
const viewer = new Cesium.Viewer('cesiumContainer', {
    terrainProvider: Cesium.createWorldTerrain(),
    animation: false,
    baseLayerPicker: false,
    fullscreenButton: false,
    geocoder: false,
    homeButton: false,
    infoBox: false,
    sceneModePicker: false,
    selectionIndicator: false,
    timeline: false,
    navigationHelpButton: false,
    scene3DOnly: true
});

// Remove default imagery and add Bing Maps imagery
viewer.imageryLayers.removeAll();
viewer.imageryLayers.addImageryProvider(
    new Cesium.IonImageryProvider({ assetId: 3 })
);

// Set initial camera position
viewer.camera.setView({
    destination: Cesium.Cartesian3.fromDegrees(-75.59777, 40.03883, 1000000.0)
});

// Export viewer for potential use in other modules
export { viewer }; 