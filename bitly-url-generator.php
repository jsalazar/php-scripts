function getBitlyUrl($url) {  

    $encodedURL = urlencode($url); 
    $betterURL = "http://api.bit.ly/v3/shorten?login=YOURLOGIN&apiKey=YOURLONGAPIKEY&longUrl=$encodedURL&format=txt";
    $bitlyURL = file_get_contents($betterURL);
    
    return $bitlyURL;
}
