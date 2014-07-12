Blocked.org.uk Middleware
=========================

[API Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_API)

[Database Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_DB)

Using the Vagrant VM Image
--------------------------

Download and install:

* [Oracle Virtualbox](https://www.virtualbox.org/wiki/Downloads)
* [Vagrant](https://www.vagrantup.com/downloads.html)

Obtain a git checkout of the Blocking Middleware repository, then run:

    cd /path/to/Blocking-Middleware
    vagrant up

This will set up and run the VM image. The initial download of the compressed filesystem image can take a few minutes (size: 500MB)

The resulting VM contains a webserver configured to service requests by running the PHP pages from your checkout.  A MySQL database and RabbitMQ instance will be created and configured in the VM when it is first booted.

You should then be able to execute API commands against your local running instance by
using the base URL [http://localhost:8080/1.2/](http://localhost:8080/1.2/)

The example client directory is accessible through the URL [http://localhost:8080/example-client/](http://localhost:8080/example-client/).

Get involved!
-------------

We welcome new contributors especially - we hope you find getting involved both easy and fun. All you need to get started is a github account.

Please see our [issues repository](https://github.com/openrightsgroup/cmp-issues) for details on how to join in.

Credits
-------
We reused the following software components to make this:

- @ircmaxell's [password compatibility library](https://github.com/ircmaxell/password_compat) (MIT license).
- The [Symfony2](https://github.com/symfony/symfony) PHP web development framework (MIT license).
- The [Silex](https://github.com/silexphp/Silex) PHP micro-framework to develop websites based on Symfony2 components (MIT license).
- The [MySQL community edition](https://www.mysql.com/products/community/) database (GPLv2 license).
- The [Vagrant](https://github.com/mitchellh/vagrant) tool for building and distributing development environments (MIT license).
- [php-amqp](http://pecl.php.net/package/amqp) from pecl (PHP license).

Thanks!
