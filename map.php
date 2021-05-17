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
$mapapikey = read_config_option('map_api_key');
$maptools = read_config_option('map_tools');
// check if extenddb is present, if so use it
$sql_phone = '';
if( ! db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='extenddb' AND status=1") ) {
	$sql_phone = "AND host.isPhone!='on'";
}

$sql_where  = '';
$description       = get_request_var_request("description");

if ($description != '') {
	$sql_where .= " AND " . "host.description like '%$description%'";
}

general_header();

$sql_query = "SELECT host.id as 'id', 
		host.description as 'description', host.hostname as 'hostname', sites.latitude as 'lat', sites.longitude as 'lon', sites.address1 as 'address', host.disabled as 'disabled', host.status as 'status'
		FROM host, sites
		WHERE host.site_id=sites.id
		$sql_phone
		$sql_where 
		ORDER BY host.id
		";
map_log('Map query: '.$sql_query);
$result = db_fetch_assoc($sql_query);
?>

<script type="text/javascript">
<!--

function applyFilterChange(objForm) {
	if( objForm.description.length > 0)
		strURL = '&description=' + objForm.description.value;
	else strURL = '';
	document.location = strURL;
}
function clearFilter() {
	<?php
		kill_session_var("sess_map_host");

		unset($_REQUEST["description"]);
	?>
	strURL  = 'map.php?header=false';
	loadPageNoHeader(strURL);
	}

-->
</script>

<?php
// TOP DEVICE SELECTION
html_start_box('<strong>Filters</strong>', '100%', '', '3', 'center', '');

?>
<meta charset="utf-8"/>
	<td class="noprint">
	<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $config['url_path'];?>plugins/map/map.php">
		<table width="100%" cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;Description :&nbsp;
				</td>
				<td width="1">
					<input type="text" name="description" size="25" value="<?php print get_request_var_request("description");?>">
				</td>
				<td nowrap style='white-space: nowrap;'>
					<input type="submit" value="Go" title="Set/Refresh Filters">
					<input type='button' value="Clear" id='clear' onClick='clearFilter()' title="Reset fields to defaults">
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
    <style>
      #map-container {
        padding: 6px;
        border-width: 1px;
        border-style: solid;
        border-color: #ccc #ccc #999 #ccc;
        -webkit-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
        -moz-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
        box-shadow: rgba(64, 64, 64, 0.1) 0 2px 5px;
        width: 1024px;
      }
      #map {
        width: 1024px;
        height: 768px;
      }
    </style>

	<script type="text/javascript" src="<?php print $config['url_path'] ?>plugins/map/markerclusterer.js"></script>
    <script async defer type="text/javascript" src="https://maps.googleapis.com/maps/api/js?<?php ($mapapikey != NULL)?print 'key='.$mapapikey."&":"" ?>callback=initMap"></script>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/OverlappingMarkerSpiderfier/1.0.3/oms.min.js"></script>

	<script defer type="text/javascript">
    // auto refresh every 5 minutes
    setTimeout(function() {
    location.reload();
    }, 300000);

	window.initMap = function() {
		<?php
		$gpslocation_lati = read_config_option('map_center_gps_lati');
		$gpslocation_longi = read_config_option('map_center_gps_longi');
		?>
        var center = new google.maps.LatLng(<?php print $gpslocation_lati .",". $gpslocation_longi ?>);
        var map = new google.maps.Map(document.getElementById('map'), {
          zoom: 10,
          center: center,
          mapTypeId: google.maps.MapTypeId.ROADMAP
        });

	var oms = new OverlappingMarkerSpiderfier(map, {keepSpiderfied : true, 
			markersWontMove : false, 
			circleSpiralSwitchover: 5});
		
		var markers = [];
		var iconBase = './images/';

<?php
		foreach( $result as $device ) {
		// get latitude, longitude and formatted address
?>
			var marker = new google.maps.Marker( {
				position: new google.maps.LatLng(<?php print $device['lat'];?>, <?php print $device['lon'];?>),
				title: "<?php print $device['description']. "\\n" . $device['hostname']. "\\n" . utf8_encode($device['address']);?>",
				icon: iconBase + '<?php if ($device['disabled'] == 'on') print 'pingrey.png'; else if ($device['status']==1) print 'pin.png'; else if ($device['status']==2) print 'pinblue.png'; else print 'pingreen.png';?>'
			} );
			markers.push(marker);
			oms.addMarker(marker);
<?php
		}
?>

		var markerCluster = new MarkerClusterer(map, markers, {imagePath: './images/m', maxZoom: 15});
		google.maps.event.addDomListener(window, 'load', initMap);
    }
 
</script>
  <body>
    <div id="map-container"><div id="map"></div></div>
  </body>

<?php
} else {
//************************************************ OpenStreetMAP
	$gpslocation_lati = read_config_option('map_center_gps_lati');
	$gpslocation_longi = read_config_option('map_center_gps_longi');
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
  integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
  crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
  integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
  crossorigin=""></script>
	<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.css">
	<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.3.0/dist/MarkerCluster.Default.css">
	<script src="https://unpkg.com/leaflet.markercluster@1.3.0/dist/leaflet.markercluster.js"></script>
  

<div id="map" style="width: 800px; height: 600px;"></div>
<script>
    var mymap = L.map('map', {center: [<?php print $gpslocation_lati .",". $gpslocation_longi ?>], zoom: 13} );
	var tiles = L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=pk.eyJ1IjoiYXJubyIsImEiOiJjajhvbW5mcjQwNHh3MzhxdXR3Y3lrOGJ4In0.Z9KUWZsed2piLTZxwlg0Ng', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
            '<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery Â© <a href="https://www.mapbox.com/">Mapbox</a>',
        id: 'mapbox/streets-v11',
		tileSize: 512,
		zoomOffset: -1,
		accessToken: 'pk.eyJ1IjoiYXJubyIsImEiOiJjajhvbW5mcjQwNHh3MzhxdXR3Y3lrOGJ4In0.Z9KUWZsed2piLTZxwlg0Ng'
    }).addTo(mymap);

    var pingrey = L.icon ({
    iconUrl: './images/pingrey.png',
    iconSize: [30,48],
    iconAnchor: [15,48],
    });

    var pingreen = L.icon ({
    iconUrl: './images/pingreen.png',
    iconSize: [30,48],
    iconAnchor: [15,48],
    });

    var pinblue = L.icon ({
    iconUrl: './images/pinblue.png',
    iconSize: [30,48],
    iconAnchor: [15,48],
    });

    var pinred = L.icon ({
    iconUrl: './images/pin.png',
    iconSize: [30,48],
    iconAnchor: [15,48],
    });

 	var markersCluster = L.markerClusterGroup();
<?php
		foreach( $result as $device ) {
		// get latitude, longitude and formatted address
?>
			var marker = new L.marker([<?php print $device['lat'];?>, <?php print $device['lon'];?>],
            {title: "<?php print $device['description']?>", 
			icon: <?php if ($device['disabled'] == 'on') print 'pingrey'; else if ($device['status']==1) print 'pinred'; 
			else if ($device['status']==2) print 'pinblue'; else print 'pingreen';?>} );

			marker.bindPopup( "<?php print $device['description']. "<br>" . $device['hostname']. "<br>" . $device['address'];?>");

		    markersCluster.addLayer(marker);

<?php
		}
?>
		mymap.addLayer(markersCluster);

	setTimeout(function(){ mymap.invalidateSize()}, 10);
</script>
<?php
}
html_end_box(false);

bottom_footer();

?>
