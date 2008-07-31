<?php

class OutputFormat {
	var $source_dir;
	
	function OutputFormat() {
		// Find out where this script lives.
		$this->source_dir = dirname(__FILE__);	
	}

	function arrayToSerial($php_array){
		return(serialize($php_array));
	}
	
	function serialToArray($serialized_array) {
		return(unserialize($serialized_array));
	}
	
	function arrayToJSON($php_array, $callback = "") {
		$json_string = "";

		// Use the PECL JSON function if it is available,
		// it is much faster that Services_JSON from PEAR.
		if(function_exists("json_encode")) {
			$json_string = json_encode($php_array);
		}
		else {
			// Pull in Services_JSON if it hasn't been already.
			if(!class_exists("Services_JSON")) {
				require("{$this->source_dir}/JSON.php");
			}
			
			$json = new Services_JSON();
			$json_string = $json->encode($php_array);
		}

		// Wrap the JSON text in a callback function if
		// one was provided.
		if(!empty($callback)) {
			$json_string = "{$callback}( {$json_string} );";
		}

		return($json_string);
	}
	
	function jsonToArray($json_array){
		// Use the PECL JSON function if it is available.
		if(function_exists("json_decode")) {
			return(json_decode($json_array));
		}
		
		// Pull in Services_JSON if it hasn't been already.
		if(!class_exists("Services_JSON")) {
			require("{$this->source_dir}/JSON.php");
		}
		
		$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		return($json->decode($json_array));
	}
	
	function arrayToXML($php_array) {
		// Pull in XML_serialize function if it isn't available.
		if(!function_exists("XML_serialize")) {
			require("{$this->source_dir}/xml.php");
		}
		
		return(XML_serialize($php_array));
	}
	
	function xmlToArray($xml_array) {
		// Pull in XML_unserialize function if it isn't available.
		if(!function_exists("XML_unserialize")) {
			require("{$this->source_dir}/xml.php");
		}
		
		return(XML_unserialize($xml_array));
	}
}
?>