// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// INCLUDE DB CONNECTION STRING 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

include '/usr/local/path/to/your/db/connection.php';   



// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// DELETE ALL RECORDS FROM EXISTING EMPLOYEE DIRECTORY TABLE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$delete_employee_data = "delete from employee_directory ";          
// execute sql statement 
$run_delete_employee_data = pg_query($connection, $delete_employee_data) or die("Error: Unable to delete employee data.");
// get number of rows in result
$delete_employee_data_count = pg_num_rows($run_delete_employee_data);


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// RESET SEQUENCE FOR EXISTING EMPLOYEE DIRECTORY TABLE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$reset_employee_sequence = "alter sequence employee_directory_directory_id_seq restart with 1";         
// execute sql statement 
$run_reset_employee_sequence = pg_query($connection, $reset_employee_sequence) or die("Error: Unable to delete employee data.");



// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// INITIALIZE QUERY STRING 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$insertEmployeeRecord = "
        insert into employee_directory 
        (id, title, first_name, last_name, nickname, department, building, building_room, email, extension, phone, suffix)
        values ";    

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// OPEN CSV FILE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$file_handle = fopen("/usr/local/path/to/your/extracts/EXTRACT_FOR_EMPL_DIR.txt", "r");
$uCount=0;
$iCount=0;
while (!feof($file_handle) ) {

$line_of_text = fgetcsv($file_handle, 1024, "|");


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// CLEAN OUT ANY KNOWN DATA CRAZINESS IF POSSIBLE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

//$line_of_text[20] = (trim($line_of_text[20]) == "***** INVALID REQUEST *****")? "": $line_of_text[20];

// dont insert BLANKs or TITLE ROW    
if ($line_of_text[0] != "" && $line_of_text[0] != "EE_ID")
    {
        $iCount=$iCount+1; // up the counter for inserted records
       
        $insertEmployeeRecord .= " ( '$line_of_text[0]', '".addslashes($line_of_text[9])."', '$line_of_text[2]', '".addslashes($line_of_text[1])."', '$line_of_text[4]', '".addslashes($line_of_text[8])."', '".addslashes($line_of_text[11])."', '".addslashes($line_of_text[12])."', '$line_of_text[19]', '$line_of_text[18]', '$line_of_text[17]', '$line_of_text[6]' ),";
    }

}


// Get the last character of a string.
$last_char = $insertEmployeeRecord[strlen($insertEmployeeRecord)-1]; 
if ($last_char == ",")
    {
        $insertEmployeeRecord = substr_replace($insertEmployeeRecord,"",-1);
    }

    
$insertEmployeeSTACK = $insertEmployeeRecord; 

// deBugg - query stacks
//echo $updateCourseSTACK;
//echo $insertEmployeeSTACK;

    
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// RUN INSERT COURSES QUERY 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

// execute SQL statement 
$run_insertEmployeeRecord = pg_query($connection, $insertEmployeeSTACK) or die("ERROR: Unable to insert course records.");
// get number of rows in result
$insertEmployeeRecord_count = pg_num_rows($run_insertEmployeeRecord);       
// free resources and close connection
pg_free_result($run_insertEmployeeRecord);
        
    
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// UPDATE COURSE LOG WITH THIS TRANSACTION 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    
echo "Inserted: $iCount";
$totalRecords = $uCount+$iCount;

$logRecord = date('M/d/Y')."|".date('H:i:s a')."|inserted:$iCount|total:$totalRecords\n";
//sample log entry: date|time|recordsUpdatedCount|recordsInsertedCount|totalRecordsParsed|CRN#'s of records parsed

// set file path 
$coursesLOGFile = "/usr/local/path/to/your/extracts/transactionLOG.txt";
// open the file for reading and writing
$fw = fopen($coursesLOGFile, "a") or die("ERROR: Unable to find file!");
// write to file
fwrite($fw, $logRecord) or die("ERROR: Unable to write to file!");
// close file
fclose($fw);


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// FREE RESOURCES AND CLOSE CONNECTION 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

pg_close($connection);
fclose($file_handle);

