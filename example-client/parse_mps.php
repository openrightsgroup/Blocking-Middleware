<?php

include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";


// remember to update database with new enum value for data from this source
// ALTER TABLE urls MODIFY COLUMN source ENUM('social','user','canary','probe','alexa','dmoz','nextmp');


class parseYourNextMP
{
    protected $_file = "";
    
    protected $_mysqli;
    
    public function __construct($file) {
        
        global $dbuser, $dbpass, $dbhost, $dbname;
		
        $this->_file = $file;
        
	$this->_mysqli = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
	if ($this->_mysqli->connect_errno) {
	    echo "Failed to connect to MySQL: (" . $this->_mysqli->connect_errno . ") " . $this->_mysqli->connect_error;
	}
    }
    
    public function parse()
    {
        // get the json file
        $file = file_get_contents($this->_file);
        if ($file === false) {
            echo "Error Reading file\n";
            return false;
        }
        
        // decode file into json
        $json = json_decode($file);
        if ($json === false || is_null($json)) {
            echo "Error decoding JSON";
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
            
            echo $mp->name . " - ". $mp->versions->homepage_url."<br>";
        }
        
    }
    
    
    
    public function save_url($url, $source)
    {
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

        }
        else {
                //already in urls table - should we mark as an mp?
                $row = $res->fetch_assoc();
                $urlID = $row['urlID'];
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
$parser = new parseYourNextMP($file_loc);
$parser->parse();


