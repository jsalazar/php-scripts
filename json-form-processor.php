<?php
/*
ABOUT 
- formBot (fb-processor.php) is built for use by single page forms, one-time submission forms
- formBot builds web forms on the fly using a JSON file as a config
- one JSON file is used per app (form) and it contains all the necessary information used to generate: html form code, javascript code, error checking code, database calls
- how formBot treats form submission: it only inserts the data only if all error checking passes, if any fails occur, we send them back to complete the form
*/

// initialize some var for use below
$date_now = mktime();
$save_date = date("D M j, Y g:i:s a", mktime()); 
$errorz = array();


// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
//  HERE WE RUN A GENERIC ERROR CHECK OF REQUIRED FIELDS AND DYNAMICALLY
//  BUILD AN INSERT QUERY STATEMENT FOR ALL DATA SUBMITTED
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#


// grab all fields from AJAX submission - should come across as: $jfirst_name, etc. Prefixed with a "j" for javascript
foreach($_POST as $key => $value) {
        $fieldName = $key;
        // use "variable variables [$$]" to dynamically create variable names from $key values
        $$fieldName = $value;
    }   

// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
//  SECURITY AND INTEGRITY CHECK JSON FILE PATH

// First strip $appPath of :   ../     ./     ../   etc - value should be a simple string with alpha values
// Check if directory is legit
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

$badStrings = array(".", "/");
$jappPath = str_replace($badStrings, "", $jappPath);
 
 if(is_dir($_SERVER["DOCUMENT_ROOT"].'/global/forms/'.$jappPath)) {
    //include the form json file
    include $_SERVER["DOCUMENT_ROOT"].'/global/forms/'.$jappPath.'/json.php';   
 }
 else {
     $errorz[] = "Error: It appears you are trying to access a directory you don't have access to!";
 }

clearstatcache();

// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
// PREPARE JSON DATA FOR USE 
// REFERENCE $jsonData from successful include above
// Seed the FIELDS object for looping 
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

$jsonData = json_decode($JSON);
//seed the FIELD object for looping 
$jsonFields = $jsonData->fields;

// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
// FORMBOT PLUGIN BEGIN
// FORM BOT PRE PROCESSOR
// This file is good for:
// custom error checking specific to a particular app (an app with multiple field dependencies (if student - then fill this out...))
// checking checkbox fields (if isset, != 'undefined', etc)
// enhance error checking on particular fields that ALSO require a server-side check (word count, character count, string or number format, verify data for other than blank)
// manipulating hidden form field data
// manipulating date and time fields
// for checking non required fields that BECOME required if the user makes a particular selection

// FILE CAN ALSO BE BLANK IF YOUR APP DOES NOT NEED IT
// ONLY CHECKS DEPENDENCIES AND REQUIRED FIELDS FOR THE FORM CURRENTLY BEING SUBMITTED
include $_SERVER["DOCUMENT_ROOT"].'/global/forms/'.$jsonData->meta->appPath.'/formBotPreProcessor.php';   
// FORMBOT PLUGIN END
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#



// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
// INCLUDE DB CONNECTION STRING 
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
include $_SERVER["DOCUMENT_ROOT"].'/global/dbConnection.php';       

// SET DATABASE AND TABLE NAMES
$APP_databaseName = $jsonData->meta->dbName;
$APP_table = $jsonData->meta->table;

// ESTABLISH CONNECTION TO DATABASE
mysql_select_db($APP_databaseName, $APP_connection);


// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
// INITIALIZE QUERY STATEMENT
// IF ALL REQUIRED FIELDS FOUND TO BE ERROR FREE - THEN THE FORM IS SUBMITTED ALONG WITH OTHER NON-REQUIRED FIELDS SUBMITTED
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

// HOLDS FIELD NAMES
$APP_insertQuery = "
insert into $APP_table
(save_date ";

// HOLDS FIELD VALUES - we collect both names and values at the same time in a single loop
$APP_insertQueryValues = ") values ('$save_date'";

// HOLDS ALL RECEIPT TEXT FOR CONFIRMATION E-MAILS
$receiptContents = "";


// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
//  LOOP THRU FIELDS OBJECT AND PROCESS EACH FORM FIELD TYPE
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

for ($i=0; $i < count($jsonFields); $i++) { 

    // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
    //  DETERMINE FIELD TYPE AND GENERATE FIELD CODE APPROPRIATELY
    // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

    switch ($jsonFields[$i]->type) {

             case 'text':
                    // set database value, use same naming convention used in web form code to grab field values from database 
                    $fieldName = 'j'.$jsonFields[$i]->name;
                    
                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldName == "") {  
                                $errorz[] = $jsonFields[$i]->errorMessage;
                            }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                    $APP_insertQueryValues .= ", '".$$fieldName."'";

                    if ($$fieldName != "") { // if field is not blank, add to reciept
                          $receiptContents .= "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";
                      }
                    break;

            case 'heading':
                    $receiptContents .= "<div><strong>".$jsonFields[$i]->headingName."</strong></div><br>";
                    break;

            case 'html':
                    $receiptContents .= "<div>".$jsonFields[$i]->value."</div><br>";
                    break;
                    
            case 'notice':
                    $receiptContents .= "<div>".$jsonFields[$i]->value."</div><br>";
                    break;
                    
            case 'textarea':
                    // set database value, use same naming convention used in web form code to grab field values from database 
                    $fieldName = 'j'.$jsonFields[$i]->name;
                    // reduce some simple exploits
                    // need to also add preg_match for sql exploits and remove some special chars [!@#$%^&*()_+=-~`:]
                    $$fieldName = strip_tags($$fieldName, '<p><a><br><em><strong><object><embed>');

                    if (strtolower($jsonFields[$i]->required) == "y") { // is this field required?
                        if ($$fieldName == "") { // if so, check if it is blank
                                $errorz[] = $jsonFields[$i]->errorMessage; // record an error if it blank, AND then continue with update SQL statement
                            }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                    $APP_insertQueryValues .= ", '".$$fieldName."'";

                    if ($$fieldName != "") { // if field is not blank, add to reciept
                          $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";
                      }

                    break;
                    
            case 'selectFlex':
                    // set database value, use same naming convention used in web form code to grab field values from database 
                    $fieldName = 'j'.$jsonFields[$i]->name;
                    
                    if (strtolower($jsonFields[$i]->required) == "y") { // is this field required?
                        if ($$fieldName == "") { // if so, check if it is blank
                            $errorz[] = $jsonFields[$i]->errorMessage; // record an error if it blank, AND then continue with update SQL statement
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                    $APP_insertQueryValues .= ", '".$$fieldName."'";

                    if ($$fieldName != "" && $$fieldName != "null") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";
                    }                    
                    break;  

            // Each checkbox should have a unique name and be stored in its own DB field.                    
            // This should be the standard for handling data collection for checkboxes.  
            case 'checkboxFlex':
                        // initialize checkbox count to see if any were checked
                        $cbCount = 0;

                        $checkOptions = $jsonFields[$i]->options;
                        // begin receipt here so we dont repeat the lable for a block of elements
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>";

                            for ($ii=0; $ii < count($checkOptions); $ii++) { 

                                $fieldName = 'j'.$checkOptions[$ii]->name;

                                if (isset($$fieldName) && $$fieldName != "undefined") {
                                    $APP_insertQuery .= ", ".$checkOptions[$ii]->name;
                                    $APP_insertQueryValues .= ", '".$$fieldName."'";                                                                        

                                    $receiptContents .= $checkOptions[$ii]->text."<br><br>"; // changed display value to 'text' instead of 'value' to avoid cryptic looking email receipts
                                    // tell formBot that a checkbox was checked
                                    $cbCount = $cbCount +1;
                                }
                                else {
                                    // keep building SQL statement
                                    // need to blank out the value or provide the default so previous selections are changed
                                    $APP_insertQuery .= ", ".$checkOptions[$ii]->name;
                                    $APP_insertQueryValues .= ", ''";
                                }
                            }
                            if (strtolower($jsonFields[$i]->required) == "y") {
                                // if none of the checkboxes were checked and at least one was required, give then an error message
                                if ($cbCount == 0) {
                                    // EXECUTIVE DECISION: LET CUSTOM CODE IN formBotPreProcessor.php errorCheck ALL checkbox groups and single checkboxes
                                    $errorz[] = $jsonFields[$i]->errorMessage;
                                }
                            }
                    break;                  

            case 'radioFlex':
                    $fieldName = 'j'.$jsonFields[$i]->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if (!isset($$fieldName)) {
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        } 
                        else {
                            if ($$fieldName == "" || $$fieldName == "undefined") {  
                                    $errorz[] = $jsonFields[$i]->errorMessage;
                                }
                        }
                    }

                    if (isset($$fieldName)) {
                        if ($$fieldName != "" && $$fieldName != "undefined") {  
                            // keep building SQL statement
                            $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                            $APP_insertQueryValues .= ", '".$$fieldName."'";
                      
                            $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";
                        }
                    }
                    break;                  

            case 'twoRadioFlex':
                    $fieldName = 'j'.$jsonFields[$i]->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if (!isset($$fieldName)) {
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        } 
                        else {
                            if ($$fieldName == "" || $$fieldName == "undefined") {  
                                    $errorz[] = $jsonFields[$i]->errorMessage;
                                }
                        }
                    }

                    if (isset($$fieldName)) {
                        if ($$fieldName != "" && $$fieldName != "undefined") {  
                            // keep building SQL statement
                            $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                            $APP_insertQueryValues .= ", '".$$fieldName."'";
                      
                            $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";
                        }
                    }
                    break;                      
                                      
                                      
                case 'file':
                        $fieldName = 'j'.$jsonFields[$i]->name;

                        if (strtolower($jsonFields[$i]->required) == "y") {
                            if ($$fieldName == "") {  
                                $errorz[] = $jsonFields[$i]->errorMessage;
                            }
                        }
                        // keep building SQL statement
                        $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                        $APP_insertQueryValues .= ", '".$$fieldName."'";

                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldName."<br><br>";                                        
                        break;


            case 'hidden':
                        $fieldName = 'j'.$jsonFields[$i]->name;
                        // in this case we can use the required field flag to determine if this hidden field has to inserted or if it is being used as a FLAG 
                        if (isset($jsonFields[$i]->required) && strtolower($jsonFields[$i]->required) == "y") {
                                // keep building SQL statement
                                $APP_insertQuery .= ", ".$jsonFields[$i]->name;
                                $APP_insertQueryValues .= ", '".$$fieldName."'";
                        }
                        //we dont show hidden fields in email receipts, so we don't need this
                        //$receiptContents .= $jsonFields[$i]->labelName."<br>".$$fieldName."<br><br>";
                        break;

            case 'name':
                    $fieldNameFN = 'j'.$jsonFields[$i]->first->name;
                    $fieldNameLN = 'j'.$jsonFields[$i]->last->name;
                    $fieldNameMN = 'j'.$jsonFields[$i]->middle->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNameFN == "" || $$fieldNameLN == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->first->name.", ".$jsonFields[$i]->last->name.", ".$jsonFields[$i]->middle->name;
                    $APP_insertQueryValues .= ", '".$$fieldNameFN."', '".$$fieldNameLN."', '".$$fieldNameMN."'";                                                 

                    if ($$fieldNameFN != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName." (first middle last)</strong><br>".$$fieldNameFN." ".$$fieldNameMN." ".$$fieldNameLN."<br><br>";
                    }
                    break;                          

                case 'address':
                        $fieldNameAD1 = 'j'.$jsonFields[$i]->lineone->name;
                        $fieldNameAD2 = 'j'.$jsonFields[$i]->linetwo->name;
                        $fieldNameADC = 'j'.$jsonFields[$i]->city->name;
                        $fieldNameADS = 'j'.$jsonFields[$i]->state->name;
                        $fieldNameADZ = 'j'.$jsonFields[$i]->zip->name;
                        $fieldNameADCTY = 'j'.$jsonFields[$i]->country->name;

                        if (strtolower($jsonFields[$i]->required) == "y") {
                                if ($$fieldNameAD1 == "" || $$fieldNameADC == "" || $$fieldNameADS == "" || $$fieldNameADZ == "") {  
                                        $errorz[] = $jsonFields[$i]->errorMessage;
                                }
                        }
                        // keep building SQL statement
                        $APP_insertQuery .= ", ".$jsonFields[$i]->lineone->name.", ".$jsonFields[$i]->linetwo->name.", ".$jsonFields[$i]->city->name.", ".$jsonFields[$i]->state->name.", ".$jsonFields[$i]->zip->name;
                        $APP_insertQueryValues .= ", '".$$fieldNameAD1."', '".$$fieldNameAD2."', '".$$fieldNameADC."', '".$$fieldNameADS."', '".$$fieldNameADZ."'";

                         if ($$fieldNameAD1 != "") { // if field is not blank, add to reciept
                             $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNameAD1."<br>".$$fieldNameAD2."<br>".$$fieldNameADC.", ".$$fieldNameADS." ".$$fieldNameADZ."<br><br>";
                          }
                        
                         if ($jsonFields[$i]->country->enabled == "y") {
                            $APP_insertQuery .= ", ".$jsonFields[$i]->country->name;
                            $APP_insertQueryValues .= ", '".$$fieldNameADCTY."'";
                            $receiptContents .= $$fieldNameADCTY."<br><br>";
                        }
                    break;                          

            case 'date':
                    
                    $fieldNameDMN = 'j'.$jsonFields[$i]->month->name;
                    $fieldNameDDY = 'j'.$jsonFields[$i]->day->name;
                    $fieldNameDYR = 'j'.$jsonFields[$i]->year->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNameDMN == "" || $$fieldNameDDY == "" || $$fieldNameDYR == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->month->name.", ".$jsonFields[$i]->day->name.", ".$jsonFields[$i]->year->name;
                    $APP_insertQueryValues .= ", '".$$fieldNameDMN."', '".$$fieldNameDDY."', '".$$fieldNameDYR."'";        

                    if ($$fieldNameDMN != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNameDMN."/".$$fieldNameDDY."/".$$fieldNameDYR."<br><br>";
                    }
                    break;      
                    
            case 'dateflex':
                    $fieldNameDMN = 'j'.$jsonFields[$i]->month->name;
                    $fieldNameDDY = 'j'.$jsonFields[$i]->day->name;
                    $fieldNameDYR = 'j'.$jsonFields[$i]->year->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNameDMN == "" || $$fieldNameDDY == "" || $$fieldNameDYR == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->month->name.", ".$jsonFields[$i]->day->name.", ".$jsonFields[$i]->year->name;
                    $APP_insertQueryValues .= ", '".$$fieldNameDMN."', '".$$fieldNameDDY."', '".$$fieldNameDYR."'";        

                    if ($$fieldNameDMN != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNameDMN." ".$$fieldNameDDY.", ".$$fieldNameDYR."<br><br>";
                    }
                    break;                  

            case 'timeflex':
                    $fieldNameDTH = 'j'.$jsonFields[$i]->hour->name;
                    $fieldNameDTM = 'j'.$jsonFields[$i]->minute->name;
                    $fieldNameDTP = 'j'.$jsonFields[$i]->ampm->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNameDTH == "" || $$fieldNameDTM == "" || $$fieldNameDTP == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->hour->name.", ".$jsonFields[$i]->minute->name.", ".$jsonFields[$i]->ampm->name;
                    $APP_insertQueryValues .= ", '".$$fieldNameDTH."', '".$$fieldNameDTM."', '".$$fieldNameDTP."'";        

                    if ($$fieldNameDTH != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNameDTH.":".$$fieldNameDTM." ".$$fieldNameDTP."<br><br>";
                    }
                    break;                  

            case 'phone':
                    $fieldNamePA = 'j'.$jsonFields[$i]->area->name;
                    $fieldNamePF = 'j'.$jsonFields[$i]->first->name;
                    $fieldNamePL = 'j'.$jsonFields[$i]->last->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNamePA == "" || $$fieldNamePF == "" || $$fieldNamePL == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->area->name.", ".$jsonFields[$i]->first->name.", ".$jsonFields[$i]->last->name;
                    $APP_insertQueryValues .= ", '".$$fieldNamePA."', '".$$fieldNamePF."', '".$$fieldNamePL."'";    

                    if ($$fieldNamePA != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNamePA."-".$$fieldNamePF."-".$$fieldNamePL."<br><br>";
                    }
                    break;      


            case 'socialsecurity':

                    $fieldNameSS1 = 'j'.$jsonFields[$i]->ss1->name;
                    $fieldNameSS2 = 'j'.$jsonFields[$i]->ss2->name;
                    $fieldNameSS3 = 'j'.$jsonFields[$i]->ss3->name;

                    if (strtolower($jsonFields[$i]->required) == "y") {
                        if ($$fieldNameSS1 == "" || $$fieldNameSS2 == "" || $$fieldNameSS3 == "") {  
                            $errorz[] = $jsonFields[$i]->errorMessage;
                        }
                    }
                    // keep building SQL statement
                    $APP_insertQuery .= ", ".$jsonFields[$i]->ss1->name.", ".$jsonFields[$i]->ss2->name.", ".$jsonFields[$i]->ss3->name;
                    $APP_insertQueryValues .= ", '".$$fieldNameSS1."', '".$$fieldNameSS2."', '".$$fieldNameSS3."'";

                    if ($$fieldNameSS1 != "") { // if field is not blank, add to reciept
                        $receiptContents .=  "<strong>".$jsonFields[$i]->labelName."</strong><br>".$$fieldNameSS1."-".$$fieldNameSS2."-".$$fieldNameSS3."<br><br>";
                    }
                    break;      

                    
            default: // do nothing...field type does not exist
                    break;
    }
}

    
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
// CHECK FOR AND DISPLAY ERRORS 
// =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

// count and check to see if there were any errors
$errorz_count = count($errorz);

if ($errorz_count > 0) {
        // RETURN JSON/AJAX RESPONSE
        echo json_encode(array("status"=>"error", "heading"=>"<h4>Sorry</h4> You submitted the form, but some information is missing!", "message"=>$errorz));
    }
    else {
                
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        // FORMBOT PLUGIN BEGIN
        // FORM BOT POST PROCESSOR
        // This file is good for:
        // manipulating data for special use in email reciepts, or additional e-mail notification, or other database table manipulation 
        // manipulating SQL string by concatenating another field name and value existing query string before actual insert
        // anything that has to be done after all fields are submitted, checked for errors and BEFORE query is executed
        
        // FILE CAN ALSO BE BLANK IF YOUR APP DOES NOT NEED IT
        // ONLY AVAILABLE FOR THE FORM CURRENTLY BEING SUBMITTED
        include $_SERVER["DOCUMENT_ROOT"].'/global/forms/'.$jsonData->meta->appPath.'/formBotPostProcessor.php';  
        // FORMBOT PLUGIN END
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        
        
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        // RUN INSERT QUERY IF NO ERRORS ARE FOUND AND APP IS COMPLETE
        // FINISH THE QUERY STATEMENT
        // CONCATENATE INSERT VALUES WITH FIELD STATEMENTS
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                $APP_insertQuery .= $APP_insertQueryValues.")";

                // execute SQL statement 
                $APP_insertResult = mysql_query($APP_insertQuery) or die("ERROR: Unable to insert record.\n");
                // lets free the result in memory and close the DB connection   
                mysql_close($APP_connection);


        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        // SEND EMAIL TO APPLICANT
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#


        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        // INCLUDE E-MAIL CLASS TO SEND E-MAIL RECEIPTS TO ALL PARTIES
        // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
        
        require $_SERVER["DOCUMENT_ROOT"].'/lib/PHPMailer/class.phpmailer.php';


        // PREP DATA FOR REFERENCE IN EMAIL TEMPLATE
        $emailHeading = $jsonData->meta->emailHeading;
        $emailCopy = $receiptContents;
        // INJECT GLOBAL EMAIL TEMPLATE
        include $_SERVER["DOCUMENT_ROOT"].'/global/emailTemplate.php';


        $mail = new PHPMailer(); 

        $mail->IsSMTP();                         // Enable SMTP debugging  0 = off (for production use)  1 = client messages  2 = client and server messages (for testing)
        $mail->SMTPDebug = 0;
        
        //$mail->Debugoutput = 'html';        // Ask for HTML-friendly debug output
        $mail->SMTPAuth = true;              // enable SMTP authentication
        $mail->SMTPSecure = "tls";          // sets the prefix to the servier
        $mail->Host = "";                           // sets SMTP server
        $mail->Port = 587;                       // set the SMTP port for the GMAIL server
        $mail->Username = "";  
        $mail->Password = "";  
        $mail->SetFrom('receipts@logicalstars.com', 'Web Reciepts');
        $mail->AddReplyTo('receipts@logicalstars.com', 'Web Reciepts');


        $emailName = 'j'.$jsonData->meta->emailName;
        if ($jsonData->meta->emailName == "n") {
                //do nothing - no email receipt is required
            }
            else
                {

                    $mail->Subject = $jsonData->meta->emailSubject;

                    //Read an HTML message body from an external file, convert referenced images to embedded, convert HTML into a basic plain-text alternative body
                    $mail->MsgHTML($emailSource);

                    //Replace the plain text body with one created manually
                    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                    $mail->AddAddress($$emailName);

                    // EMAIL ATTACHMENTS
                    if ($jsonData->meta->emailAttachments == "y") {
                            include $_SERVER["DOCUMENT_ROOT"].'/global/forms/'.$jsonData->meta->appPath.'/formBotMailAttachments.php';    
                        }
                      
                    if (!$mail->Send()) {
                       echo 'Message could not be sent.';
                       echo 'Mailer Error: '.$mail->ErrorInfo;
                    }
                    
                    // CLEAR ADDRESSES AND ATTACHMENTS FROM PREVIOUS MESSAGE
                    $mail->ClearAddresses();
                    $mail->ClearAttachments();
                }


                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                // SEND EMAIL TO STAFF
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                

                    $mail->Subject = $emailSubjectPrefix.$jsonData->meta->emailSubject;

                    //Read an HTML message body from an external file, convert referenced images to embedded, convert HTML into a basic plain-text alternative body
                    $mail->MsgHTML($emailSource);
                    //Replace the plain text body with one created manually
                    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

                    $mail->AddAddress($jsonData->meta->staffRecipients);

                    if (!$mail->Send()) {
                       echo 'Message could not be sent.';
                       echo 'Mailer Error: '.$mail->ErrorInfo;
                    }

        
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                // DISPLAY SUCCESS MESSAGE
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#

                
                // added custom redirect for forms like CTH that need entire form reset for dynamic form fields and large thanks message
        $successRedirect = $jsonData->meta->successRedirect;
        // assumes a url is provided in var above
        if ($successRedirect == "y") {
            echo "<script type='text/javascript'>location.href='$successRedirect';</script>";               
            }
            else {
                        // RETURN JSON/AJAX RESPONSE              
                        echo json_encode(array("status"=>"success", "heading"=>"<h4>Form Submitted Successfully</h4>", "message"=>$jsonData->meta->successMessage));    
               }



                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                // RECORD THIS TRANSACTION  START
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                // FORM PARAMETERS
                $formIdentity = $jsonData->meta->analyticsID;
                $sendEmail = "y";
                // TURN ON LATER...   include $_SERVER["DOCUMENT_ROOT"].'/global/bigBrotherAnalytics.php';
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                // RECORD THIS TRANSACTION  END
                // =#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#=#
                
            }
?>
