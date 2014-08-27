#!/bin/bash

if [ ! -d /var/lib/mysql/bowdlerize ]
then
	mysql -uroot -e "create database bowdlerize;"
	mysql -uroot bowdlerize < /vagrant/mysql/structure.sql
	mysql -uroot bowdlerize < /vagrant/mysql/example-init.sql
fi

if [ ! -f /vagrant/api/1.2/libs/config.php ]
then
	cp /vagrant/api/1.2/libs/config.example.php /vagrant/api/1.2/libs/config.php
fi

if [ ! -f /vagrant/example-client/credentials.php ]
then
	cp /vagrant/example-client/credentials.example.php /vagrant/example-client/credentials.php
fi

