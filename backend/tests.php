<?php

include "ooni_test.php";
include "silex/vendor/autoload.php";

$STR1 = "---
agent: agent
body_length_match: null
body_proportion: null
control_failure: null
experiment_failure: null
factor: 0.8
headers_diff: !!set {}
headers_match: true
input: null
requests:
- failure: task_timed_out
  request:
    body: null
    headers:
    - - User-Agent
      - ['Mozilla/5.0 (Windows; U; Windows NT 6.1; de; rv:1.9.2) Gecko/20100115 Firefox/3.6']
    method: GET
    tor: {exit_ip: 46.165.221.166, exit_name: thoreau, is_tor: true}
    url: http://twc.com
  response:
    body: null
    code: 301
    headers:
    - - Content-Length
      - ['296']
    - - Set-Cookie
      - [TWC-COOKIE-%3Fwebapps%3Fwebcms-twc-sg=JAEHKIEE; Path=/]
    - - Expires
      - ['Fri, 22 May 2015 16:30:24 GMT']
    - - Server
      - [Apache]
    - - Location
      - ['http://www.timewarnercable.com/']
    - - Cache-Control
      - [max-age=1800]
    - - Date
      - ['Fri, 22 May 2015 16:00:24 GMT']
    - - Content-Type
      - [text/html; charset=iso-8859-1]
- failure: task_timed_out
  request:
    body: null
    headers:
    - - User-Agent
      - ['Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9.2) Gecko/20100115 Firefox/3.6']
    method: GET
    tor: {is_tor: false}
    url: http://twc.com
  response:
    body: null
    code: 301
    headers:
    - - Content-Length
      - ['296']
    - - Set-Cookie
      - [TWC-COOKIE-%3Fwebapps%3Fwebcms-twc-sg=JAEHKIEE; Path=/]
    - - Expires
      - ['Fri, 22 May 2015 16:30:25 GMT']
    - - Server
      - [Apache]
    - - Location
      - ['http://www.timewarnercable.com/']
    - - Cache-Control
      - [max-age=1800]
    - - Date
      - ['Fri, 22 May 2015 16:00:25 GMT']
    - - Content-Type
      - [text/html; charset=iso-8859-1]
socksproxy: null
test_runtime: 60.008007764816284
test_start_time: 1432306823.0
...";

$STR2 = "---
agent: agent
body_length_match: false
body_proportion: 0.04760061919504644
control_failure: null
experiment_failure: null
factor: 0.8
headers_diff: !!set {Location: null, X-Frame-Options: null}
headers_match: false
input: null
requests:
- request:
    body: null
    headers:
    - - User-Agent
      - ['Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.7) Gecko/20091221
          Firefox/3.5.7']
    method: GET
    tor: {is_tor: false}
    url: http://pornodingue.com
  response:
    code: 301
    headers:
    - - Transfer-Encoding
      - [chunked]
    - - Set-Cookie
      - ['__cfduid=da8c3f55aec66c4a38d2c94b535e5dad41432324903; expires=Sat, 21-May-16
          20:01:43 GMT; path=/; domain=.pornodingue.com; HttpOnly']
    - - Expires
      - ['-1']
    - - Server
      - [cloudflare-nginx]
    - - Connection
      - [close]
    - - Location
      - ['http://www.pornodingue.com/']
    - - Cache-Control
      - ['private, must-revalidate']
    - - Date
      - ['Fri, 22 May 2015 20:01:43 GMT']
    - - CF-RAY
      - [1eab1d5872bc13a1-LHR]
    - - Content-Type
      - [text/html; charset=iso-8859-1]
- request:
    body: null
    headers:
    - - User-Agent
      - ['Mozilla/5.0 (Windows; U; Windows NT 5.1; de; rv:1.9.2) Gecko/20100115 Firefox/3.6']
    method: GET
    tor: {exit_ip: 94.126.178.1, exit_name: kramse, is_tor: true}
    url: http://pornodingue.com
  response:
    code: 403
    headers:
    - - Transfer-Encoding
      - [chunked]
    - - Set-Cookie
      - ['__cfduid=d80d1df684c5de705faaeb2c450d2117b1432324903; expires=Sat, 21-May-16
          20:01:43 GMT; path=/; domain=.pornodingue.com; HttpOnly']
    - - Expires
      - ['Fri, 22 May 2015 20:01:45 GMT']
    - - Server
      - [cloudflare-nginx]
    - - Connection
      - [close]
    - - Cache-Control
      - [max-age=2]
    - - Date
      - ['Fri, 22 May 2015 20:01:43 GMT']
    - - X-Frame-Options
      - [SAMEORIGIN]
    - - Content-Type
      - [text/html; charset=UTF-8]
    - - CF-RAY
      - [1eab1d59a3c508b7-FRA]
socksproxy: null
test_runtime: 1.1878700256347656
test_start_time: 1432321302.0
...";


class ResultFilterTestCase extends PHPUnit_Framework_TestCase {
    function setUp() {
        global $STR1;
        $this->data = yaml_parse($STR1);
        global $STR2;
        $this->data2 = yaml_parse($STR2);
    }
    
    function testRedirect() {
        $this->assertEquals($this->data['agent'], 'agent');
        $this->assertNull($this->data['body_length_match']);
        $this->assertEquals(test_result($this->data),'ok');
    }
    function testCloudflare() {
        $this->assertEquals($this->data2['agent'], 'agent');
        $this->assertFalse($this->data2['body_length_match']);
        $this->assertEquals(test_result($this->data2),'unknown');
    }

}
