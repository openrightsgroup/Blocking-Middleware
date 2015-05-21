<?php

class UserLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($email) {
		$result = $this->conn->query(
			"select id,secret,probeHMAC,status,administrator from users where email = ?",
			array($email)
			);

		if ($result->num_rows == 0) {
			throw new UserLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}
}

class ProbeLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($probe_uuid) {
		$result = $this->conn->query(
			"select * from probes where uuid=?",
			array($probe_uuid)
			);
		if ($result->num_rows == 0) {
			throw new ProbeLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}

	function updateReqSent($probe_uuid, $count=1) {
		# increment the requests sent counter on the probe record
		$result = $this->conn->query(
			"update probes set probeReqSent=probeReqSent+?,lastSeen=now() where uuid=?",
			array($count, $probe_uuid)
			);
		if ($this->conn->affected_rows != 1) {
			throw new ProbeLookupError();
		}
	}

	function updateRespRecv($probe_uuid) {
		# increment the responses recd counter on the probe record
		$result = $this->conn->query(
			"update probes set probeRespRecv=probeRespRecv+1,lastSeen=now() where uuid=?",
			array($probe_uuid)
			);
		if ($this->conn->affected_rows != 1) {
			throw new ProbeLookupError();
		}
	}

}

class UrlLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	static function parse($url) {
		$parts = parse_url($url);

		if (!isset($parts['path'])) {
			$parts['path'] = '';
		}

		if (@$parts['query']) {
			$path = $parts['path'] . '?' . $parts['query'];
		} else {
			$path = $parts['path'] ;
		}
		return array(($parts['host']), $path, ($parts['scheme']));
	}

	function insert($urltext, $source) {
		error_log("Inserting: $urltext");
		$urlparts = $this->parse($urltext);
		$this->conn->query(
			"insert ignore into urls (domain, path, scheme, hash, source, lastPolled, inserted) values (?,?,?,?,?,now(), now())",
			array($urlparts[0], $urlparts[1], $urlparts[2], md5($urltext), $source)
		);
		if ($this->conn->affected_rows) {
			# we really did insert it, so make sure it queues
			error_log("Inserted");
			$newurl = true;
		} else {
			$newurl = false;
		}
		return $newurl;
	}

	function load($url) {
		$urlparts = $this->parse($url);
		error_log("Looking up: " . implode(',', $urlparts));
		$result = $this->conn->query(
			"select * from urls where domain=? and path=? and scheme=?",
			$urlparts
			);
		if ($result->num_rows == 0) {
			error_log("Not found");
			throw new UrlLookupError();
		}
		$row = $result->fetch_assoc();
		$row['URL'] = $row['scheme'] . '://' . $row['domain'] . $row['path'];
		return $row;
	}

	function checkLastPolled($urlid) {
		# save autocommit state
		$automode = $this->conn->get_autocommit();
		# set autocommit off to allow transaction
		$this->conn->autocommit(false);
		# test lastPolled date in the database
		$result = $this->conn->query(
			"select lastPolled, date_add(lastPolled, INTERVAL 1 DAY) < now()
			from urls where urlID = ?",
			array($urlid)
			);
		$row = $result->fetch_row();

		# if it has never been tested, or the last test < today
		if ($row[0] == null || $row[1] == 1) {
			# update the lastPolled timer inside transaction
			$this->updateLastPolled($urlid);
			$ret = true;
		} else {
			$ret = false;
		}
		# finish transaction with stored result.
		$this->conn->commit();
		#restore autocommit mode
		$this->conn->autocommit($automode);
		return $ret;
	}

	function load_categories($urlID) {
		$result = $this->conn->query(
			"select display_name from categories
			inner join url_categories on category_id = categories.id
			where urlID = ?", array($urlID));
		$out = array();
		while ($row = $result->fetch_row()) {
			$out[] = $row[0];
		}
		return $out;
	}

	function updateLastPolled($urlid) {
		$this->conn->query("update urls set lastPolled=now() where urlID=?",
			array($urlid));
		if ($this->conn->affected_rows != 1) {
			throw new UrlLookupError();
		}
	}
}

class ContactLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($email) {
		$result = $this->conn->query(
			"select * from contacts where email=?",
			array($email)
			);
		if ($result->num_rows == 0) {
			throw new UrlLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}

}

class IspLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($ispname) {
		$result = $this->conn->query(
			"select isps.* from isps left join isp_aliases on isp_aliases.ispID = isps.id where name = ? or alias = ?",
			array($ispname,$ispname)
			);
		$row = $result->fetch_assoc();
		if (!$row) {
			throw new IspLookupError();
		}
		return $row;
	}

	function create($name, $create_queues=false) {
		$title = preg_replace('/[^A-Za-z0-9 \-].*$/','',$name);
		// TODO: tidy up module dependency
        if ($create_queues) {
            $queue_name =  get_queue_name($title);
        } else {
            $queue_name = null;
        }
		$result = $this->conn->query(
			"insert ignore into isps(name,created, description) values (?, now(), ?, ?)",
			array($title, $title, $queue_name)
			);
		if (!$result) {
			throw new DatabaseError();
		}
		$ispid = $this->conn->insert_id;
		$this->conn->query("insert into isp_aliases(ispid, alias, created)
			values (?, ?, now())",
			array($ispid, $name)
		);
		if (!$result) {
			throw new DatabaseError();
		}
		return array('name' => $title);
	}
}

class IpLookupService {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function check_cache($ip) {
		error_log("Checking cache for $ip");
		$result = $this->conn->query(
			"select network from isp_cache where ip = ? and 
			created >= date_sub(current_date, interval 7 day)",
			array($ip)
			);
		if (!$result) {
			return null;
		}
		$row = $result->fetch_assoc();
		if (!$row) {
			return null;
		}
		return $row['network'];
	}

	function write_cache($ip, $network) {
		error_log("Writing cache entry for $ip, $network");
		$this->conn->query(
			"insert into isp_cache(ip, network, created) 
			values (?, ?, now())
			on duplicate key update created = current_date",
		array($ip, $network)
		);
		error_log("Cache write complete");
	}

	function lookup($ip) {
		# run a DNS query for the IP address

		$descr = $this->check_cache($ip);
		if ($descr == null) {
			error_log("Cache miss for $ip");

			if (strpos($ip, ".") !== false) {
				# ipv4 address

				$parts = array_reverse(explode(".", $ip));
				$hostname = implode(".", $parts) . '.origin.asn.cymru.com';
				error_log("Hostname: $hostname");

				$record = dns_get_record($hostname, DNS_TXT);
				if (!$record) {
					throw new IpLookupError();
				}
				error_log("TXT: " .  $record[0]['txt']);
				list($as, $junk) = explode(' ', $record[0]['txt'], 2);

				error_log("AS: $as");

				$ashost = "AS{$as}.asn.cymru.com";
				$record2 = dns_get_record($ashost, DNS_TXT);
				if (!$record) {
					throw new IpLookupError();
				}

				error_log("TXT: " .  $record2[0]['txt']);
				if (!preg_match('/ \| [A-Z0-9\-_]+ (\- )?([^\|]*?)$/', $record2[0]['txt'], $matches)) {
					throw new IpLookupError();
				}
				$descr = $matches[2];
				error_log("Descr: $descr");

			}

			if (!$descr) {
				throw new IpLookupError();
			}
			$this->write_cache($ip, $descr);
		} else {
			error_log("Cache hit");
		}
		error_log("Descr: $descr");
		return $descr;
	}
}

class ResultProcessorService {
	function __construct($conn, $url_loader, $probe_loader, $isp_loader) {
		$this->conn = $conn;
		$this->url_loader = $url_loader;
		$this->probe_loader = $probe_loader;
		$this->isp_loader = $isp_loader;
	}

	function process_result($result, $probe) {
		# make sure that the named network exists
		$isp = $this->isp_loader->load($result['network_name']);
		$url = $this->url_loader->load($result['url']);

		$this->conn->query(
			"insert into results(urlID,probeID,config,ip_network,status,http_status,network_name, category, blocktype, created) 
			values (?,?,?,?,?,?,?,?,?,now())",
			array(
				$url['urlID'],$probe['id'], $result['config'],$result['ip_network'],
				$result['status'],$result['http_status'], $result['network_name'],
				@$result['category'],@$result['blocktype']
			)
		);

		$this->conn->query(
			"update urls set polledSuccess = polledSuccess + 1 where urlID = ?",
			array($url['urlID'])
			);

		$this->probe_loader->updateRespRecv($probe['uuid']);
	}
}
