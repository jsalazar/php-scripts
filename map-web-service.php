// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
//          			   CAMPUS MAP WEB SERVICE
// USAGE: accepts URLs with map data as well as desired FORMAT and records COUNT
// URL FORMAT:  
//		fetch location details by ID - http://www.yourdomain.edu/web/services/map/?id=13598794394939631&format=(xml/json)
//		fetch list of all locations - http://www.yourdomain.edu/web/services/map/?list=(buildings/parking/dorms)&format=(xml/json)&count=50(max is 500, default is 100)
//		search (location_name, building_departments) - http://www.yourdomain.edu/web/services/map/?search=(treadaway/UC/dedicated/registrar)&format=(xml/json)&count=50(max is 500, default is 100)
//  check if all parameters are passed - if not then provide sensible defaults 
// 
// :::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++

	
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// INCLUDE DB CONNECTION STRING 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

include $_SERVER["DOCUMENT_ROOT"].'/functions/connection.php'; 	

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// DETERMINE SERVICE REQUEST OR ESTABLISH DEFAULTS
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$WS_countLimit = isset($_GET['count']) ? intval($_GET['count']) : 100; //check count, grab integer, 100 is the default 
$WS_countLimit = ($WS_countLimit > 100) ? 100 : $WS_countLimit; //check count, enforce max, 100 is the max
$WS_dataFormat = strtolower($_GET['format']) == 'json' ? 'json' : 'xml'; //xml is the default 

// THIS CAN BE USED LATER TO PASS TIME PERIOD FOR NEWS STORIES
if (isset($_GET['search']) && $_GET['search'] != "") {
		$keyWordText = strtolower(trim($_GET['search']));		// need to sanitize this query post
		 
		$directoryWhereClause = "  ( 
		lower(location_name) like '%$keyWordText%' or 
		lower(building_departments) like '%$keyWordText%'
		 ) order by location_name ";
	}
	elseif (isset($_GET['id']) && $_GET['id'] != "") {
			// check that ID is numeric
			if (ctype_digit($_GET['id'])) {
					// grab details for single location 
					$directoryWhereClause = " location_id = '{$_GET['id']}' ";
				}
				else {
					// set default  
					$directoryWhereClause = " location_id = '7' ";
				}
		}
		elseif (isset($_GET['alpha']) && $_GET['alpha'] != "") {
				// check that alpha is single alpha letter
				if (ctype_alpha($_GET['alpha']) && strlen($_GET['alpha']) == 1) {
						$alphaChar = strtoupper($_GET['alpha']);
						// grab ALPHA CAP 1st letter and compare to location name
						$directoryWhereClause = " initcap(location_name) like '$alphaChar%' order by location_name";
						$showAlpha = "y";			
					}
					else {	// set default
						$directoryWhereClause = " initcap(location_name) like 'A%' order by location_name";
						$showAlpha = "y";
					}
			}
			elseif (isset($_GET['list']) && $_GET['list'] != "") {
					switch ($_GET['list']) {
							case "buildings": $directoryWhereClause = " location_type = 'building' order by location_name "; break;
							case "dorms": $directoryWhereClause = " location_type = 'dorm' order by location_name "; break;
							case "parking": $directoryWhereClause = " location_type = 'parking' order by location_name "; break;
							default: $directoryWhereClause = " location_type = 'building' order by location_name "; break;
						}
					$showAlpha = "y";
				}
				else {
					// set sensible default values - show first 100 records by ALPHA
					// old default
					//$directoryWhereClause = " initcap(last_name) LIKE 'A%' order by last_name ";
					// new default - changed per request from BLACKBOARD to pull all records with all information
					$directoryWhereClause = " location_name != '' order by location_name ";
					// override limit default
					$WS_countLimit = 100; // should be more than # of records in DB
					$showAlpha = "y";
				}


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// RUN QUERY TO GRAB CALENDAR EVENTS
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$grabWSMapInformation = "SELECT * FROM map_data where $directoryWhereClause LIMIT $WS_countLimit";
$runGrabWSMapInformation = pg_query($connection, $grabWSMapInformation) or die("ERROR: Unable to select WS Map data.");
$grabWSMapInformationCount = pg_num_rows($runGrabWSMapInformation);	

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// PREP DATA FOR XML TAGS AND GENERATE <ARTICLES> TAG SCHEMA
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

// INITIALIZE XML STRING
$WS_XML = '<?xml version="1.0" encoding="utf-8"?>';

if (isset($_GET['search']) || $showAlpha == "y") {$WS_XML .= '<locations>';}

 
// FIX STRINGS FOR XML USE
$search = array('&','<','>','"','\'','’'); 
$replace = array('&amp;','&lt;','&gt;','&quot;','&apos;','&apos;');

//loop through and create array
for ($i = 0; $i < $grabWSMapInformationCount; $i++) {

		$row = pg_fetch_array($runGrabWSMapInformation, $i, PGSQL_ASSOC); 
		
		$WS_location_id = trim($row["location_id"]);
		$WS_location_name = trim($row["location_name"]);
		$WS_location_name = str_replace($search,$replace,$WS_location_name);
		$WS_location_latitude = trim($row["location_latitude"]);
		$WS_location_longtitude = trim($row["location_longtitude"]);
		$WS_location_image_url = trim($row["location_image_url"]);
		$WS_location_description = trim($row["location_description"]);
		$WS_building_departments = trim($row["building_departments"]);
		$WS_location_type = trim($row["location_type"]);
		$WS_building_code = trim($row["building_code"]);
		
		$b_department =explode("|", $WS_building_departments);
		//echo $b_department[0]; // piece1

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// BUILD XML SCHEMA 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
		
		if (isset($_GET['search']) || $showAlpha == "y") {
				
				if (isset($_GET['simple']) == "y") {
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
					// BUILD JSON ARRAY  
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
							
					$WS_JSON[$i] = array("location_name"=>"$WS_location_name");			
					
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
					// BUILD XML TREE  
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++	
					$WS_XML .= '
					<location>
						<location_name>'.$WS_location_name.'</location_name>
					</location>';
				
				}
				else {
					
				// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				// BUILD JSON ARRAY  
				// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
						
				$WS_JSON[$i] = array(
				"location_id"=>"$WS_location_id", 
				"location_name"=>"$WS_location_name",
				"geocode"=>array ("latitude" => "$WS_location_latitude", "longtitude" => "$WS_location_longtitude"),
				"location_image_url"=>"$WS_location_image_url",
				"location_code"=>"$WS_building_code",
				"location_description"=>"$WS_location_description"
				);			
				
				// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
				// BUILD XML TREE  
				// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++	
				$WS_XML .= '
				<location>
					<location_id>'.$WS_location_id.'</location_id>
					<location_name>'.$WS_location_name.'</location_name>
					<geocode>
						<latitude>'.$WS_location_latitude.'</latitude>
						<longtitude>'.$WS_location_longtitude.'</longtitude>
					</geocode>
					<location_image_url>'.$WS_location_image_url.'</location_image_url>
					<location_code>'.$WS_building_code.'</location_code>
					<location_description>'.$WS_location_description.'</location_description>
					<organizations>';
					foreach ($b_department as $key=>$building_department) {						
							$WS_XML .= '<organization>'.$building_department.'</organization>';
							// add multiple organizations to JSON organizations array
							$WS_JSON[$i]["organizations"][]= array("organization"=>"$building_department");
						}
				$WS_XML .= '
					</organizations>
				</location>';
				
				
				}
			}
			elseif (isset($_GET['id'])) {	
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
					// BUILD JSON ARRAY  
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
							
					$WS_JSON[$i] = array(
					"location_id"=>"$WS_location_id", 
					"location_name"=>"$WS_location_name",
					"geocode"=>array ("latitude" => "$WS_location_latitude", "longtitude" => "$WS_location_longtitude"),
					"location_image_url"=>"$WS_location_image_url",
					"location_code"=>"$WS_building_code",
					"location_description"=>"$WS_location_description"
					);					
					
					
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
					// BUILD XML TREE  
					// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++	
	
					$WS_XML .= '
					<location_detail>
						<location_id>'.$WS_location_id.'</location_id>
						<location_name>'.$WS_location_name.'</location_name>
						<geocode>
							<latitude>'.$WS_location_latitude.'</latitude>
							<longtitude>'.$WS_location_longtitude.'</longtitude>
						</geocode>
						<location_image_url>'.$WS_location_image_url.'</location_image_url>
						<location_code>'.$WS_building_code.'</location_code>
						<location_description>'.$WS_location_description.'</location_description>
						<organizations>';
						foreach ($b_department as $key=>$building_department) {						
								$WS_XML .= '<organization>'.$building_department.'</organization>';
								
								// add multiple organizations to JSON organizations array
								$WS_JSON[$i]["organizations"][]= array("organization"=>"$building_department");
							}
					$WS_XML .= '
						</organizations>
					</location_detail>';
				}
		
		
	}
if (isset($_GET['search']) || $showAlpha == "y") {$WS_XML .= '</locations>';}	

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// SEND CONTENT HEADERS & DATA 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

if ($WS_dataFormat == "json") {
    if (isset($_GET['search']) || $showAlpha == "y") {		
header('Content-type: application/json'); 
echo json_encode(array("locations"=>array("location"=>$WS_JSON)));
}
else {
header('Content-type: application/json'); 
echo json_encode(array("location_detail"=>$WS_JSON));
}
	}
	else {
header('Content-type: text/xml'); 
echo $WS_XML;
	}

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// FREE RESOURCES AND CLOSE CONNECTION 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
pg_free_result($runGrabWSMapInformation);
pg_close($connection);
