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

print categorize_url("http://www.example.com")."\n";
print categorize_url("http://example.com")."\n";
print categorize_url("http://example.co.uk")."\n";
print categorize_url("http://example.com.hk")."\n";
print categorize_url("http://www.example.co.uk")."\n";
print categorize_url("http://www.example.co.uk/something")."\n";
