// PURPOSE: this script is run by CRON and creates a cache file for use elsewhere...
// METHODS: 
//          flickr.photosets.getPhotos
//          flickr.photosets.getList
// DOCUMENTATION: http://www.flickr.com/services/api/explore/


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// SET FLICKR API KEY AND USER ID FOR OUR PHOTOS 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$apiKey = 'yourAPIKeyGoesHere'; 
$userId = '12345678@A01'; 

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// CALL FLICKR API AND LOAD RESPONSE AS XML 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

$photos = simplexml_load_file("http://api.flickr.com/services/rest/?method=flickr.photosets.getList&api_key=$apiKey&user_id=$userId");

// get count of photosets found
$setsCount = count($photos->photosets->photoset); 

// initialize the sets array to hold thier id's
$setsKeys = array();

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// ADD PHOTOSET ID's TO ARRAY 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

// Iterate over each photo returned:
for ($p = 0; $p < $setsCount; $p++) {
        $setsKeys[] = $photos->photosets->photoset[$p]['id'];
        //$id = $photos->photosets->photoset[$p]['id'];
        //$photosCount = $photos->photosets->photoset[$p]['photos'];
        //$title = $photos->photosets->photoset[$p]->title;
        //$description = $photos->photosets->photoset[$p]->description;
    }
    
// grab 3 random photo album keys   
$randKeys = array_rand($setsKeys, 3);  

// set some generic values
$pageNumber = 1; 

// can be any number - be smart about it
$perPage = 10; 

// initialize the photos array to hold thier id's
$photoKeys = array();
$randomPhotosContent = "";

for ($k = 0; $k < sizeof($randKeys); $k++) {
        $photoSetID = $setsKeys[$randKeys[$k]];

        $photos = simplexml_load_file("http://api.flickr.com/services/rest/?method=flickr.photosets.getPhotos&api_key=$apiKey&photoset_id=$photoSetID&page=$pageNumber&perPage=$perPage");
        // Iterate over each photo returned:
        for ($p = 0; $p < $perPage; $p++) {
                if (!empty($photos->photoset->photo[$p]['id'])) {
                        $id = $photos->photoset->photo[$p]['id'];    
                        $secret = $photos->photoset->photo[$p]['secret'];
                        $server = $photos->photoset->photo[$p]['server'];
                        $farm = $photos->photoset->photo[$p]['farm'];
                        $title = $photos->photoset->photo[$p]['title'];
                        $thumbNail = "http://farm$farm.static.flickr.com/$server/".$id."_".$secret."_s.jpg";
                        $img_url = "http://farm$farm.static.flickr.com/$server/".$id."_".$secret.".jpg";
                        $photoKeys[] = $thumbNail;
                    }
            }
            // grab one random picture from each random album
            $randPhotos = array_rand($photoKeys, 1);
            $randomPhotosContent .= "&lt;img src=\"".$photoKeys[$randPhotos]."\"&gt;";            
            // reset the photoKeys array
            $photoKeys = array();
    }

// show 3 random photos
echo "3 randoms photos: $randomPhotosContent";


// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// WRITE RESULTS OF QUERIES TO STATIC HTML FILE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

// concatenate the results  
$flickrCacheContent = $randomPhotosContent;
// set file path 
$flickrCacheFile = "/usr/local/path/to/your/directory/flickr.html";
// open the file for reading and writing
$fw = fopen($flickrCacheFile, "w") or die("ERROR: Unable to find file!");
// write to file
fwrite($fw, $flickrCacheContent) or die("ERROR: Unable to write to file!");
// close file
fclose($fw);
