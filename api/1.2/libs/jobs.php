<?php

function update_jobs($conn, $id, $message) {
    $q = $conn->query("delete from jobs where id = ?", array($id));
    $q = $conn->query("insert into jobs(id, updated, message) values (?,now(),?)", array($id, $message));

}