<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;
chdir('../../');
include("./include/auth.php");

map_check_upgrade();

/* remember these search fields in session vars so we don't have to keep passing them around */
load_current_session_value("description", "sess_map_host", "");
load_current_session_value("down_device", "sess_map_down", "");
load_current_session_value("show", "sess_map_show", "");
$mapapikey = read_config_option('map_api_key');
$maptools = read_config_option('map_tools');

$gpslocation = geocod_address( read_config_option('map_center') );
$gpslocation_lati = $gpslocation[0];
$gpslocation_longi = $gpslocation[1];
map_log('Map gpslocation: '.print_r($gpslocation, true));

//map_log('server: '. print_r($_SERVER, true ) );
map_log('get: '. print_r($_GET, true ) );
map_log('cacti get: '. print_r($_CACTI_REQUEST, true ) );

$sql_show = '';
// check if extenddb is present, if so use it
if( db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='extenddb' AND status=1") ) {
	if (!isempty_request_var('show')) {
		set_request_var('show', sanitize_search_string(get_request_var("show")) );
		switch ( (get_request_var("show")) )
		{
			case -1:
			default:
			break;
			
			case 1: // Phone only
				$sql_show = "AND host.isPhone='on' AND host.isWifi!='on'";
			break;
			
			case 2: // network only
				$sql_show = "AND host.isPhone!='on' AND host.isWifi!='on'";
			break;
			
			case 3: // Wifi only
				$sql_show = "AND host.isWifi='on' AND host.isPhone!='on'";
			break;
		}
	
	} else {
		unset($_REQUEST["show"]);
		kill_session_var("sess_map_show");
	}
}

$sql_where  = '';
// down_device only
$down_device = get_request_var("down_device");
if( $down_device == '' || $down_device == '0' || $down_device == NULL ) {
	$down_device='0';
} else {
	$sql_where .= " AND host.status = 1";
	$down_device='1';
}

// Description of specific device
if (!isempty_request_var('description') ) {
	set_request_var('description', sanitize_search_string(get_request_var("description")) );
	$description = get_request_var("description");
	map_log('has description');
	$sql_where .= " AND host.description like '%$description%'";
} else {
	map_log('has no description');
	unset($_REQUEST["description"]);
	kill_session_var("sess_map_host");
}

$sql_query = "SELECT host.id as 'id', 
		host.description as 'description', host.hostname as 'hostname', sites.latitude as 'lat', sites.longitude as 'lon', sites.address1 as 'address', host.disabled as 'disabled', host.status as 'status'
		FROM host
		INNER JOIN sites ON host.site_id=sites.id
		$sql_show
		$sql_where 
		AND sites.id > 0
		ORDER BY host.id
		";
map_log('Map query: '.$sql_query);
$result = db_fetch_assoc($sql_query);

general_header();

?>

<script type="text/javascript">
<!--

function clearFilter() {
	<?php
		kill_session_var("sess_map_host");
		kill_session_var("sess_map_down");
		kill_session_var("sess_map_show");

		unset($_REQUEST["description"]);
		unset($_REQUEST["down_device"]);
		unset($_REQUEST["show"]);
	?>
	strURL  = 'map.php';
	document.location = strURL;
	}

-->
</script>

<?php

// TOP DEVICE SELECTION
html_start_box('<strong>Filters</strong>', '100%', '', '3', 'center', '');

?>
<meta charset="utf-8"/>
<tr class="even">
	<td>
        <form id=''map' action="<?php print $config['url_path'];?>plugins/map/map.php?header=false">
		<table cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Description :&nbsp;
				</td>
				<td width="1">
					<input type="text" name="description" size="25" value="<?php print get_request_var("description");?>">
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;&nbsp;Down device Only:
				</td>
				<td width="1">
					<input type="checkbox" name="down_device" value="1" <?php ($down_device=='1')?print 'checked':print '' ?>>
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;&nbsp;Show
				</td>
				<td width="1" title="Device To View" >
					<select name="show" id='show' >
						<option value='-1' <?php if (get_request_var('show') == '-1') {?> selected<?php }?>>All</option>
						<option value='1'<?php if (get_request_var('show') == '1') {?> selected<?php }?>>Phone</option>
						<option value='2'<?php if (get_request_var('show') == '2') {?> selected<?php }?>>Network</option>
						<option value='3'<?php if (get_request_var('show') == '3') {?> selected<?php }?>>WiFi</option>
					</select>
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					Devices&nbsp;&nbsp;
				</td>
				<td nowrap style='white-space: nowrap;' width="1">
					<input type="submit" value="Go" title="Set/Refresh Filters">
					<input id='clear' type='button' value='<?php print __('Clear');?>' onClick='clearFilter()' title="Reset fields to defaults">
				</td>
			</tr>
		</table>
		</form>
	</td>
</tr>

<?php
html_end_box();

html_start_box('', '100%', '', '3', 'center', '');

if( $maptools == '0' ) {
//************************************************************************* GoogleMap
	?>

	<script defer type="text/javascript">
    setTimeout(function() {
    location.reload();
    }, 300000);
	function initMap() {};
	$(() => {
        var center = new google.maps.LatLng(<?php print $gpslocation_lati .",". $gpslocation_longi ?>);
        var map = new google.maps.Map(document.getElementById('map'), {
          zoom: 10,
          center: center,
          mapTypeId: google.maps.MapTypeId.ROADMAP
        });

	var oms = new OverlappingMarkerSpiderfier(map, {
			keepSpiderfied : true, 
			markersWontMove : true,
			markersWontHide: true,
			circleSpiralSwitchover: 5});
		
		var markers = [];
		var iconBase = './images/';

<?php
		foreach( $result as $device ) {
		// get latitude, longitude and formatted address
?>
			var marker = new google.maps.Marker( {
				position: new google.maps.LatLng(<?php print $device['lat'];?>, <?php print $device['lon'];?>),
				title: "<?php print $device['description']. "\\n" . $device['hostname']. "\\n" . mb_convert_encoding($device['address'], 'UTF-8', 'ISO-8859-1');?>",
				icon: iconBase + '<?php if ($device['disabled'] == 'on') print 'pingrey.png'; else if ($device['status']==1) print 'pinred.png'; else if ($device['status']==2) print 'pinblue.png'; else print 'pingreen.png';?>'
			} );
			markers.push(marker);
			oms.addMarker(marker);
<?php
		}
?>

		var clusterOptions = {
			ignoreHidden: true,
			zoomOnClick: true,
			imagePath:'./images/m',
			maxZoom: 15,
		}
		var markerCluster = new MarkerClusterer(map, markers, clusterOptions );
    })
 
</script>
  <body>
    <div id="map-container"><div id="map"></div></div>
  </body>

<?php
} else {
//************************************************ OpenStreetMAP
$mapapikey = read_config_option('map_api_key');
map_log('Map location: '.$gpslocation_longi.','. $gpslocation_lati);
?>

</table>
<div id='map'>
<script>

	mapboxgl.accessToken = '<?php print $mapapikey?>'; //'pk.eyJ1IjoiYXJubyIsImEiOiJjajhvbW5mcjQwNHh3MzhxdXR3Y3lrOGJ4In0.Z9KUWZsed2piLTZxwlg0Ng';

    var map = new mapboxgl.Map({
		container: 'map',
		style: 'mapbox://styles/mapbox/streets-v11', // satellite-v9 ou streets-v11', 
		center: [<?php print $gpslocation_longi;?>, <?php print $gpslocation_lati;?>],
		zoom: 12
		});
		
	// Create a popup, but don't add it to the map yet.
	var popup = new mapboxgl.Popup({
		closeButton: false,
		closeOnClick: false
	});
	
	map.resize();
	
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
<?php
				foreach( $result as $device ) {
				// get latitude, longitude and formatted address
?>	
					{
						'type': 'Feature',
						'properties': {
							'description':
								"<?php print $device['description']. "<br>" . $device['hostname']. "<br>" . $device['address'];?>",
								'status': '<?php if ($device['disabled'] == 'on') print 'disabled'; else if ($device['status']==1) print 'down'; else if ($device['status']==2) print 'recovering'; else if ($device['status']==3) print 'up'; else print 'unknown';?>'
						},
						'geometry': {
							'type': 'Point',
							'coordinates': [<?php print $device['lon'];?>, <?php print $device['lat'];?>]
						}
					},
<?php	
				}
?>
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
					20,
					15,
					20
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
			// if max zoom display list of device on cluster
			if( map.getZoom() >= 15 ) {
			// Get all points under a cluster
				var features = map.queryRenderedFeatures(e.point, { layers: ['cluster_places'] });
				clusterSource = map.getSource('places');
				if( features.length > 0 ) {
					var clusterId = features[0].properties.cluster_id,
					point_count = features[0].properties.point_count, clusterSource;
				}		
				var text='';
				clusterSource.getClusterLeaves(clusterId, point_count, 0, function(err, aFeatures){
					for (let i = 0; i < point_count; i++ ){
						text += aFeatures[i].properties.description+'<br>';
					}					
					popup.setLngLat(e.lngLat).setHTML(text).addTo(map);
				})

			} else map.getCanvas().style.cursor = 'Pointer';

		});
		
		map.on('mouseleave', 'cluster_places', function (e) {
			popup.remove();
			map.getCanvas().style.cursor = '';
		});
		
		map.on('click', 'cluster_places', function (e) {
			// click onthe cluster, then zoom it
			map.setCenter(e.lngLat);
			if( map.getZoom() < 15 ) {
			  map.setZoom( map.getZoom() + 1);
			} else {
				popup.remove();
				map.setZoom(15);
				map.getCanvas().style.cursor = '';
			}
	        
			var features = map.queryRenderedFeatures(e.point, { layers: ['cluster_places'] });
			console.log('queryRenderedFeatures', features);
			clusterSource = map.getSource('places');
			if( features.length > 0 ) {
				var clusterId = features[0].properties.cluster_id,
				point_count = features[0].properties.point_count,
				clusterSource;
			}		
		});
			
	});
</script>
<?php
}

html_end_box(false, true);

bottom_footer();

?>
