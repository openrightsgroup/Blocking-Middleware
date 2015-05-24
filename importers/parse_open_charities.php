<?php

// remember to update database with new enum value for data from this source
// ALTER TABLE urls MODIFY COLUMN source ENUM('social','user','canary','probe','alexa','dmoz','nextmp','open_charities');


include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";

class SimpleOpenCharitiesParser
{
    const WEBSITE_FIELD = 5;
    const ADDRESS_FIELD = 4;
    
    
    
    protected $_stack = array();
    protected $_file = "";

    protected $_charities_processed = 0;
    protected $_urls_added = 0;
    protected $_urls_skipped = 0;

    protected $_mysqli;
    protected $_counter;

    public function __construct($file)
    {
	global $dbuser, $dbpass, $dbhost, $dbname;
		
        $this->_file = $file;

	$this->_mysqli = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
	if ($this->_mysqli->connect_errno) {
	    echo "Failed to connect to MySQL: (" . $this->_mysqli->connect_errno . ") " . $this->_mysqli->connect_error;
	}

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
    
    
    
    public function parse()
    {
        $row = 1;
        if (($handle = fopen($this->_file, "r")) !== FALSE) {
            while (($data = fgetcsv($handle)) !== FALSE) {
                $this->_charities_processed++;
                if (!is_null($data) && $data[self::WEBSITE_FIELD] != '') {
                    print "saving ". $data[self::WEBSITE_FIELD];
                    $this->save_url($data[self::WEBSITE_FIELD], 'opencharities');
                }
                
                $row++;
            }
            fclose($handle);
        }
        
        // stats
        echo $this->_charities_processed . " charities processed, ".
             $this->_urls_added ." urls added ".
             $this->_urls_skipped ." urls skipped";
    }
}

if (!@$argv[1]) {
	print "Required parameter: <filename>\n";
	exit(1);
}

$file = "output.csv";

echo "<pre>Reading: ". $argv[1] ."\n";
$parser = new SimpleOpenCharitiesParser($argv[1]);
$parser->parse();


?>
