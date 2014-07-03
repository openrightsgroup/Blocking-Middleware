<?php

include "url.php";

print normalize_url("http://www.example.com/");
print "\n";
print normalize_url("http://www.example.com");
print "\n";
print normalize_url("http://www.example.com/bar");
print "\n";
print normalize_url("http://www.example.com/bar/");
print "\n";
