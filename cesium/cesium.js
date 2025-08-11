import * as Cesium from 'cesium';

// Initialize the Cesium ion access token
Cesium.Ion.defaultAccessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJqdGkiOiJlYWE1OWUxNy1mMWZiLTQzYjYtYTQ0OS1kMWFjYmFkNjc5YzciLCJpZCI6NTc3MzMsImlhdCI6MTYyMjY0NjQ5OH0.XcKpgANiY19MC4bdFUXMVEBToBmqS8kuYpUlxJHYZxY';

// Create the viewer
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

// Remove the default Cesium ion logo
viewer._cesiumWidget._creditContainer.style.display = "none";

// Set initial camera position
viewer.camera.setView({
    destination: Cesium.Cartesian3.fromDegrees(-75.59777, 40.03883, 1000.0),
    orientation: {
        heading: Cesium.Math.toRadians(0.0),
        pitch: Cesium.Math.toRadians(-45.0),
        roll: 0.0
    }
});

// Add a simple point entity
viewer.entities.add({
    position: Cesium.Cartesian3.fromDegrees(-75.59777, 40.03883, 0),
    point: {
        pixelSize: 10,
        color: Cesium.Color.RED
    },
    label: {
        text: 'Hello World!',
        font: '14pt sans-serif',
        fillColor: Cesium.Color.WHITE,
        style: Cesium.LabelStyle.FILL_AND_OUTLINE,
        outlineWidth: 2,
        verticalOrigin: Cesium.VerticalOrigin.BOTTOM,
        pixelOffset: new Cesium.Cartesian2(0, -10)
    }
});

// Make sure the viewer is initialized
// console.log('Cesium viewer initialized:', viewer);

// Export viewer for potential use in other modules
export { viewer };

// Also make it available globally
window.viewer = viewer; 