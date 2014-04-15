[![Stories in Ready](https://badge.waffle.io/openrightsgroup/blocking-middleware.png?label=ready&title=Ready)](https://waffle.io/openrightsgroup/blocking-middleware)
Middleware
==========

[API Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_API) 

Using the Vagrant VM Image
--------------------------

Download and install:

* [Oracle Virtualbox](https://www.virtualbox.org/wiki/Downloads)
* [Vagrant](https://www.vagrantup.com/downloads.html)

Obtain a git checkout of the Blocking Middleware repository, then run:

    cd /path/to/Blocking-Middleware
    vagrant up

This will set up and run the VM image. The initial download of the compressed filesystem image can take a few minutes (size: 300MB)

The resulting VM contains a webserver configured to service requests by running the PHP pages from your checkout.  A ready-configured MySQL database is already running in the VM.

You should then be able to execute API commands against your local running instance by
using the base URL [http://localhost:8080/api/1.2/](http://localhost:8080/api/1.2/)


