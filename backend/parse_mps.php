<?php

include "../api/1.2/libs/url.php";
include "../api/1.2/libs/services.php";
include "../api/1.2/libs/DB.php";


// remember to update database with new enum value for data from this source
// ALTER TABLE urls MODIFY COLUMN source ENUM('social','user','canary','probe','alexa','dmoz','nextmp');

// Process You Next MP feed and save to url table

class parseYourNextMP
{
    protected $_file_root = "";
    
    protected $_mysqli;
    
    protected $_urls_processed = 0;
    protected $_urls_added = 0;
    protected $_urls_skipped = 0;
    
    public function __construct() {
        
        global $dbuser, $dbpass, $dbhost, $dbname;
        
	$this->_mysqli = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
	if ($this->_mysqli->connect_errno) {
	    echo "Failed to connect to MySQL: (" . $this->_mysqli->connect_errno . ") " . $this->_mysqli->connect_error;
	}
		$this->url_loader = new UrlLoader($this->_mysqli);
    }
    
    // process an individual page of results
    public function parse($file_url)
    {
        // get the json file
        $file = file_get_contents($file_url);
        if ($file === false) {
            echo "Error Reading file\n";
            return false;
        }
        
        // decode file into json
        $json = json_decode($file, true);
        if ($json === false || is_null($json)) {
            echo "Error decoding JSON\n";
            return false;
        }

        // if no results then quit
        if (count($json['result']) < 1) {
            echo "No more results found\n";
            return false;
        }
        
        // loop each entry
        foreach ($json['result'] as $mp) {
            // homepage_url only seems to be present in 'versions' array of each MP
            // each version seems to be in date order newest first, so going to
            // assume that this is always true and just take first version in array
 
            $url = $mp['versions'][0]['data']['homepage_url'];
            $source = 'nextmp';
            $urlID = $this->save_url($url, $source);
            
            // prepare tags
            //get JSON as array so can access integer keys
            $tags = array();
            $tags['mp:const'] = $mp['versions'][0]['data']['standing_in']['2015']['name'];
            $tags['source'] = 'Your Next MP';
            $tags['addr:country'] = 'uk';
            
            // save tags
            $this->url_loader->save_tags($urlID, $tags, $tags['source']);
        }
        return true;
    }
    
    // process results, works out url of each page and calls parse()
    public function parse_all_pages($root)
    {
        $i = 1;
        $got_data = true;
        while ($got_data != false) {
            if ($i == 0) {
                $file_url = $root;
            }
            else {
                $file_url = $root ."&page=".$i;
            }
            echo "processing page ". $file_url ."\n";
            $got_data = $this->parse($file_url);
            
            $i++;
            flush();
        }
        
        echo "Found ". $this->_urls_processed . " urls, added ". $this->_urls_added ." urls,"
           . " skipped ". $this->_urls_skipped ." urls because they were already in the db \n";
    }
    
    
    // save a url to the db, if it already exists do nothing
    public function save_url($url, $source)
    {
        $this->_urls_processed++;
        try {
            $url = normalize_url($url);
            print "save url ($url) \n"; 
        } catch (BadUrlError $exc) {
            print "bad URL: ". $url ."\n";
            return false;
        }

		return $this->url_loader->insert($url, $source);
    }
    
}

if (@$argv[1]) {
    $file_loc = $argv[1];
}
else {
    $file_loc = "http://yournextmp.popit.mysociety.org/api/v0.1/search/persons?q=_exists_:homepage_url";
}


echo "<pre>Reading: ". $file_loc ."\n";
$parser = new parseYourNextMP();
$parser->parse_all_pages($file_loc);


