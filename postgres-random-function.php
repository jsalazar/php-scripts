
include $_SERVER["DOCUMENT_ROOT"].'/functions/connection.php'; 	

// grab info for this headline
$grabRandomExpert = "SELECT * FROM product_experts ORDER BY RANDOM() LIMIT 1";

$runGrabRandomExpert = pg_query($connection, $grabRandomExpert) or die("ERROR: Unable to select an expert record.");
$grabRandomExpertCount = pg_num_rows($runGrabRandomExpert);	

//loop through and create array
for ($i = 0; $i < $grabRandomExpertCount; $i++) { 
		$row = pg_fetch_array($runGrabRandomExpert, $i, PGSQL_ASSOC); 
		$ID = trim($row["application_id"]);
		$first_name = trim($row["first_name"]);
		$last_name = trim($row["last_name"]);
		$title = trim($row["title"]);
		$bio_summary = trim($row["bio_summary"]);
		
		$bio_summary = substr($bio_summary, 0, 200)." ...";
		
		echo '<div>'.$first_name.' '.$last_name.'</div> <div>'.$title.'</div>'.$bio_summary;

	}
		
