<?php

include "../api/1.2/libs/url.php";
include "../api/1.2/libs/DB.php";

class SimpleDMOZParser
{
    protected $_stack = array();
    protected $_file = "";
    protected $_parser = null;

    protected $_currentId = "";
    protected $_current = "";

    protected $_lastURL;
    protected $_in_ext_page;

    protected $_mysqli;
    protected $_counter;

    public function __construct($file)
    {
	global $dbuser, $dbpass, $dbhost, $dbname;
		
        $this->_file = $file;

        $this->_parser = xml_parser_create("UTF-8");
        xml_set_object($this->_parser, $this);
        xml_set_element_handler($this->_parser, "startTag", "endTag");
        xml_set_character_data_handler($this->_parser,"charData");

	$this->_mysqli = new APIDB($dbhost, $dbuser, $dbpass, $dbname);
	if ($this->_mysqli->connect_errno) {
	    echo "Failed to connect to MySQL: (" . $this->_mysqli->connect_errno . ") " . $this->_mysqli->connect_error;
	}

    }

    public function startTag($parser, $name, $attribs)
    {

        array_push($this->_stack, $this->_current); 
        $this->_current = $name;

	//find current category
	if (is_array($attribs) && isset($attribs['R:ID']) && $attribs['R:ID'] != "") {

		//check db if category exists
		$query = "SELECT ID FROM categories WHERE display_name = ? LIMIT 1";
		$res = $this->_mysqli->query($query, array($attribs['R:ID']));

		//add category to table if new to blocked
		if ($res->num_rows < 1) {
			$elements = explode ('/',$attribs['R:ID']);

			$query = "INSERT INTO categories 
			    (display_name, name1, name2, name3, name4, name5, name6, name7, name8, name9, name10) 
			    VALUES (?,?,?,?,?,?,?,?,?,?,?)";
			    
			$res = $this->_mysqli->query($query, array($attribs['R:ID'],@$elements[1],@$elements[2],@$elements[3],
			    @$elements[4],@$elements[5],@$elements[6],@$elements[7],@$elements[8],@$elements[9],@$elements[10]));
			//note category id
			$categoryID = $this->_mysqli->insert_id;

		}
		else {
			//get category id of row that already exists
			$row = $res->fetch_assoc();
			$categoryID = $row['id'];
		}

	}

 	if (isset($attribs['ABOUT'])) {
		$this->_lastURL = $attribs['ABOUT'];
	}


   }

    public function endTag($parser, $name)
    {
	$this->_current = array_pop($this->_stack);
    }

    public function charData($parser,$text)
    {

	//var_dump($this->_stack);
	if (isset($this->_stack[2]) && $this->_stack[2] == "EXTERNALPAGE" && $this->_current == "TOPIC") {
		//var_dump($this->_stack, $this->_current);

		try {
		    $url = normalize_url($this->_lastURL);
		    echo "save url ($url) ".$text."\n"; 
		} catch (BadUrlError $exc) {
		    return;
		}

		//check if url already submitted
		$query = "SELECT urlID,URL FROM urls WHERE URL=? LIMIT 1";
		$res = $this->_mysqli->query($query, array($url));

		//add to urls table if new to blocked
		if ($res->num_rows < 1) {
			$query = "INSERT INTO urls (URL,hash,source,inserted) VALUES (?,?,?,now())";
			$res = $this->_mysqli->query($query, array( $url,md5($url),'dmoz'));
		
			$urlID = $this->_mysqli->insert_id;

		}
		else {
			//already in urls table --- update record?
			$row = $res->fetch_assoc();
			$urlID = $row['urlID'];
		}

		$query = "insert into url_categories(urlID, category_id) select '$urlID',id from categories 
		    where display_name = ?";
		$res = $this->_mysqli->query($query, array($text )); 
	}
 
    }
    public function parse()
    {
        $fh = fopen($this->_file, "r");
        if (!$fh) {
            die("Epic fail!\n");
        }

        while (!feof($fh)) {
            $data = fread($fh, 4096);
            xml_parse($this->_parser, $data, feof($fh));
        }
    }
}

if (!@$argv[1]) {
	print "Required parameter: <filename>\n";
	exit(1);
}

echo "Reading: $argv[1]\n";
$parser = new SimpleDMOZParser($argv[1]);
$parser->parse();


?>
