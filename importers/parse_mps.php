<?php

include "../api/1.2/libs/url.php";
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
        $json = json_decode($file);
        if ($json === false || is_null($json)) {
            echo "Error decoding JSON\n";
            return false;
        }

        // if no results then quit
        if (count($json->result) < 1) {
            echo "No more results found\n";
            return false;
        }

        // loop each entry
        foreach ($json->result as $mp) {
            // homepage_url only seems to be present in 'versions' array of each MP
            // each version seems to be in date order newest first, so going to
            // assume that this is always true and just take first version in array
            
            $url = $mp->versions[0]->data->homepage_url;
            $source = 'nextmp';
            $this->save_url($url, $source);
            
//            echo $mp->name . " - ". $mp->versions->homepage_url."<br>";
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
            echo "save url ($url) ".$text."\n"; 
        } catch (BadUrlError $exc) {
            echo "bad URL: ". $url ."\n";
            return false;
        }

        //check if url already submitted
        $query = "SELECT urlID,URL FROM urls WHERE URL=? LIMIT 1";
        $res = $this->_mysqli->query($query, array($url));

        //add to urls table if new to blocked
        if ($res->num_rows < 1) {
                $query = "INSERT INTO urls (URL,hash,source,inserted) VALUES (?,?,?,now())";
                $res = $this->_mysqli->query($query, array( $url, md5($url), $source));

                $urlID = $this->_mysqli->insert_id;
                $this->_urls_added++;
        }
        else {
                //already in urls table - should we mark as an mp?
                $row = $res->fetch_assoc();
                $urlID = $row['urlID'];
                $this->_urls_skipped++;
                echo "url exists already - doing nothing\n";
        }
        
        return $urlID;
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


