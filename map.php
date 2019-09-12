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

map_setup_table();
map_check_upgrade();

/* if the user pushed the 'clear' button */
if (isset($_REQUEST["button_clear_x"])) {
	kill_session_var("sess_map_host");

	unset($_REQUEST["description"]);
}

/* remember these search fields in session vars so we don't have to keep passing them around */
load_current_session_value("description", "sess_map_host", "");
$mapapikey = read_config_option('map_api_key');
$maptools = read_config_option('map_tools');

$sql_where  = '';
$description       = get_request_var_request("description");

if ($description != '') {
	$sql_where .= " AND " . "host.description like '%$description%'";
}

include(dirname(__FILE__) . "/general_header.php");

$sql_query = "SELECT host.id as 'id', 
		host.description as 'description', host.hostname as 'hostname', map.lat as 'lat', map.lon as 'lon', map.address as 'address', host.disabled as 'disabled', host.status as 'status'
		FROM host, plugin_map_coordinate map, plugin_map_host maphost
		WHERE host.id=maphost.host_id
		AND maphost.address_id=map.id
		$sql_where 
		ORDER BY host.id";

$result = db_fetch_assoc($sql_query);
?>
<script type="text/javascript">
<!--

function applyFilterChange(objForm) {
	if( objForm.host.length > 0)
		strURL = '&description=' + objForm.host.value;
	else strURL = '';
		document.location = strURL;
}

-->
</script>

<?php
// TOP DEVICE SELECTION
html_start_box("<strong>Filters</strong>", "100%", $colors["header"], "3", "center", "");

?>
<tr bgcolor="#<?php print $colors["panel"];?>" class="noprint">
	<td class="noprint">
	<form style="padding:0px;margin:0px;" name="form" method="get" action="<?php print $config['url_path'];?>plugins/map/map.php">
		<table width="100%" cellpadding="0" cellspacing="0">
			<tr class="noprint">
				<td nowrap style='white-space: nowrap;' width="1">
					&nbsp;description :&nbsp;
				</td>
				<td width="1">
					<input type="text" name="description" size="25" value="<?php print get_request_var_request("description");?>">
				</td>
				<td nowrap style='white-space: nowrap;'>
					&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
					<input type="submit" name="button_clear_x" value="Clear" title="Reset fields to defaults">
				</td>
			</tr>
		</table>
	</form>
	</td>
</tr>
<?php
html_end_box();

html_start_box("", "100%", $colors["header"], "3", "center", "");
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
        width: 800px;
      }
      #map {
        width: 800px;
        height: 500px;
      }
    </style>

    <script src="/cacti/plugins/map/markerclusterer.js"></script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?<?php ($mapapikey != NULL)?print 'key='.$mapapikey."&":"" ?>callback=initMap"></script>

	<script defer>
    // auto refresh every 5 minutes
    setTimeout(function() {
    location.reload();
    }, 300000);

	function initMap() {
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
		
		var markers = [];
		var iconBase = './images/';

<?php
		foreach( $result as $device ) {
		// get latitude, longitude and formatted address
?>
			var marker = new google.maps.Marker( {
				position: new google.maps.LatLng(<?php print $device['lat'];?>, <?php print $device['lon'];?>),
				title: '<?php print $device['description']. "\\n" . $device['hostname']. "\\n" .utf8_encode($device['address']);?>',
				icon: iconBase + '<?php if ($device['disabled'] == 'on') print 'pingrey.png'; else if ($device['status']==1) print 'pin.png'; else if ($device['status']==2) print 'pinblue.png'; else print 'pingreen.png';?>'
			} );
			markers.push(marker);
<?php
		}
?>
		var markerCluster = new MarkerClusterer(map, markers, {imagePath: './images/m'});
    }
    google.maps.event.addDomListener(window, 'load', initMap);

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

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.5.1/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="">
    <script src="https://unpkg.com/leaflet@1.5.1/dist/leaflet.js" integrity="sha512-GffPMF3RvMeYyc1LWMHtK8EbPv0iNZ8/oTtHPx9/cc2ILxQ+u905qIwdpULaqDkyBKgOaB57QTMg7ztg8Jm2Og==" crossorigin=""></script>

	<link rel="stylesheet" href="/cacti/plugins/map/MarkerCluster.css">
	<link rel="stylesheet" href="/cacti/plugins/map/MarkerCluster.Default.css">
	<script src="/cacti/plugins/map/leaflet.markercluster.js"></script>
  

<div id="map" style="width: 800px; height: 600px;"></div>
<script>

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

    var tiles = L.tileLayer('https://api.tiles.mapbox.com/v4/{id}/{z}/{x}/{y}.png?access_token=pk.eyJ1IjoiYXJubyIsImEiOiJjajhvbW5mcjQwNHh3MzhxdXR3Y3lrOGJ4In0.Z9KUWZsed2piLTZxwlg0Ng', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
            '<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
            'Imagery Â© <a href="https://www.mapbox.com/">Mapbox</a>',
        id: 'mapbox.streets'
    });

    var mymap = L.map('map', {center: [<?php print $gpslocation_lati .",". $gpslocation_longi ?>], zoom: 13, layers: [tiles]} );


	var markersCluster = L.markerClusterGroup();
<?php
		foreach( $result as $device ) {
		// get latitude, longitude and formatted address
?>
			var marker = new L.marker([<?php print $device['lat'];?>, <?php print $device['lon'];?>],
            {title: "<?php print $device['description']?>", 
			icon: <?php if ($device['disabled'] == 'on') print 'pingrey'; else if ($device['status']==1) print 'pinred'; 
			else if ($device['status']==2) print 'pinblue'; else print 'pingreen';?>} );

			marker.bindPopup( "<?php print $device['description']. "<br>" .$device['hostname']. "<br>" . $device['address'];?>");

		    markersCluster.addLayer(marker);

<?php
		}
?>
		mymap.addLayer(markersCluster);

	setTimeout(function(){ mymap.invalidateSize()}, 100);
</script>
<?php
}
html_end_box(false);

include_once("./include/bottom_footer.php");

?>
