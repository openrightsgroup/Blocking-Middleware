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

		$row = $result->fetch();
		if (!$row) {
			throw new UserLookupError();
		}
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
		$row = $result->fetch();
		if (!$row) {
			throw new ProbeLookupError();
		}
		return $row;
	}

	function updateReqSent($probe_uuid, $count=1) {
		# increment the requests sent counter on the probe record
		$result = $this->conn->query(
			"update probes set probeReqSent=probeReqSent+?,lastSeen=now() where uuid=?",
			array($count, $probe_uuid)
			);

		if ($result->rowCount() != 1) {
			throw new ProbeLookupError();
		}
	}

	function updateRespRecv($probe_uuid) {
		# increment the responses recd counter on the probe record
		$result = $this->conn->query(
			"update probes set probeRespRecv=probeRespRecv+1,lastSeen=now() where uuid=?",
			array($probe_uuid)
			);

		if ($result->rowCount() != 1) {
			throw new ProbeLookupError();
		}
	}

}

class UrlLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

    function insert($url, $source="user") {
        /* Insert user record.  Does not return ID. 
        AN insert rule emulates INSERT IGNORE */
        $result = $this->conn->query(
            "insert into urls (URL, hash, source, lastPolled, inserted) values (?,?,?,now(), now())",
            array($url, md5($url), $source)
        );
        /* returns true/false for whether a row was really inserted. */

        if ($result->rowCount()) {
            return true;
        } else {
            return false;
        }
        
    }

	function loadByID($urlid) {
		$result = $this->conn->query(
			"select * from urls where urlID=?",
			array($urlid)
			);
		$row = $result->fetch();
		if (!$row) {
			throw new UrlLookupError();
		}
		return $row;
	}

	function load($url) {
		$result = $this->conn->query(
			"select * from urls where URL=?",
			array($url)
			);
		$row = $result->fetch();
		if (!$row) {
			throw new UrlLookupError();
		}
		return $row;
	}

	function checkLastPolled($urlid) {
		# test lastPolled date in the database
        $this->conn->beginTransaction();
		$result = $this->conn->query(
			"select lastPolled, lastPolled+ INTERVAL '1 DAY' < now()
			from urls where urlID = ?",
			array($urlid)
			);
		$row = $result->fetch(PDO::FETCH_NUM);

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
		return $ret;
	}

	function load_categories($urlID) {
		$result = $this->conn->query(
			"select display_name from categories
			inner join url_categories on category_id = categories.id
			where urlID = ?", 
            array($urlID),
            PDO::FETCH_NUM
            );
		$out = array();
        foreach ($result as $row) {
			$out[] = $row[0];
		}
		return $out;
	}

	function updateLastPolled($urlid) {
		$result = $this->conn->query("update urls set lastPolled=now() where urlID=?",
			array($urlid));

		if ($result->rowCount() != 1) {
			throw new UrlLookupError();
		}
	}

    function get_unreported_blocks($count = 10) {
        // return <n> unreported blocked sites

        $res = $this->conn->query("select 
                urls.url
            from urls 
            inner join blocked_dmoz on blocked_dmoz.urlID = urls.urlID
            left join isp_reports on (isp_reports.urlID = urls.urlID)
            where isp_reports.urlID is null 
            order by rand() limit " . (int)$count,
            # sort  by rand is horrible, do something better
            array()
            );

        $output = array();
        foreach ($res as $data) {
            $output[] = $data['url'];
        }

        return $output;

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
		$row = $result->fetch();
		if (!$row) {
			throw new ContactLookupError();
		}
		return $row;
	}

	function loadByToken($token) {
		$result = $this->conn->query(
			"select * from contacts where token=?",
			array($token)
			);
		$row = $result->fetch();
		if (!$row) {
			throw new ContactLookupError();
		}
		return $row;
	}

    function insert($email, $fullname, $joinlist=false) {
        $this->conn->query(
            "select insert_contact(?,?,?)",
            array(
                $email,
                $fullname,
                $joinlist
                )
        );
        $contact = $this->load($email);
        return $contact;

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
		$row = $result->fetch();
		if (!$row) {
			throw new IspLookupError();
		}
		return $row;
	}

	function create($name) {
		$title = preg_replace('/[^A-Za-z0-9 \-].*$/','',$name);
		// TODO: tidy up module dependency
		$result = $this->conn->query(
			"insert into isps(name,created, description) values (?, now(), ?) returning id as id",
			array($title, $title),
            PDO::FETCH_NUM
			);
		
        $row = $result->fetch();
		$ispid = $row[0];
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
			created >= current_date  - interval '7 day'",
			array($ip)
			);
		if (!$result) {
			return null;
		}
		$row = $result->fetch();
		if (!$row) {
			return null;
		}
		return $row['network'];
	}

	function write_cache($ip, $network) {
		error_log("Writing cache entry for $ip, $network");
        $this->conn->beginTransaction();
        $q = $this->conn->query(
            "update isp_cache set created = now() where ip = ? and network = ?",
            array($ip, $network)
            );
        if ($q->rowCount() == 0) {
            $this->conn->query(
            "insert into isp_cache(ip, network, created) values (?, ?, now())",
            array($ip, $network)
            );
        }
        $this->conn->commit();
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
				if (!preg_match('/ \| (\- )?([^\|]*?)$/', $record2[0]['txt'], $matches)) {
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

class DMOZCategoryLoader {
    function __construct($conn) {
        $this->conn = $conn;
    }

    function load($id) {
        $res = $this->conn->query('select * from categories where id = ?',
            array($id));
        $row = $res->fetch();
        return $row;
    }

    function get_lookup_key($parent) {
        // returns a dictionary containing name columns that are in use
        $output = array();
        $last = null;
        for ($i = 1; $i <= 10; $i++) {
            if (trim($parent["name$i"]) != "") {
                $v = $parent["name$i"];
                $output["name$i"] = $parent["name$i"];
            }
        }
        return $output;

    }

    function get_parents($node) {
        $out = array();

        $res = $this->conn->query(
            'select id, name from categories
            where tree @> ? and id <> ?
            order by tree',
            array($node['tree'], $node['id']),
            PDO::FETCH_NUM
            );

        foreach ($res as $data) {
            if ($data[1] != $node['tree']) {
                $out[] = $data;
            }
        }

        return $out;
    }

    function get_parent($node) {

        $sql = 'select id
            from categories 
            where tree @> ?
            order by tree desc limit 1 offset 1';
        $q = $this->conn->query($sql, array($node['tree']));
        $row = $q->fetch();
        return $row['id'];
    }


    function load_children($parent, $show_empty=0, $sort='display_name') {
        if (!in_array(ltrim($sort,'-'), array('display_name','total_block_count'))) {
            throw new InvalidSortError("invalid sort: $sort");
        }
        if ($sort[0] == '-') {
            $sort = ltrim($sort,'-') . " desc";
        }

        $cond = array('tree ~ ?');
        if (!$show_empty) {
            $cond[] = "total_block_count > 0";
        }
        $where = implode(" and ", $cond);
        $args = array($parent['tree'] . '.*{1}');

        $sql = "select id, display_name,
            name, 
            total_blocked_url_count,
            total_block_count,
            blocked_url_count,
            block_count
            from categories 
            where $where
            order by $sort";
        return $this->conn->query($sql, $args);
    }

    function load_toplevel($show_empty = 1, $sort='display_name') {
        // get the top-level categories (same format as load_children)
        if (!in_array(ltrim($sort,'-'), array('display_name','total_block_count'))) {
            throw new InvalidSortError("invalid sort: $sort");
        }
        if ($sort[0] == '-') {
            $sort = ltrim($sort,'-') . " desc";
        }
        $sql = "select id, display_name,
            name,
            total_blocked_url_count,
            total_block_count,
            blocked_url_count,
            block_count
            from categories 
            where nlevel(tree) = 1 " .
            ($show_empty ? "" : " and total_block_count > 0 ") . 
            "order by $sort";
        return $this->conn->query($sql, array());


    }

    function load_sites($parent) {
        // get sites that belong to a category (does not get sites of child categories)
        # TODO: unicode function
		$result = $this->conn->query(
			"select URL from urls
			inner join url_categories on urls.urlID = url_categories.urlID
			where category_id = ?", 
            array($parent), 
            PDO::FETCH_NUM
            );
		$out = array();
        foreach ($result as $row) {
			$out[] = $row[0];
		}
		return $out;
	}

    function load_sites_recurse($parent) {
        // get sites that belong to a category 
        # TODO: unicode function
        $row = $this->load($parent);
        $key = $this->get_lookup_key($row);
        $f = array();
        $k = array();
        foreach($key as $k => $v) {
            $f[] = "$k = ?";
            $args[] = $v;
        }
        $where = implode(" AND ", $f);

		$result = $this->conn->query(
			"select URL from urls
			inner join url_categories on urls.urlID = url_categories.urlID
            inner join categories on categories.id = url_categories.category_id
			where $where limit 20", 
            $args,
            PDO::FETCH_NUM
            );
		$out = array();
        foreach ($result as $row) {
			$out[] = $row[0];
		}
		return $out;
	}

    function load_blocks($parentid, $filter_active=0) {
        // get blocked sites that belong to a category (does not get sites of child categories)
        if ($filter_active) {
            $active = "inner join isps on uls.network_name = isps.name and isps.queue_name is not null";
        } else {
            $active = "";
        }

        $result = $this->conn->query(
            "select URL as url, count(distinct network_name) block_count
                from urls
            inner join url_categories on urls.urlID = url_categories.urlID
            inner join url_latest_status uls on uls.urlID=urls.urlID
            $filter_active
            where url_categories.category_id = ? and uls.status = 'blocked'
            group by url
            order by URL, network_name",
            array($parentid)
            );
        return $result;
    }

    function load_blocks_recurse($parentid, $page=0, $filter_active=0, $pagesize=20) {
        // get blocked sites that belong to a category (does not get sites of child categories)
        $row = $this->load($parentid);

        if ($filter_active) {
            $active = "block_count_active";
        } else {
            $active = "block_count_all";
        }

        $off = (int)$page * (int)$pagesize;
        $sql = "select URL as url, $active block_count,
                url_categories.category_id, substr(display_name, ?) category_title,
                last_reported
                from urls
            inner join url_categories on urls.urlID = url_categories.urlID
            inner join categories on categories.id = url_categories.category_id
            left join cache_block_count on cache_block_count.urlID = urls.urlID
            where ? @> tree and $active > 0
            order by URL limit 20 offset $off";
        error_log("SQL: $sql");
        $result = $this->conn->query(
            $sql, 
            array(strlen($row['display_name'])+2, $row['tree'])
            );
        return $result;
    }

    function search($name) {
        $q = $this->conn->query("select id, display_name, name 
            from categories where name_fts @@ to_tsquery(?)
            order by name",
            array($name . ":*")
            );
        return $q;
    }
}


class ISPReportLoader {
    function __construct($conn) {
        $this->conn = $conn;
    }

    function insert($name, $email, $urlID, $network_name, $message, $report_type, $send_updates, $contact_id) {
        $q = $this->conn->query("insert into isp_reports
        (name, email, urlID, network_name, message, report_type, send_updates, contact_id, created)
        values (?,?,?,?,?,?,?,?,now()) returning id as id",
        array($name, $email, $urlID, $network_name, $message, $report_type, $send_updates, $contact_id)
        );
        $row = $q->fetch();
        return $row['id'];
    }

    function load($id) {
        $res = $this->conn->query("select isp_reports.* from isp_reports
        where id = ?",
        array($id));

        $row = $res->fetch();
        return $row;
    }

    function can_report($urlID, $network_name) {
        $res = $this->conn->query("select count(*) from isp_reports
            where urlID = ? and network_name = ? and unblocked = 0",
            array($urlID, $network_name));
        $row = $res->fetch(PDO::FETCH_NUM);
        if ($row[0] == 0) {
            return true;
        } 
        return false;
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
				$url['urlid'],$probe['id'], $result['config'],$result['ip_network'],
				$result['status'],$result['http_status'], $result['network_name'],
				@$result['category'],@$result['blocktype']
			)
		);

		$this->conn->query(
			"update urls set polledSuccess = polledSuccess + 1 where urlID = ?",
			array($url['urlid'])
			);

		$this->probe_loader->updateRespRecv($probe['uuid']);
	}
}
