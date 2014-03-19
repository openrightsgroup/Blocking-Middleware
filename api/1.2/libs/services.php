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
			"update probes set probeReqSent=probeReqSent+1,lastSeen where uuid=?",
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
			"select * from tempURLs where URL=?",
			array($url)
			);
		if ($result->num_rows == 0) {
			throw new UrlLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}

	function get_next_old() {
		$result = $this->conn->query("select tempID,URL,hash from tempURLs where lastPolled is null or lastPolled < date_sub(now(), interval 12 hour) ORDER BY lastPolled ASC,polledAttempts DESC LIMIT 1", array());
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
			"select URL, urlID, queue.id from tempURLs
			inner join queue on queue.urlID = tempURLs.tempID
			where queue.ispID = ? and (lastSent < date_sub(now(), interval 1 day) or lastSent is null)
			order by queue.results, queue.lastSent
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
			"update tempURLs set lastPolled = now(), polledAttempts = polledAttempts + 1 where tempID = ?",
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
			"select * from isps where name = ?",
			array($ispname)
			);
		if (!$result) {
			throw new IspLookupError();
		}
		$row = $result->fetch_assoc();
		return $row;
	}

	function create($name) {
		$result = $this->conn->query(
			"insert ignore into isps(name,created) values (?, now())",
			array($name)
			);
		if (!$result) {
			throw new DatabaseError();
		}
	}
}

