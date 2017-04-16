************************************
HTTP2 - Miscellaneous HTTP utilities
************************************
Static methods for doing miscellaneous HTTP related stuff like
date formatting, language negotiation or HTTP redirection.


Features
========
- Language negotiation
- MIME type negotiation
- Charset negotiation
- Send HEAD requests to URLs
- Make URLs absolute
- Parse HTTP ``Link:`` header values (RFC 5988)


Installation
============

PEAR
----
::

    $ pear install HTTP2

Composer
--------
::

    $ composer require pear/http2


Usage
=====

Language negotiation
--------------------
Quality factors in the ``Accept-Language:`` header are supported, e.g.::

    Accept-Language: en-UK;q=0.7, en-US;q=0.6, no, dk;q=0.8

Code::

    <?php
    require_once 'HTTP2.php';
    $http = new HTTP2();
    $langs = array(
        'en'    => 'locales/en',
        'en-US' => 'locales/en',
        'en-UK' => 'locales/en',
        'de'    => 'locales/de',
        'de-DE' => 'locales/de',
        'de-AT' => 'locales/de',
    );
    $neg = $http->negotiateLanguage($langs);
    $dir = $langs[$neg];
    ?>


Charset negotiation
-------------------
::

    <?php
    require_once 'HTTP2.php';
    $http = new HTTP2();
    $charsets = array(
        'UTF-8',
        'ISO-8859-1',
    );
    $charset = $http->negotiateCharset($charsets);
    ?>


MIME type negotiation
---------------------
::

    <?php
    require_once 'HTTP2.php';
    $http = new HTTP2();
    $contentType = array(
        'application/xhtml+xml',
        'application/xml',
        'text/html',
        'text/plain',
    );
    $mime = $http->negotiateMimeType($contentType);
    ?>


Links
=====
Homepage
  http://pear.php.net/package/HTTP2
Bug tracker
  http://pear.php.net/bugs/search.php?cmd=display&package_name[]=HTTP2
Documentation
  http://pear.php.net/package/HTTP2/docs
Unit test status
  https://travis-ci.org/pear/HTTP2

  .. image:: https://travis-ci.org/pear/HTTP2.svg?branch=master
     :target: https://travis-ci.org/pear/HTTP2
Packagist
  https://packagist.org/packages/pear/http2
