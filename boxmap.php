<!DOCTYPE html>
<html lang='en'>
  <head>
    <meta charset='utf-8' />
    <title>Lausanne Network</title>
    <meta name='viewport' content='width=device-width, initial-scale=1' />
    <script src='https://api.tiles.mapbox.com/mapbox-gl-js/v2.2.0/mapbox-gl.js'></script>
	<link href="/cacti/plugins/map/mapbox-gl.css" type='text/css' rel='stylesheet'/>
    <style>

#map {   
position: absolute;
  width: 800px;
  height: 600px;
  top: 0; 
  bottom: 0;
 }
    </style>
  </head>
  <body>

<div id='map'>
<script>
mapboxgl.accessToken = 'pk.eyJ1IjoiYXJubyIsImEiOiJjajhvbW5mcjQwNHh3MzhxdXR3Y3lrOGJ4In0.Z9KUWZsed2piLTZxwlg0Ng';

    var map = new mapboxgl.Map({
		container: 'map',
		style: 'mapbox://styles/mapbox/streets-v11', // satellite-v9 ou streets-v11', 
		center: [6.6188865,46.5228798],
		zoom: 13
		});
		
	// Create a popup, but don't add it to the map yet.
	var popup = new mapboxgl.Popup({
		closeButton: false,
		closeOnClick: false
	});
	
	map.on('load', function() {
		
		map.addSource('places', {
		'type': 'geojson',
		'cluster': true,
		'clusterRadius': 25,
		'clusterProperties': {
			'number':['+', 1]
			},
		'data': {
			'type': 'FeatureCollection',
			'features': [
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"lsinet195<br>10.253.247.19<br>Place Chauderon 9",
								'status': 'up'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6251866000, 46.5229745000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"sre-core<br>10.0.2.26<br>Chemin de Pierre-de-Plan",
								'status': 'up'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6437855000, 46.5287369000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"sre-vbb-30<br>10.1.128.10<br>Chemin de Pierre-de-Plan",
								'status': 'down'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6437855000, 46.5287369000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-ch9-40<br>10.128.1.40<br>Place du Tunnel",
								'status': 'recovering'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6339305000, 46.5262459000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-pdp-40<br>10.128.1.41<br>Chemin de Pierre-de-Plan",
								'status': 'disabled'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6437855000, 46.5287369000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-dch9-72<br>10.0.2.54<br>Place Chauderon 9",
								'status': 'disabled'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6251866000, 46.5229745000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-dch9-71<br>10.0.2.53<br>Place Chauderon 9",
								'status': 'up'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6251866000, 46.5229745000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-dch9-60<br>10.128.1.60<br>Chemin du Viaduc 14",
								'status': 'disabled'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.5992070864, 46.5286483000]
						}
					},
	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"se-se46-8502<br>10.85.0.17<br>Avenue de Sévelin 46",
								'status': 'down'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [6.6188865000, 46.5228798000]
						}
					}
			]}
		});

		map.addLayer({
			id: 'cluster_places',
			type: 'circle',
			source: 'places',
			filter: ['has', 'point_count'],
			paint: {
				// Use step expressions (https://docs.mapbox.com/mapbox-gl-js/style-spec/#expressions-step)
				// with three steps to implement three types of circles:
				//   * Blue, 20px circles when point count is less than 5
				//   * Yellow, 30px circles when point count is between 5 and 15
				//   * Pink, 40px circles when point count is greater than or equal to 15
				'circle-color': [
					'step',
					['get', 'point_count'],
					'#51bbd6',
					5,
					'#f1f075',
					15,
					'#f28cb1'
				],
				'circle-radius': [
					'step',
					['get', 'point_count'],
					20,
					5,
					30,
					15,
					40
				]
			}
		});
 
 
// Add a layer showing the number of point in the cluster.
		map.addLayer({
			id: 'cluster_count_places',
			type: 'symbol',
			source: 'places',
			filter: ['has', 'point_count'],
			layout: {
				'text-field': '{point_count_abbreviated}',
				'text-font': ['DIN Offc Pro Medium', 'Arial Unicode MS Bold'],
				'text-size': 12
			}
		});
 
 // Add a layer showing the points.
		map.addLayer({
			'id': 'points',
			'type': 'circle',
			'source': 'places',
			'filter': ['!', ['has', 'point_count']],
			'paint': {
					// make circles larger as the user zooms from z12 to z22
				'circle-radius': {
					'base': 1.75,
					'stops': [
						[10, 10],
						[22, 15]
					]
				},
				'circle-color': [
					'match',
					['get', 'status'],
					'disabled',
					'black',
					'down',
					'red',
					'up',
					'green',
					'recovering',
					'orange',
					'blue'
					]
			}
		});

		map.on('mouseenter', 'points', function (e) {
			// Change the cursor style as a UI indicator.
			map.getCanvas().style.cursor = 'Pointer';
			
			var coordinates = e.features[0].geometry.coordinates.slice();
			var description = e.features[0].properties.description;
			var status = e.features[0].properties.status;
			
			// Ensure that if the map is zoomed out such that multiple
			// copies of the feature are visible, the popup appears
			// over the copy being Pointed to.
			while (Math.abs(e.lngLat.lng - coordinates[0]) > 180) {
				coordinates[0] += e.lngLat.lng > coordinates[0] ? 360 : -360;
			}
			
			// Populate the popup and set its coordinates
			// based on the feature found.
			// if we have more than one point it's a cluster, otherwise it's a place
			popup.setLngLat(coordinates).setHTML(description+'<br>'+status).addTo(map);
		});
			
		map.on('mouseleave', 'points', function () {
			map.getCanvas().style.cursor = '';
			popup.remove();
		});
		
// cluster view management
		map.on('mouseenter', 'cluster_places', function (e) {
			// Change the cursor style as a UI indicator.
			map.getCanvas().style.cursor = 'Pointer';
		});
		
		map.on('click', 'cluster_places', function (e) {
			// click onthe cluster, then zoom it
			map.setCenter(e.lngLat);
			map.setZoom( map.getZoom() + 1);
	        
			var features = map.queryRenderedFeatures(e.point, { layers: ['cluster_places'] });
			console.log('queryRenderedFeatures', features);
			clusterSource = map.getSource('places');
			if( features.length > 0 ) {
				var clusterId = features[0].properties.cluster_id,
				point_count = features[0].properties.point_count,
				clusterSource;
			}		
/*			
			// Get Next level cluster Children
			clusterSource.getClusterChildren(clusterId, function(err, aFeatures){
				console.log('getClusterChildren', err, aFeatures);
			});
			
			// Get all points under a cluster
			clusterSource.getClusterLeaves(clusterId, point_count, 0, function(err, aFeatures){
				console.log('getClusterLeaves', err, aFeatures);
			})
*/
		});
			
	});
	</script>
  </body>
</html>
