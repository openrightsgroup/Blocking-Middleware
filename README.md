# Blocked.org.uk Middleware

[API Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_API)

[Database Specification](https://wiki.openrightsgroup.org/wiki/Censorship_Monitoring_Project_DB)

## Using the docker development seutp

To start a development instance, install docker and docker-compose and run:

```
docker-compose --profile dev up
```
(requires docker-compose v1.29)

This will start containers for:
* The main API (on localhost:8080)
* Postgres database
* RabbitMQ messaging
* System daemons:
  * results recorder
  * whois checker
  * category importer
  * metadata gatherer
  * robots.txt checker
* Example web client (on localhost:8081)

You can use the example web client by visiting http://localhost:8081/example-client/

The postgres database is loaded with the main schema, as well as some example data to get started.


## Get involved!

We welcome new contributors especially - we hope you find getting involved both easy and fun. All you need to get started is a github account.

Please see our [issues repository](https://github.com/openrightsgroup/cmp-issues) for details on how to join in.

## Credits

We reused the following software components to make this:

- @ircmaxell's [password compatibility library](https://github.com/ircmaxell/password_compat) (MIT license).
- The [Symfony2](https://github.com/symfony/symfony) PHP web development framework (MIT license).
- The [Silex](https://github.com/silexphp/Silex) PHP micro-framework to develop websites based on Symfony2 components (MIT license).
- The [PostgreSQL](https://www.postgresql.org) database (PostgreSQL license).
- [php-amqp](http://pecl.php.net/package/amqp) from pecl (PHP license).
- [RabbitMQ](https://www.rabbitmq.com) (Mozilla Public License 1.1)

Thanks!
