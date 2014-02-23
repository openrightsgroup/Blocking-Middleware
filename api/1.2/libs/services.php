<?php

class UserLoader {
	function __construct($conn) {
		$this->conn = $conn;
	}

	function load($email) {
		$result = $this->conn->query(
			"select id,secret,probeHMAC,status from users where email = ?",
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
			"select secret from probes where probeUUID=?",
			array($probe_uuid)
			);
		if ($result->numrows == 0) {
			throw new ProbeLookupError();
		}
		$row = $result->fetchrow_assoc();
		return $row;
	}
};
