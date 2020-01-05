<?php

function debug_log($msg) {
    if (defined('DEBUG_LOG') {
        if (DEBUG_LOG) {
            error_log($msg);
        }
    }
}