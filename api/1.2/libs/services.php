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

	function updateReqSent($probe_uuid) {
		# increment the requests sent counter on the probe record
		$result = $this->conn->query(
			"update probes set probeReqSent=probeReqSent+1,lastSeen=now() where uuid=?",
			array($probe_uuid)
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

	function load($url) {
		$result = $this->conn->query(
			"select * from urls where URL=?",
			array($url)
			);
		if ($result->num_rows == 0) {
			throw new UrlLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}

	function get_next_old() {
		$result = $this->conn->query("select urlID,URL,hash from urls where lastPolled is null or lastPolled < date_sub(now(), interval 12 hour) ORDER BY lastPolled ASC,polledAttempts DESC LIMIT 1", array());
		if ($result->num_rows == 0) {
			return null;
		}
		$row = $result->fetch_assoc();
		return $row;
	}

	function get_next($ispid) {
		/*
		The main queue query.  This prioritises URLs to check in order of results received, 
		aiming to gain multiple opinions of a URL's status on a given ISP as quickly as possible.
		The URL list cycles once per day.

		It is possible for the same probe to handle a single URL on multiple successive days.
		Trying to control which probe gets which URLs can be done, but it's a trade-off between 
		fine-grained control and queue management expense.  This would involve a lumpy left-join or
		burning through results until we find one that hasn't been sent to the probe before.

		This won't scale wonderfully, but it's a start.

		The main query does at least have a covering index, so it's a bit less expensive to start 
		with (no filesort!).
		*/

		$result = $this->conn->query(
			"select URL, urls.urlID, queue.id, hash from urls
			inner join queue on queue.urlID = urls.urlID
			where queue.ispID = ? and (lastSent < date_sub(now(), interval 1 day) or lastSent is null)
			order by queue.priority,queue.results, queue.lastSent
			limit 1",
			array($ispid)
			);

		if ($result->num_rows == 0) {
			# return null when we don't have any queued URLs to return.
			return null;
		}

		# get the row
		$row = $result->fetch_assoc();

		# update the lastSent timestamp to keep queue entries rolling around
		$this->conn->query(
			"update queue set lastSent = now() where id = ?",
			array($row['id'])
			);

		# update the poll counter on  the URL record
		$this->conn->query(
			"update urls set lastPolled = now(), polledAttempts = polledAttempts + 1 where urlID = ?",
			array($row['urlID'])
			);

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

	function create($name) {
		$title = preg_replace('/[^A-Za-z0-9 \-].*$/','',$name);
		$result = $this->conn->query(
			"insert ignore into isps(name,created) values (?, now())",
			array($title)
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
		return $title;
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
				if (!preg_match('/ \| [A-Z0-9\-_]+ (\- )?([^\|]*?)$/', $record2[0]['txt'], &$matches)) {
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
		$isp = $this->isp_loader->load($result['network_name']);
		$url = $this->url_loader->load($result['url']);

		$this->conn->query(
			"insert into results(urlID,probeID,config,ip_network,status,http_status,network_name, created) values (?,?,?,?,?,?,?,now())",
			array(
				$url['urlID'],$probe['id'], $result['config'],$result['ip_network'],
				$result['status'],$result['http_status'], $result['network_name']
			)
		);

		$this->conn->query(
			"update urls set polledSuccess = polledSuccess + 1 where urlID = ?",
			array($url['urlID'])
			);
		$this->conn->query(
			"update queue set results=results+1 where urlID = ? and IspID = ?",
			array($url['urlID'], $isp['id'])
			);

		$this->probe_loader->updateRespRecv($probe['uuid']);
	}
}
