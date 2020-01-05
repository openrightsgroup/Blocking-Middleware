<?php

function debug_log($msg) {
    if (DEBUG_LOG) {
        error_log($msg);
    }
}