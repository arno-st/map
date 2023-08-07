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
include_once($config['base_path'].'/lib/snmp.php');

function plugin_map_install () {
	api_plugin_register_hook('map', 'page_head', 'map_header', 'setup.php');
	api_plugin_register_hook('map', 'top_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'top_graph_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'draw_navigation_text', 'map_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('map', 'config_settings', 'map_config_settings', 'setup.php'); // personal settings info
	api_plugin_register_hook('map', 'api_device_new', 'map_api_device_new', 'setup.php');
	api_plugin_register_hook('map', 'utilities_action', 'map_utilities_action', 'setup.php');
	api_plugin_register_hook('map', 'utilities_list', 'map_utilities_list', 'setup.php');
	
// Device action
    api_plugin_register_hook('map', 'device_action_array', 'map_device_action_array', 'setup.php');
    api_plugin_register_hook('map', 'device_action_execute', 'map_device_action_execute', 'setup.php');
    api_plugin_register_hook('map', 'device_action_prepare', 'map_device_action_prepare', 'setup.php');

	api_plugin_register_realm('map', 'map.php', 'Plugin -> Map', 1);

	map_setup_table();
}

function plugin_map_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_map_check_config () {
	$ret = true;
	// Here we will check to ensure everything is configured
	map_check_upgrade ();
	
	return $ret;
}

function plugin_map_upgrade () {
	// Here we will upgrade to the newest version
	map_check_upgrade ();
	return false;
}

function map_version () {
	return plugin_map_version();
}

function map_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'map.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = plugin_map_version ();
	$current = $version['version'];
	$old     = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="map"');

	if ($current != $old) {

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='map'");
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["homepage"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
	if( $old < '1.1' ) {
		// move address from plugin_map_coordinate to sites
		db_execute("DROP TABLE IF EXISTS `plugin_map_coordinate`;");
		db_execute("DROP TABLE IF EXISTS `plugin_map_host`;");
	}
	if( $old < '1.3.1' ) {
		api_plugin_register_hook('map', 'page_head', 'map_header', 'setup.php');
	}
}

function plugin_map_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/map/INFO', true);
	return $info['info'];
}

function map_header() {
	global $config;
	$mapapikey = read_config_option('map_api_key');

	if( read_config_option('map_tools') == 0 ){
		//******************** GoogleMap
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
<?php
	} else {
		//******************* OpenStreet Map
?>

	<script src='https://api.tiles.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.js'></script>
	<link href="https://api.tiles.mapbox.com/mapbox-gl-js/v2.10.0/mapbox-gl.css" type='text/css' rel='stylesheet'/>
	
	<style>
		#map {   
			width: 1024px;
			height: 768px;
			top: 0; 
			bottom: 0;
		}
	</style>
<?php
 }

}

function map_config_settings () {
	global $tabs, $settings;
	$tabs["misc"] = "Misc";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['misc'] = 'Misc';
	$temp = array(
		"map_general_header" => array(
			"friendly_name" => "Map",
			"method" => "spacer",
			),
		"map_tools" => array(
			"friendly_name" => "Which maping tools",
			"description" => "Define the mapping tools used.",
			"method" => "drop_array",
			'array' => array("0" => "GoogleMap", "1" => "OpenStreetMap"),
			"default" => "20"
			),
		"map_api_key" => array(
			"friendly_name" => "API Key",
			"description" => "This is key for google map or OpenStreetMap usage.",
			"method" => "textbox",
			"max_length" => 150,
			"default" => ""
			),
		"map_center" => array(
			"friendly_name" => "Map center",
			"description" => "Address to where where should center the map (country;city;street,number).",
			"method" => "textbox",
			"max_length" => 120,
			"default" => ""
			),
		'map_do_geocoding' => array(
			'friendly_name' => "Enable the geocoding of a device when it's saved",
			'description' => "When a device is saved it will be geocoded, can take long internet time during mass discovery",
			'method' => 'checkbox',
			'default' => 'off',
			),
		'map_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages during map file creation',
			'method' => 'checkbox',
			'default' => 'off'
			),
	);
	
	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function map_show_tab () {
	global $config;
	include_once($config["library_path"] . "/database.php");

	if (api_user_realm_auth('map.php')) {
		if (!substr_count($_SERVER["REQUEST_URI"], "map.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/map/map.php"><img src="' . $config['url_path'] . 'plugins/map/images/tab_gpsmap.gif" alt="Map" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/map/map.php"><img src="' . $config['url_path'] . 'plugins/map/images/tab_gpsmap_down.gif" alt="Map" align="absmiddle" border="0"></a>';
		}
	}
}

function map_draw_navigation_text ($nav) {
	$nav["map.php:"] = array("title" => "map", "mapping" => "", "url" => "map.php", "level" => "0");

	$nav['map.php:'] = array('title' => 'map', 'mapping' => 'index.php:', 'url' => 'map.php', 'level' => '1');
	return $nav;
}

function map_utilities_list () {
	global $colors;
	html_header(array("Map Plugin"), 4);
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=map_rebuild'>Rebuild sites table</a>
		</td>
		<td class="textArea">
			This will rebuild the sites table from the device list.
		</td>
	<?php
	form_end_row();
}

function map_utilities_action ($action) {
	// get device list,  where snmp is active
	$dbquery = db_fetch_assoc("SELECT id, hostname, site_id, snmp_community, 
	snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
	ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
	snmp_priv_protocol, snmp_context FROM host 
	WHERE snmp_version > '0' 
	AND status = 3
	AND disabled != 'on'	
	ORDER BY id");
	if ( ($dbquery > 0) && $action == 'map_rebuild' ){
		if ($action == 'map_rebuild') {
		// Upgrade the map address table
			foreach ($dbquery as $host) {
				BuildLocation( $host, true );
			}
		}
		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	} 
	return $action;
}

function map_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

}

function map_api_device_new( $host_id ) {
// check valid call
	if( !array_key_exists('disabled', $host_id ) ) {
map_log('Not valid call: '. print_r($host_id, true) );
		return $host_id;
	}

	$do_geocoding = read_config_option('map_do_geocoding');
	if( !$do_geocoding ) {
		return $host_id;
	}
map_log('Enter Map: '.$host_id['description'] );

	BuildLocation( $host_id, false );
	
map_log('Exit Map' );

	return $host_id;
}

function  BuildLocation( $host, $force ) {
	$snmp_sysLocation = ".1.3.6.1.2.1.1.6.0";
/* id, hostname, snmp_community, 
		snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
		ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
		snmp_priv_protocol, snmp_context */
	// if snmp is not active return
	// or if site_id is valid return
	if( array_key_exists('availability_method', $host ) && array_key_exists('site_id', $host ) ) {
		if( ( $host['availability_method'] == 3 || $host['site_id'] != 0 ) && $force != true ) {
map_log("availability host: " . $host['hostname'] ."\n");
			return $host;
		}
	} else if( $force != true ) return $host;
	
	// device is saved, take the snmplocation to check with database
	$snmp_location = cacti_snmp_get_raw( $host['hostname'], $host['snmp_community'], 
	$snmp_sysLocation, $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
	$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'] ); 

// make some cleanup
	$snmp_location = trim( $snmp_location );
	$snmp_location = str_replace(array('"', '>', '<', "\\", "\n", "\r"), '', $snmp_location);
	$snmp_location = preg_replace( '/STRING: ?/i', '', $snmp_location );


	// geocod it, many Google query but the address is the geocoded one
	if( $snmp_location == 'U' || empty($snmp_location) ) {
map_log("error host: " . $host['hostname'] ." (".$host['id'].")\n");
		return $host;
	}
map_log("host: " . $host['hostname'] ." location: ".$snmp_location." (".$host['id'].")\n");
	
	$gpslocation = geocod_address ( $snmp_location );
	if( $gpslocation === false) { 
map_log("Wrong location host: " . $host['hostname'] ."\n");
		return $host;
	}

	$gpslocation[2] = str_replace( "'", "\'", $gpslocation[2]);
	$gpslocation[3]['address1'] = str_replace( "'", "\'", $gpslocation[3]['address1']);
	$gpslocation[3]['address2'] = str_replace( "'", "\'", $gpslocation[3]['address2']);
	$gpslocation[3]['city'] = str_replace( "'", "\'", $gpslocation[3]['city']);
	$gpslocation[3]['state'] = str_replace( "'", "\'", $gpslocation[3]['state']);
	$gpslocation[3]['country'] = str_replace( "'", "\'", $gpslocation[3]['country']);
	$gpslocation[3]['types'] = str_replace( "'", "\'", $gpslocation[3]['types']);

map_log("host location: " . $host['hostname'] ." location: ".$gpslocation[2]."\n");

	// check if this adress is present into the plugin_map_coordinate
	$sql_query = "SELECT id FROM sites WHERE name='".$gpslocation[2]."'";
	$address_id = db_fetch_cell($sql_query );
map_log("DB location id: " . $address_id . " query: ". $sql_query ."\n");
	if( $address_id == 0) // record does not exist
	{
		// save to new table with id and location
		$sql_query = "INSERT INTO sites (name, address1, city, state, postal_code, country, address2, latitude, longitude, notes) VALUES ('"
		. $gpslocation[2] ."','"
		. $gpslocation[3]['address1']." ".$gpslocation[3]['street_number']."','"
		. $gpslocation[3]['city']."','"
		. $gpslocation[3]['state']."','"
		. $gpslocation[3]['postal_code']."','"
		. $gpslocation[3]['country']."','"
		. $gpslocation[3]['address2']."','"
		. $gpslocation[0] . "', '"
		. $gpslocation[1] . "', '"
		. $gpslocation[3]['types']."' )";
		$ret = db_execute( $sql_query );
map_log("New location : " . $gpslocation[2] . " dbquery: " .$sql_query. "\n");
	} 

   // and add  host to host_table
	$address_id = db_fetch_cell("SELECT id FROM sites WHERE name='".$gpslocation[2]."'" );
	if ( !empty($address_id) ) {
		db_execute("UPDATE host SET site_id = ".$address_id. " where id=".$host['id'] );
	}
	
	return $host;
}

// return the full address, lat, lon
function geocod_address( $snmp_location ) {
	$maptools = read_config_option('map_tools');

	// parse google map for geocoding
	// location format: Country;City;Street_Building;Floor;Room;Rack;RU;Lat;lon
	$snmp_location = str_replace( ",", ";", $snmp_location );
	$address = explode( ';', $snmp_location ); // Suisse;Lausanne;Chemin de Pierre-de-Plan 4;-1;Local Telecom;;;46.54;6.56
	if( (count($address) <= 7) && count($address) > 2 ) {
		$gpslocation = array();
		if( $maptools == '0' ) {
			$gpslocation = GoogleGeocode($address);
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetGeocode($address);
		}
		if($gpslocation != false ){
			$gpslocation[2] = str_replace ("'", " ", $gpslocation[2]); // replace ' by space
		} 
	} else if( count($address) == 9 ) { 
		// gps coordinate
		if( $maptools == '0' ) {
			$gpslocation = GoogleReverGeocode( $address[7], $address[8] );
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetReverseGeocode( $address[7], $address[8] );
		}
		
	} else {
		map_log("Snmp location error: ".print_r($address, true)."\n" );
		$gpslocation = false;
	}
	return $gpslocation;
}

// return formatted_address
function GoogleReverGeocode ($lat, $lng ) {
	global $config;
	$mapapikey = read_config_option('map_api_key');
    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$lng."&key={$mapapikey}&sensor=true";
	else 
		$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng=".$lat.",".$lng."&sensor=true";

	//https://maps.googleapis.com/maps/api/geocode/json?latlng=46.51157,6.62179&amp;key=AIzaSyCpw0hNO2ZzIxKb9cTyrSPEN3ADvUTc5Xc&amp;sensor=true
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true, 512 );

    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
	// location as array
map_log("GoogleReverGeocode: ". print_r($resp['results'], true) );
		$location = array();
        // get the important data
//		if( $resp['results'][0]['types'][0] != 'plus_code' && $resp['results'][0]['types'][0] != 'premise' && !isset($resp['results'][1]) ) {
		if( $resp['results'][0]['types'][0] != 'premise' && !isset($resp['results'][1]) ) {
			$lati = $resp['results'][0]['geometry']['location']['lat'];
			$longi = $resp['results'][0]['geometry']['location']['lng'];
			$formatted_address = $resp['results'][0]['formatted_address'];
			$location = formatedJson( $resp['results'][0] );
		} else {
			$lati = $resp['results'][1]['geometry']['location']['lat'];
			$longi = $resp['results'][1]['geometry']['location']['lng'];
			$formatted_address = $resp['results'][1]['formatted_address'];
			$location = formatedJson( $resp['results'][1] );
		}

        // verify if data is complete
        $data_arr = array();            
        if($lati && $longi && $formatted_address){
         
            // put the data in the array
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    empty($formatted_address)?"Unnamed road":$formatted_address,
		    $location
                );
             
            return $data_arr;
             
        }else{
         
            array_push(
                $data_arr, 
                    $lat, 
                    $lng, 
                    "Unnamed road",
		    $location
                );
             
            return $data_arr;
        }
         
		map_log("Google ReverseGeocoding: ". $formatted_address );
    } else{
		map_log("Google Geocoding error: ".$resp['status'] );
        $formatted_address = false;
    }

	return $formatted_address;
}

function GoogleGeocode($location){
	global $config;
	$mapapikey = read_config_option('map_api_key');
	// Suisse;Lausanne;Chemin de Pierre-de-Plan 4;-1;Local Telecom;;;46.54;6.56
	$address = $location[2]. "," .$location[1]. "," . $location[0];
	$address = str_replace(' ', '+', $address );

map_log('Map GoogleGeocode: '.print_r($address, true));

	//https://maps.googleapis.com/maps/api/geocode/json?address=4+chemin+pierre+de+plan,+Lausanne,+Suisse&key=AIzaSyAr0rad39hJtQLiRoPqsTstFW9u8kl6PYA
    // url encode the address
    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}&key={$mapapikey}";
	else 
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}";
map_log('Map GoogleGeocode url: '.$url);
 
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true, 512 );

    // response status will be 'OK', if able to geocode given address 
	$location = array();
    if($resp['status']=='OK'){
        // get the important data
		if ($resp['results'][0]['types'][0] != 'plus_code' ) {
			// get the important data
			$lati = $resp['results'][0]['geometry']['location']['lat'];
			$longi = $resp['results'][0]['geometry']['location']['lng'];
			$formatted_address = $resp['results'][0]['formatted_address'];
			$location = formatedJson( $resp['results'][0] );
		} else {
			// get the important data
			$lati = $resp['results'][1]['geometry']['location']['lat'];
			$longi = $resp['results'][1]['geometry']['location']['lng'];
			$formatted_address = $resp['results'][1]['formatted_address'];
			$location = formatedJson( $resp['results'][1] );
		}
	// location as array

        // verify if data is complete
        // put the data in the array
        $data_arr = array();            
        if($lati && $longi && $formatted_address){
         
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    empty($formatted_address)?"Unnamed road":$formatted_address,
		    $location
                );
             
            return $data_arr;
             
        }else{
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $address,
		    $location
                );
             
            return $data_arr;
        }
         
    }else{
		map_log("Google Geocoding error: ".$resp['status'] );
        return false;
    }
}

function FormalizedAddress( $resp ) {
			$lati = $resp['lat'];
			$longi = $resp['lon'];

			if( !empty($resp['address']['road']) ) {
				$location['address1'] = utf8_decode($resp['address']['road']);
			} else if( !empty($resp['address']['pedestrian']) ) {
				$location['address1'] = utf8_decode($resp['address']['pedestrian']);
			} else if( !empty($resp['address']['path']) ) {
				$location['address1'] = utf8_decode($resp['address']['path']);
			} else if( !empty($resp['address']['address27']) ) {
				$location['address1'] = utf8_decode($resp['address']['address27']);
			} else {
				$location['address1'] = "unknown";
			}
			$location['street_number'] = empty($resp['address']['house_number'])?"":$resp['address']['house_number'];
			if ( !empty($resp['address']['city']) ) { 
				$location['city'] = $resp['address']['city'];
			} else if ( !empty($resp['address']['town']) ) {
				$location['city'] = $resp['address']['town'];
			} else if ( !empty($resp['address']['village']) ) {
				$location['city'] = $resp['address']['village'];
			} else if ( !empty($resp['address']['suburb']) ) {
				$location['city'] = $resp['address']['suburb'];
			} else if ( !empty($resp['address']['neighbourhood']) ) {
				$location['city'] = $resp['address']['neighbourhood'];
			} else {
				$location['city'] = "unknown";
			}
			if ( !empty($resp['address']['postcode']) ) {
				$location['postal_code'] = $resp['address']['postcode'];
			} else $location['postal_code'] = '0000';
			$location['country'] = $resp['address']['country'];
			$location['state'] = $resp['address']['state'];
			if ( !empty( $resp['address']['county']) ) {
				$location['address2'] =  $resp['address']['county'];
			} else  {
				$location['address2'] = "unknown";
			}
			$location['lat'] = $lati;
			$location['lon'] = $longi;
			$location['types'] = $resp['category'];

			$formatted_address = $location['address1']." ".$location['street_number'].", ".$location['postal_code']. " ".$location['city'].", ".$location['country'];
			$location['formated_address'] = $formatted_address;

			$data_arr = array();            
         
			array_push(
				$data_arr, 
				$lati, 
				$longi, 
				$formatted_address,
				$location
				);
			return $data_arr;
}

function OpenStreetGeocode($locations){
	// http://nominatim.openstreetmap.org/search/chemin pierre-de-plan 4 Lausanne?format=json&addressdetails=1&limit=1&polygon_svg=1
	
	/* jsonv2
	[{"place_id":"82095265","licence":"Data © OpenStreetMap contributors, ODbL 1.0. http:\/\/www.openstreetmap.org\/copyright","osm_type":"way","osm_id":"46835009","boundingbox":["46.5275177","46.5286706","6.643296","6.6451603"],"lat":"46.52810155","lon":"6.64424499124893","display_name":"Pierre-de-Plan, Chemin de Pierre-de-Plan, Chailly, Lausanne, District de Lausanne, Vaud, 1011, Suisse","place_rank":"30","category":"man_made","type":"works","importance":0.511,"address":{"address29":"Pierre-de-Plan","road":"Chemin de Pierre-de-Plan","neighbourhood":"Chailly","city":"Lausanne","county":"District de Lausanne","state":"Vaud","postcode":"1011","country":"Suisse","country_code":"ch"},"svg":"M 6.643296 -46.5280323 L 6.6436639 -46.527865200000001 6.6436909 -46.527716699999999 6.6439739 -46.527604500000002 6.6440302 -46.527667100000002 6.6441516 -46.5276183 6.6445688 -46.527548199999998 6.6450363 -46.527522099999999 6.6450765 -46.527517699999997 6.6451603 -46.528054300000001 6.6451038 -46.528148799999997 6.6438793 -46.528670599999998 6.6437815 -46.528624499999999 6.6435271 -46.5285194 6.6434554 -46.5284458 6.64361 -46.528383400000003 Z"}]
	*/
	//// Suisse;Lausanne;Chemin de Pierre-de-Plan 4;-1;Local Telecom;;;46.54;6.56
    // url encode the address
	
	$address = $locations[2].",".$locations[1].",".$locations[0];
	$address = str_replace( ' ', '%20', $address);
map_log('Map OpenStreetGeocode: '.print_r($address, true));

	$url = "https://nominatim.openstreetmap.org/search/". $address. "?format=jsonv2&addressdetails=1&limit=1";
map_log('Map OpenStreetGeocode url: '.$url);

	// Setup headers - I used the same headers from Firefox version 2.0.0.6
	$header[] = "Accept-Language: en,en-US;q=0.8,fr-FR;q=0.5,fr;q=0.3";
	$header[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:45.0) Gecko/20100101 Firefox/45.0";
	$header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";

    $handle = curl_init($url);
    curl_setopt($handle, CURLOPT_HTTPHEADER, $header); 
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($handle);
    curl_close($handle);

    libxml_use_internal_errors(true); // Prevent HTML errors from displaying
   
    $doc = new DOMDocument();
    $doc->loadHTML($html);

    if ($doc) {
		$location = array();
		// decode the json
		$resp = json_decode($doc->textContent, true, 512 );

        if( (json_last_error() == JSON_ERROR_NONE) && count($resp) > 0 ) {// get the important data
			return FormalizedAddress($resp[0]);
		} else {
			map_log("OpenStreetmap json error: ".json_last_error_msg()." loca: ".$url ."\n" );
			//return false;
			return FormalizedAddress( json_decode('{"boundingbox":["46.5228246","46.5229246","6.6190748","6.6191748"],"lat":"46.5228746","lon":"6.6191248","display_name":"Base Bar, 46, Avenue de Sévelin, Lausanne, District de Lausanne, Vaud, 1004, Suisse","place_rank":30,"category":"amenity","type":"bar","importance":0.611,"icon":"https://nominatim.openstreetmap.org/ui/mapicons//food_bar.p.20.png","address":{"amenity":"Base Bar","house_number":"46","road":"Avenue de Sévelin","city":"Lausanne","county":"District de Lausanne","state":"Vaud","postcode":"1004","country":"Suisse","country_code":"ch"}}', true, 512));
		}
		
	} else {
		map_log("OpenStreetmap Geocoding error: ".json_last_error_msg()."loca: ".$url ."\n" );
		return false;
	}
}

function OpenStreetReverseGeocode ($lat, $lng ) {
	// http://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=46.52810155&lon=6.64424499124893&zoom=18&addressdetails=1
/* JSONv2
{"place_id":"74144934","licence":"Data © OpenStreetMap contributors, ODbL 1.0. http:\/\/www.openstreetmap.org\/copyright","osm_type":"way","osm_id":"24634955","lat":"46.52894795","lon":"6.64445024999999","place_rank":"30","category":"leisure","type":"pitch","importance":"0","addresstype":"leisure","display_name":"Stade de La Sallaz, Chemin de Pierre-de-Plan, Chailly, Lausanne, District de Lausanne, Vaud, 1011, Suisse","name":"Stade de La Sallaz","address":{"pitch":"Stade de La Sallaz","road":"Chemin de Pierre-de-Plan","neighbourhood":"Chailly","city":"Lausanne","county":"District de Lausanne","state":"Vaud","postcode":"1011","country":"Suisse","country_code":"ch"},"boundingbox":["46.5285089","46.529387","6.6437265","6.645174"]}
*/	

	$url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=".$lat."&lon=".$lng."&zoom=18&addressdetails=1";

	// Setup headers - I used the same headers from Firefox version 2.0.0.6
	$header[] = "Accept-Language: en,en-US;q=0.8,fr-FR;q=0.5,fr;q=0.3";
	$header[] = "User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:45.0) Gecko/20100101 Firefox/45.0";
	$header[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";

    $handle = curl_init($url);
	curl_setopt($handle, CURLOPT_HTTPHEADER, $header); 
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
    $html = curl_exec($handle);
    curl_close($handle);

    libxml_use_internal_errors(true); // Prevent HTML errors from displaying
   
    $doc = new DOMDocument();
    $doc->loadHTML($html);

    if ($doc) {
		$location = array();
		// decode the json
		$resp = json_decode($doc->textContent, true, 512 );

        if( (json_last_error() == JSON_ERROR_NONE) && count($resp) > 0 ) {// get the important data
			return FormalizedAddress($resp);
		} else {
			map_log("OpenStreetmap json error: ".json_last_error()."loca: ".$url ."\n" );
			return false;
		}
		
	} else {
		map_log("OpenStreetmap Reverse Geocoding error: ".json_last_error()."loca: ".$url ."\n" );
		return false;
	}
}

function map_log( $text ){
	$dolog = read_config_option('map_log_debug');
	if( $dolog ) cacti_log( $text, false, "MAP" );

}

function formatedJson( $result ) {
	$location = array();
	$location['address1'] = " ";
	$location['street_number'] = " ";
	$location['city'] = " ";
	$location['address2'] = " ";
	$location['state'] = " ";
	$location['postal_code'] = " ";
	$location['country'] = " ";
	$location['types'] = " ";

map_log('formatedJson result: '. print_R($result, true) );
	foreach ($result['address_components'] as $component) {

		switch ($component['types']) {
			case in_array('street_number', $component['types']):
				$location['street_number'] = $component['long_name'];
			break;
			case in_array('route', $component['types']):
				$location['address1'] = $component['long_name'];
			break;
			case in_array('locality', $component['types']):
				$location['city'] = $component['long_name'];
			break;
			case in_array('administrative_area_level_2', $component['types']):
				$location['address2'] = $component['long_name'];
			break;
			case in_array('administrative_area_level_1', $component['types']):
				$location['state'] = $component['long_name'];
			break;
			case in_array('postal_code', $component['types']):
				$location['postal_code'] = $component['long_name'];
			break;
			case in_array('country', $component['types']):
				$location['country'] = $component['long_name'];
			break;
			default:
			map_log("json error: ".print_r($component['types'], true)." ".$component['long_name']."\n");
		}

	}

	$location['types'] = $result['types'][0];

	return $location;
}

function map_device_action_execute($action) {
        global $config;

        if ($action != 'map_geocode' ) {
                return $action;
        }

        $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

        if ($selected_items != false) {
                if ($action == 'map_geocode' ) {
                        for ($i = 0; ($i < count($selected_items)); $i++) {
				if ($action == 'map_geocode') {
					$dbquery = db_fetch_assoc("SELECT * FROM host WHERE id=".$selected_items[$i]);
map_log("Rebuild Mapping: ".$selected_items[$i]." - ".print_r($dbquery[0], true)." - ".$dbquery[0]['description']."\n");
					BuildLocation( $dbquery[0], true );
                                }
                        }
                 }
        }

        return $action;
}

function map_device_action_prepare($save) {
	global $host_list;
	
	$action = $save['drp_action'];
	
	if ($action != 'map_geocode' ) {
		return $save;
	}
	
	if ($action == 'map_geocode' ) {
		if ($action == 'map_geocode') {
				$action_description = 'Geocode';
		}
	
		print "<tr>
				<td colspan='2' class='even'>
						<p>" . __('Click \'Continue\' to %s on these Device(s)', $action_description) . "</p>
						<p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
				</td>
		</tr>";
	}
	return $save;
}

function map_device_action_array($device_action_array) {
        $device_action_array['map_geocode'] = __('Geocode Device');

        return $device_action_array;
}

?>
