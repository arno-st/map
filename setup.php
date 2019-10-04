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
	api_plugin_register_hook('map', 'device_remove', 'map_device_remove', 'setup.php');
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
//	db_execute("DROP TABLE IF EXISTS `plugin_map_coordinate`;");
//	db_execute("DROP TABLE IF EXISTS `plugin_map_host`;");
}

function plugin_map_check_config () {
	$ret = true;
	// Here we will check to ensure everything is configured
	map_check_upgrade ();
	$ret = map_check_dependencies();
	
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
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

function map_check_dependencies() {
	global $plugins, $config;
	if ((!in_array('settings', $plugins)) &&
		(db_fetch_cell("SELECT directory FROM plugin_config WHERE directory='settings' AND status=1") == "")) {
		return false;
	}

	if (!function_exists('settings_version')) {
		if (file_exists($config['base_path'] . '/plugins/settings/setup.php')) {
			include_once($config['base_path'] . '/plugins/settings/setup.php');
			if (!function_exists('settings_version')) {
				return false;
			}
		}
	}
	$v = settings_version();
	if (!isset($v['version']) || $v['version'] < 0.3) {
		return false;
	}

	return true;
}

function plugin_map_version () {
	return array(
		'name'     => 'Map',
		'version'  => '0.37',
		'longname' => 'Map Viewer',
		'author'   => 'Arno Streuli',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'astreuli@gmail.com',
		'url'      => 'http://versions.cactiusers.org/'
	);
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
//			'array' => array("0" => "GoogleMap"),
			"default" => "20"
			),
		"map_center" => array(
			"friendly_name" => "Map center",
			"description" => "Address to where whe should center the map (country;city;street number).",
			"method" => "textbox",
			"max_length" => 120,
			"default" => ""
			),
		"map_api_key" => array(
			"friendly_name" => "API Key",
			"description" => "This is key for google map usage.",
			"method" => "textbox",
			"max_length" => 80,
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
	
	$location = read_config_option('map_center');
	if( $location != '') {
		
		$location = str_replace( ",", ";", $location );
		$address = explode( ';', $location); // Suisse;Lausanne;Chemin de Pierre-de-Plan 4

		$maptools = read_config_option('map_tools');
		if( $maptools == '0' ) {
			$gpslocation = GoogleGeocode($address);
		} else if( $maptools == '1' ) {
			$gpslocation = OpenStreetGeocode($address);
		}
	 	if( $gpslocation != false ) {
			set_config_option('map_center_gps_lati', $gpslocation[0]);
			set_config_option('map_center_gps_longi', $gpslocation[1]);
		}
	} else {
		set_config_option('map_center_gps_longi', '0.0');
		set_config_option('map_center_gps_lati', '51.48257');
	}

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

function map_utilities_action ($action) {
	// get device list,  where snmp is active
	$dbquery = db_fetch_assoc("SELECT id, hostname, snmp_community, 
	snmp_version, snmp_username, snmp_password, snmp_port, snmp_timeout, disabled, availability_method, 
	ping_method, ping_port, ping_timeout, ping_retries, snmp_auth_protocol, snmp_priv_passphrase, 
	snmp_priv_protocol, snmp_context FROM host WHERE snmp_version > '0' ORDER BY id");

	if ( (sizeof($dbquery) > 0) && $action == 'map_rebuild' || $action == 'coordinate_rebuild'){
		if ($action == 'map_rebuild') {
		// rebuild the map address table
			db_execute("TRUNCATE TABLE `plugin_map_coordinate`;");
			db_execute("TRUNCATE TABLE `plugin_map_host`;");

			foreach ($dbquery as $host) {
				// snmp_get syslocation, and geocodit
				$snmp_location = query_location ( $host );

				// geocod it, many Google query but the address is the geocoded one
				$gpslocation = geocod_address ( $snmp_location );
				if( $gpslocation == false) 
					continue;

				// check if this adress is present into the plugin_map_coordinate
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				if( $address_id == 0) // record does not exist
				{
					// save to new table with id and location
					$ret = db_execute("INSERT INTO plugin_map_coordinate (address, lat, lon) VALUES ('"
					. sql_sanitize($gpslocation[2]) ."','"
					. sql_sanitize($gpslocation[0]) . "', '"
					. sql_sanitize($gpslocation[1]) . "')");
				} 

				   // and add  host to plugin_map_host_table
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				db_execute("REPLACE INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");

				map_log("lati: ". $gpslocation[0]." longi: ".$gpslocation[1] );
			}
		} else if ($action == 'coordinate_rebuild') {
			// Empty the table first
			db_execute("TRUNCATE TABLE `plugin_map_host`;");

			foreach ($dbquery as $host) {
				// snmp_get syslocation
				$snmp_location = query_location ( $host );
				// geocod it, many Google query but the address is the geocoded one
				$gpslocation = geocod_address( $snmp_location );
				if( $gpslocation == false) 
					continue;

				// check if this adress is present into the plugin_map_coordinate
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				if( $address_id == 0) // record does not exist
				{
					// save to new table with id and location
					$ret = db_execute("INSERT INTO plugin_map_coordinate (address, lat, lon) VALUES ('"
					. sql_sanitize($gpslocation[2]) ."','"
					. sql_sanitize($gpslocation[0]) . "', '"
					. sql_sanitize($gpslocation[1]) . "')");
				} 

				// and add  host to plugin_map_host_table
				$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
				db_execute("DELETE FROM plugin_map_host where host_id=" .$host['id']);
				db_execute("INSERT INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");


				map_log("host: " .$host['id']. " address: " .$address_id );
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

map_log("Query location device: ".$host['hostname']." ".$snmp_location );

	return $snmp_location;
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
		map_log("Snmp location error: ".var_dump($address)."\n" );
		$gpslocation = false;
	}
	return $gpslocation;
}

function map_utilities_list () {
	global $colors;

	html_header(array("Map Plugin"), 4);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=map_rebuild'>Rebuild mapping table</a>
		</td>
		<td class="textArea">
			This will rebuild the mapping coordinate table from the device list, clear of the location table.
		</td>
	</tr>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=coordinate_rebuild'>Rebuild host link to coordinate table</a>
		</td>
		<td class="textArea">
			This will rebuild the link from device to the coordinate (after a backup/restore of the coordinate table).
		</td>
	</tr>
	<?php
}

function map_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'address', 'type' => 'varchar(150)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'lat', 'type' => 'float(10,6)', 'NULL' => false , 'default' => '0');
	$data['columns'][] = array('name' => 'lon', 'type' => 'float(10,6)', 'NULL' => false, 'default' => '0');
	$data['type'] = 'MyISAM';
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'id', 'columns' => 'id');
	$data['comment'] = 'Plugin map - Table of map hosts coordinate';
	api_plugin_db_table_create('map', 'plugin_map_coordinate', $data);

	$data = array();
	$data['columns'][] = array('name' => 'host_id', 'type' => 'mediumint(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'address_id', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['type'] = 'MyISAM';
	$data['primary'] = "host_id`,`address_id";
	$data['comment'] = 'Plugin map - Table of GPS coordinate';
	api_plugin_db_table_create('map', 'plugin_map_host', $data);
	
}

function map_api_device_new( $host ) {
	// device is saved, take the snmplocation to check with database
	$snmp_location = query_location ( $host );

	// geocod it, many Google query but the address is the geocoded one
	$gpslocation = geocod_address ( $snmp_location );
	if( $gpslocation == false) 
		return $host;

	// check if this adress is present into the plugin_map_coordinate
	$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
	if( $address_id == 0) // record does not exist
	{
		// save to new table with id and location
		$ret = db_execute("INSERT INTO plugin_map_coordinate (address, lat, lon) VALUES ('"
		. sql_sanitize($gpslocation[2]) ."','"
		. sql_sanitize($gpslocation[0]) . "', '"
		. sql_sanitize($gpslocation[1]) . "')");
	} 

   // and add  host to plugin_map_host_table
	$address_id = db_fetch_cell("SELECT id FROM plugin_map_coordinate WHERE address='".sql_sanitize($gpslocation[2])."'" );
	db_execute("DELETE FROM plugin_map_host where host_id=" .$host['id']);
	db_execute("INSERT INTO plugin_map_host (host_id, address_id) VALUES (" .$host['id']. "," .$address_id. ")");
	
	return $host;
}

function map_device_remove ($hosts_id) {
	//array(1) { [0]=> string(4) "1921" } device remove : 
	if( sizeof($hosts_id) ) {
		foreach( $hosts_id as $host_id) {
			map_log( "remove host: " . $host_id );
			db_execute("DELETE FROM plugin_map_host WHERE host_id=".$host_id );
		}
	}	

	return $hosts_id;
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
	
    map_log("GoogleMap Reverse URL: ".$url );
	
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
		map_log("Google ReverseGeocoding error: ".$resp['status'] );
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

	//https://maps.googleapis.com/maps/api/geocode/json?address=4+chemin+pierre+de+plan,+Lausanne,+Suisse&key=AIzaSyAr0rad39hJtQLiRoPqsTstFW9u8kl6PYA
    // url encode the address
    // google map geocode api url
	if( $mapapikey != null)
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}&key={$mapapikey}";
	else 
		$url = "https://maps.google.com/maps/api/geocode/json?address={$address}";

    map_log("GoogleMap URL: ".$url );

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
			$location['postal_code'] = $resp['address']['postcode'];
			$location['country'] = $resp['address']['country'];
			$location['state'] = $resp['address']['state'];
			$location['address2'] =  $resp['address']['county'];
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
	
	$address = $locations[2]." ".$locations[1]." ".$locations[0];
	$address = str_replace( ' ', '%20', $address);

	$url = "https://nominatim.openstreetmap.org/search/". $address. "?format=jsonv2&addressdetails=1&limit=1";

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

        if( json_last_error() === JSON_ERROR_NONE ) {// get the important data
			return FormalizedAddress($resp[0]);
		} else {
			map_log("OpenStreetmap json error: ".json_last_error()." loca: ".$url ."\n" );
			return false;
		}
		
	} else {
		map_log("OpenStreetmap Geocoding error: ".json_last_error()."loca: ".$url ."\n" );
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

        if( json_last_error() === JSON_ERROR_NONE ) {// get the important data
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

function map_device_action_execute($action) {
	global $config;

	if ($action != 'map_geocode' ) {
		return $action;
	}

	$selected_items = unserialize(stripslashes($_POST["selected_items"]));

	if ($action == 'map_geocode' ) {
		for ($i = 0; ($i < count($selected_items)); $i++) {
		/* ================= input validation ================= */
		input_validate_input_number($selected_items[$i]);
		/* ==================================================== */
			$dbquery = db_fetch_assoc("SELECT * FROM host WHERE id=".$selected_items[$i]);
map_log("Rebuild Mapping: ".$selected_items[$i]." - ".$dbquery[0]['description']."\n");
			map_api_device_new($dbquery[0]);
		}
	}

	return $action;
}

function map_device_action_prepare($save) {
	global $colors, $host_list;

    $action = $save['drp_action'];

	if ($action != 'map_geocode' ) {
		return $save;
	}

	if ($action == 'map_geocode' ) {
		if ($action == 'map_geocode') {
				$action_description = 'Geocode';
		}

		print "<tr>
			<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
				<p>To ". $action_description ." Geocode this device \"Continue\" button below.</p>
				<p>" . $save['host_list'] . "</p>
			</td>
		</tr>";
	}

}

function map_device_action_array($device_action_array) {
	$device_action_array['map_geocode'] = 'Geocode Device';

	return $device_action_array;
}

?>
