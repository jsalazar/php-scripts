
include('/usr/local/path/to/your/libraries/simplePie/simplepie.inc');


// Parse it
$feed = new SimplePie();
$twitterRSS = "http://twitter.com/statuses/user_timeline/123456789.rss";

$feed->set_feed_url($twitterRSS);
$feed->enable_cache(false);
$feed->init();
$feed->handle_content_type();

if ($feed->data) {
        $feedCounter = 0;
        $items = $feed-&gt;get_items();    
        $twitterContent = "&lt;ul&gt;";

        foreach($items as $item) {
                $fixedTitle = preg_replace("/â€™/", "'", $item-&gt;get_title());
                $fixedTitle = preg_replace("/’/", "'", $item-&gt;get_title());
                // strip out default text from beginning
                $fixedTitle = preg_replace("/SiteName: /", "", $item-&gt;get_title());
                
                // check where first @ symbol is located
                $atPOS = strpos($fixedTitle, '@'); 
 
                // print out results
                if ($atPOS === 0) {
                        // is this a reply?
                    } 
                    elseif (preg_match("/RT/", $fixedTitle)) { 
                            // is this a retweet?
                        }  
                        else {
                                $feedCounter++;
                                $twitterContent .= "&lt;li&gt;&lt;a href=\"".$item-&gt;get_permalink()."\"&gt;".$fixedTitle."&lt;/a&gt;&lt;br&gt;Posted: ".$item-&gt;get_date('j M. Y - g:i a')."&lt;/li&gt;\n";
                                if ($feedCounter == 3) {
                                        break;
                                    }
                            }
            }
        $twitterContent .= "&lt;/ul&gt;"; 
    }

echo $twitterContent;

// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++
// WRITE RESULTS OF QUERIES TO STATIC HTML FILE 
// ++++++++++++++++++++++++++++++++++++++++++++++++++++++++

// don't overwrite the static file to blank 
if ($feedCounter == 3) {
        // concatenate the results  
        $twitterCacheContent = $twitterContent;
        // set file path 
        $twitterCacheFile = "/usr/local/path/to/your/directory/twitter.html";
        // open the file for reading and writing
        $fw = fopen($twitterCacheFile, "w") or die("ERROR: Unable to find file!");
        // write to file
        fwrite($fw, $twitterCacheContent) or die("ERROR: Unable to write to file!");
        // close file
        fclose($fw);
    }
