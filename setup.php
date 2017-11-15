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

function plugin_map_install () {
	api_plugin_register_hook('map', 'top_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'top_graph_header_tabs', 'map_show_tab', 'setup.php');
	api_plugin_register_hook('map', 'draw_navigation_text', 'map_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('map', 'config_settings', 'map_config_settings', 'setup.php'); // personl settings info
	api_plugin_register_hook('map', 'api_device_new', 'map_api_device_new', 'setup.php');
	api_plugin_register_hook('map', 'utilities_action', 'map_utilities_action', 'setup.php');
	api_plugin_register_hook('map', 'utilities_list', 'map_utilities_list', 'setup.php');

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
	$old = read_config_option('plugin_map_version');
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
}

function plugin_map_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/map/INFO', true);
	return $info['info'];
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
//			'array' => array("0" => "GoogleMap", "1" => "OpenStreetMap"),
			'array' => array("0" => "GoogleMap"),
			"default" => "20"
			),
		"map_api_key" => array(
			"friendly_name" => "API Key",
			"description" => "This is key for google map usage.",
			"method" => "textbox",
			"max_length" => 80,
			"default" => ""
			),
		"map_center" => array(
			"friendly_name" => "Map center",
			"description" => "Address to where whe should center the map (number,street,city,country).",
			"method" => "textbox",
			"max_length" => 120,
			"default" => ""
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

	if( get_request_var('action')=='save') {
		$location = read_config_option('map_center');
		$maptools = read_config_option('map_tools');
		$location = str_replace(' ', '+', $location );
		if( $maptools == '0' ) {
			$gpslocation = GoogleGeocode($location);
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetGeocode($location);
		}
	 	set_config_option('map_center_gps_lati', $gpslocation[0]);
	 	set_config_option('map_center_gps_longi', $gpslocation[1]);
	}

	// https://maps.googleapis.com/maps/api/geocode/json?latlng=46.51157,6.62179&amp;key=AIzaSyCpw0hNO2ZzIxKb9cTyrSPEN3ADvUTc5Xc&amp
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
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=map_rebuild'>Rebuild sites table</a>
		</td>
		<td class="textArea">
			This will rebuild the sites table from the device list.
		</td>
	</tr>
	<?php
}
function map_utilities_action ($action) {
	// get device list,  where snmp is active
	$dbquery = db_fetch_assoc("SELECT id, hostname, snmp_community, 
	snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
	ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
	snmp_priv_protocol, snmp_context FROM host WHERE snmp_version > '0' ORDER BY id");
	if ( (sizeof($dbquery) > 0) && $action == 'map_rebuild' ){
		if ($action == 'map_rebuild') {
		// Upgrade the map address table
			foreach ($dbquery as $host) {
				// device is saved, take the snmplocation to check with database
				$snmp_location = query_location ( $host );
map_log("host: " . $host['hostname'] ."\n");
	// geocod it, many Google query but the address is the geocoded one
	/* array format:
                    $lati, 
                    $longi, 
                    $formatted_address,
					$location (array of full detail)
*/
				$gpslocation = geocod_address ( $snmp_location );

				if( $gpslocation == false) 
					continue;

				// check if this adress is present into the plugin_map_coordinate
				$address_id = db_fetch_cell("SELECT id FROM sites WHERE name='".mysql_real_escape_string($gpslocation[2])."'" );
				if( $address_id == 0) // record does not exist
				{
					// save to new table with id and location
					$ret = db_execute("INSERT INTO sites (name, address1, city, state, postal_code, country, address2, latitude, longitude) VALUES ('"
					. mysql_real_escape_string($gpslocation[2]) ."','"
					. mysql_real_escape_string($gpslocation[3]['address1'])." ".$gpslocation[3]['street_number']."','"
					. $gpslocation[3]['city']."','"
					. $gpslocation[3]['state']."','"
					. $gpslocation[3]['postal_code']."','"
					. $gpslocation[3]['country']."','"
					. mysql_real_escape_string($gpslocation[3]['address2'])."','"
					. $gpslocation[0] . "', '"
					. $gpslocation[1] . "')");

					// and add  host to host_table
					$address_id = db_fetch_cell("SELECT id FROM sites WHERE name='".mysql_real_escape_string($gpslocation[2])."'" );
					db_execute("UPDATE host SET site_id = ".$address_id. " where id=".$host['id'] );
				}

			}
		}
		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	} 
	return $action;
}

// query the snmp location from the host, host is an array with:
/* id, hostname, snmp_community, 
		snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
		ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
		snmp_priv_protocol, snmp_context */
function query_location( $host ) {
	global $config;
	$snmpsyslocation		 = ".1.3.6.1.2.1.1.6.0"; // system location
	include_once($config["library_path"] . '/snmp.php');

	$snmp_location = cacti_snmp_get( $host['hostname'], $host['snmp_community'], $snmpsyslocation, 
		$host['snmp_version'], $host['snmp_username'], $host['snmp_password'], 
		$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], 
		$host['snmp_context'] ); 

map_log("\n\ndevice: ".$host['hostname']." ".$snmp_location );

	return $snmp_location;
}

// return the full address, lat, lon
function geocod_address( $snmp_location ) {
	$maptools = read_config_option('map_tools');

	// parse google map for geocoding
	// location format: Country;City;Street_Building;Floor;Room;Rack;RU;Lat;lon
	$snmp_location = str_replace( ",", ";", $snmp_location );
	$address = explode( ';', $snmp_location ); // Suisse;Lausanne;Chemin de Pierre-de-Plan 4;-1;Local Telecom;;;46.54;6.56
	if( (count($address) <= 7) && count($address) > 3 ) {
		$location = $address[2]. "," .$address[1]. "," . $address[0];
		$location = str_replace(' ', '+', $location );
		$gpslocation = array();
		if( $maptools == '0' ) {
			$gpslocation = GoogleGeocode($location);
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetGeocode($location);
		}
		if($gpslocation != false ){
			$gpslocation[2] = str_replace ("'", " ", $gpslocation[2]); // replace ' by space
		} 
	} else if( count($address) == 9 ) { 
		// gps coordinate
		$gpslocation = GoogleReverGeocode( $address[7], $address[8] );
	} else {
		map_log("Snmp location error " );
		$gpslocation = false;
	}
	return $gpslocation;
}

function map_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

}

function map_api_device_new( $host ) {
/* id, hostname, snmp_community, 
		snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
		ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
		snmp_priv_protocol, snmp_context */
	// if snmp is not active return
	// or if site_id is valid return
	if( $host['availability_method'] == 3 || $host['site_id'] != 0 ) {
		return $host;
	}
	
	// device is saved, take the snmplocation to check with database
	$snmp_location = query_location ( $host );

	// geocod it, many Google query but the address is the geocoded one
	/* array format:
                    $lati, 
                    $longi, 
                    $formatted_address,
					$location (array of full detail)
*/
	$gpslocation = geocod_address ( $snmp_location );

	if( $gpslocation == false) 
		return $host;

	// check if this adress is present into the plugin_map_coordinate
	$address_id = db_fetch_cell("SELECT id FROM sites WHERE name='".$gpslocation[2]."'" );
	if( $address_id == 0) // record does not exist
	{
		// save to new table with id and location
		$ret = db_execute("INSERT INTO sites (name, address1, city, state, postal_code, country, address2, latitude, longitude) VALUES ('"
		. mysql_real_escape_string($gpslocation[2]) ."','"
		. mysql_real_escape_string($gpslocation[3]['address1'])." ".$gpslocation[3]['street_number']."','"
		. $gpslocation[3]['city']."','"
		. $gpslocation[3]['state']."','"
		. $gpslocation[3]['postal_code']."','"
		. $gpslocation[3]['country']."','"
		. mysql_real_escape_string($gpslocation[3]['address2'])."','"
		. $gpslocation[0] . "', '"
		. $gpslocation[1] . "')");
	} 

   // and add  host to host_table
	$address_id = db_fetch_cell("SELECT id FROM sites WHERE name='".mysql_real_escape_string($gpslocation[2])."'" );
	if ( !empty($address_id) ) {
		db_execute("UPDATE host SET site_id = ".$address_id. " where id=".$host['id'] );
	}
	
	return $host;
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
        // get the important data
        $lati = $resp['results'][0]['geometry']['location']['lat'];
        $longi = $resp['results'][0]['geometry']['location']['lng'];
        $formatted_address = $resp['results'][0]['formatted_address'];

		// location as array
		$location = array();
		$location = formatedJson( $resp['results'][0] );
 
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

function GoogleGeocode($address){
	global $config;
	$mapapikey = read_config_option('map_api_key');
	//https://maps.googleapis.com/maps/api/geocode/json?address=4+chemin+pierre+de+plan,+Lausanne,+Suisse&key=AIzaSyAr0rad39hJtQLiRoPqsTstFW9u8kl6PYA
    // url encode the address
    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}&key={$mapapikey}";
	else 
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}";
 
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true, 512 );

    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
 
        // get the important data
        $lati = $resp['results'][0]['geometry']['location']['lat'];
        $longi = $resp['results'][0]['geometry']['location']['lng'];
        $formatted_address = $resp['results'][0]['formatted_address'];

		// location as array
		$location = formatedJson( $resp['results'][0] );
 
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

function OpenStreetGeocode($address){
    // url encode the address
    $address = urlencode($address);
map_log("Open Street Address: ".$address );

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
				map_log("json error: ".$component['types']." ".$component['long_name']."\n");
			}

		}
	return $location;
}
?>
