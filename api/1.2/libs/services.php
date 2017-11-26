<?php

class UserLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($email) {
		$result = $this->conn->query(
			"select id,secret,probehmac,status,administrator from users where email = ?",
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
            "insert into urls (URL, hash, source, tags, lastPolled, inserted) values (?,?,?, makearray(?), now(), now() )",
            array($url, md5($url), $source, $source)
        );
        /* returns true/false for whether a row was really inserted. */

        if ($result->rowCount()) {
            return true;
        } else {
            // add tag if not already listed for this URL
            $this->conn->query("update urls set tags = array_append(tags, ?) where not makearray(?) && tags and url = ?",
                array($source, $source, $url));
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
			"select *, fmtime(last_reported) last_reported from urls where URL=?",
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
            order by random() limit " . (int)$count,
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
                $joinlist ? 1 : 0
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
            "update isp_cache set network=?, created = now() where ip = ?",
            array($network, $ip)
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

    function load_block($urlid, $filter_active=0) {
        if ($filter_active) {
            $active = "block_count_active";
        } else {
            $active = "block_count_all";
        }

        $result = $this->conn->query("select URL as url, $active block_count,
                fmtime(last_reported) last_reported, title
                from urls
            inner join url_categories on urls.urlID = url_categories.urlID
            left join cache_block_count on cache_block_count.urlID = urls.urlID
            where urls.urlid = ?
            ",
            array($urlid)
            );
        $data = $result->fetch();
        return $data;

    }

    function load_blocks($parentid, $filter_active=0) {
        // get blocked sites that belong to a category (does not get sites of child categories)
        if ($filter_active) {
            $active = "inner join isps on uls.network_name = isps.name and isps.queue_name is not null";
        } else {
            $active = "";
        }

        $result = $this->conn->query(
            "select URL as url, count(distinct network_name) block_count, title
                from urls
            inner join url_categories on urls.urlID = url_categories.urlID
            inner join url_latest_status uls on uls.urlID=urls.urlID
            $active
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
                fmtime(last_reported) last_reported, title
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

    function search($name, $results=10) {
        $q = $this->conn->query("select id, display_name, name, total_blocked_url_count
            from categories where name_fts @@ ?
            order by total_blocked_url_count desc, name " . ($results ? "limit " . (int)$results : ''),
            array(strtolower($name) . ":*")
            );
        return $q;
    }

    function random($count=1) {
        $q = $this->conn->query("select id, display_name, name, total_blocked_url_count
            from categories where total_blocked_url_count > 0
            order by random() limit " . (int)$count, array());


        return $q;
    }
}


class ISPReportLoader {
    function __construct($conn) {
        $this->conn = $conn;
    }

    function insert($name, $email, $urlID, $network_name, $message, $report_type, $send_updates, $contact_id, $allow_publish, $status) {
        $q = $this->conn->query("insert into isp_reports
        (name, email, urlID, network_name, message, report_type, send_updates, contact_id, allow_publish, status, created)
        values (?,?,?,?,?,?,?,?,?,?,now()) returning id as id",
        array($name, $email, $urlID, $network_name, $message, $report_type, $send_updates, $contact_id, $allow_publish, $status)
        );
        $row = $q->fetch();
        if ($status == 'sent') {
            $this->update_submitted($row['id']);
        }
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

    function set_status($reportid, $status, $last_updated=null) {
        if (!is_null($last_updated)) {
            $this->conn->query("update isp_reports set status = ?, last_updated = ? where id = ?",
                array($status, $last_updated, $reportid)
                );
        } else {
            $this->conn->query("update isp_reports set status = ? where id = ?",
                array($status,  $reportid)
                );
        }
        if ($status == 'sent') {
            $this->update_submitted($reportid);
        }
        error_log("Updated status: report={$reportid}; status={$status}");

    }

    function update_submitted($reportid) {
        $this->conn->query("update isp_reports set submitted = now() where id = ?",
            array($reportid)
            );
    }


    function get_unreported($urlID) {
        $res = $this->conn->query("select network_name
            from url_latest_status left join isp_reports using(urlid, network_name)
            where
                url_latest_status.urlid = ?
                and url_latest_status.status = 'blocked'
                and (isp_reports.id is null or isp_reports.unblocked = 1)",
            array($urlID));
        $networks = array();
        foreach ($res as $row) {
            $networks[] = $row['network_name'];
        }
        return $networks;

    }

    function get_url_reports($urlid) {
        $res = $this->conn->query("select id, network_name, created, report_type
            from isp_reports
            where urlid = ?
            order by network_name",
            array($urlid)
            );
        $reports = array();
        foreach($res as $row) {
            $reports[] = $row;
        }
        return $reports;
    }

    function get_reports($type, $network=null, $page=0, $pagesize=25) {

        $off = ((int)$page) * $pagesize;

        $args = array($type);
        if ($network) {
            $args[] = $network;
            $network_clause = " AND network_name = ?";
        } else {
            $network_clause = "";
        }

        $res = $this->conn->query("select
            url, network_name, fmtime(isp_reports.created) as created, unblocked, fmtime(isp_reports.submitted) as submitted, isps.description as description
            from isp_reports
            inner join urls using(urlid)
            inner join isps on isps.name = isp_reports.network_name
            where report_type = ? $network_clause and isp_reports.status not in ('cancelled','abuse')
            order by isp_reports.created desc
            limit $pagesize offset $off",
            $args
            );
        $reports = array();
        foreach ($res as $row) {
            $reports[] = $row;
        }
        return $reports;
    }

    function count_reports($type, $network=null) {
        $args = array($type);
        if ($network) {
            $args[] = $network;
            $network_clause = " AND network_name = ?";
        } else {
            $network_clause = "";
        }
        $res = $this->conn->query("select
            count(*)
            from isp_reports
            inner join urls using(urlid)
            where report_type = ? $network_clause  and isp_reports.status not in ('cancelled','abuse')",
            $args
            );
        return $res->fetchColumn(0);
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
			"insert into results(urlID,probeID,config,ip_network,status,http_status,network_name, category, blocktype, created,
            title, remote_ip, ssl_verified, ssl_fingerprint, request_id, final_url)
			values (?,?,?,?,?,?,?,?,?,now(),?,?,?,?,?,?)",
			array(
				$url['urlid'],$probe['id'], $result['config'],$result['ip_network'],
				$result['status'],$result['http_status'], $result['network_name'],
				@$result['category'],@$result['blocktype'],
                @$result['title'],@$result['remote_ip'],@$result['ssl_verified'],@$result['ssl_fingerprint'],@$result['request_id'],
                @$result['final_url']
			)
		);

		$this->conn->query(
			"update urls set polledSuccess = polledSuccess + 1 where urlID = ?",
			array($url['urlid'])
			);

		$this->probe_loader->updateRespRecv($probe['uuid']);
	}
}

class AMQPQueueService {
    function __construct($amqp, $submit_routing_key) {
        $this->amqp = $amqp;
        $this->submit_routing_key = $submit_routing_key;
    }

    function publish_url($urltext, $target_queue=null, $request_id=null) {
        $msgbody = json_encode(array(
            'url'=>$urltext,
            'hash'=>md5($urltext),
            'request_id'=>$request_id
            ));

        if (is_null($target_queue)) {
            $target_queue = $this->submit_routing_key;
        }

        $ch = $this->amqp;
        $ex = new AMQPExchange($ch);
        $ex->setName('org.blocked');
        $ex->publish(
            $msgbody, $target_queue, AMQP_NOPARAM,
            array('priority'=>2)
        );
    }
}


class ElasticService {
    function __construct($addr) {
        $this->addr = $addr;
    }

    function query($term, $index = '', $sort=null, $page=0, $pagesize=20, $exclude_adult=0) {
        if ($exclude_adult) {
            $query_string = trim($term) . " AND NOT (" . ELASTIC_ADULT_FILTER . ")";
        } else {
            $query_string = trim($term);
        }
        $search = array(
            'query' => array(
                'query_string' => array(
                    'query' => $query_string
                )
            )
        );
        if ($sort) {
            $search['sort'] = $sort;
        }
        $search['from'] = $page * $pagesize;
        $search['size'] = $pagesize;
        $req = new HTTP_Request2($this->addr . $index . '/_search');
        $rsp = $req->setMethod(HTTP_Request2::METHOD_POST)
            ->setBody(json_encode($search))
            ->setHeader('Content-type: application/json')
            ->send();

        $data = json_decode($rsp->getBody());
        $out = new stdClass();
        $out->results = array();
        foreach($data->hits->hits as $hit) {
            $out->results[] = $hit->_source;
        }
        $out->count = $data->hits->total;
        return $out;

    }

    function urls_by_category($catid, $page=0, $pagesize=20) {
        $search = array(
            'query' => array(
                'match' => array(
                    'categories' => $catid
                )
            )
        );
        $search['from'] = $page*$pagesize;
        $search['size'] = $pagesize;

        $req = new HTTP_Request2($this->addr .  '/urls/_search');
        $rsp = $req->setMethod(HTTP_Request2::METHOD_POST)
            ->setBody(json_encode($search))
            ->setHeader('Content-type: application/json')
            ->send();

        $data = json_decode($rsp->getBody());

        $out = new stdClass();
        $out->results = array();
        foreach($data->hits->hits as $hit) {
            $out->results[] = $hit->_source;
        }
        $out->count = $data->hits->total;

        return $out;



    }

    function delete($urlid) {
        $url = $this->addr .  '/urls/url/'.$urlid;
        $req = new HTTP_Request2($url);
        $rsp = $req->setMethod(HTTP_Request2::METHOD_DELETE)
            ->send();
    }

}

class BlacklistLoader {
    function __construct($conn) {
        $this->conn = $conn;
    }

    function insert($domain) {
        $res = $this->conn->query("insert into domain_blacklist(domain, created) values (?, now())",
            array($domain)
            );
        return true;
    }

    function delete($domain) {
        $res = $this->conn->query("delete from domain_blacklist where domain = ?",
            array($domain)
            );
        return true;
    }

    function select() {
        $res = $this->conn->query("select domain from domain_blacklist order by domain");
        $out = array();
        foreach($res as $row) {
            $out[] = $row['domain'];
        }
        return $out;
    }

    function check($url) {
        // check url against the domain blacklist.  Return true if match found
        $res = $this->conn->query("select domain from domain_blacklist where ? ~* domain",
            array($url)
            );
        $row = $res->fetch();
        if ($row !== false) {
            // found a matching row
            return true;
        }
        return false;
    }
}
