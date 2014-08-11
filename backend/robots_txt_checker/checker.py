#!/usr/bin/env python2

import os
import sys
import json
import logging
import MySQLdb
import urlparse
import robotparser
import ConfigParser

import requests
import requests_cache

import amqplib.client_0_8 as amqp

logging.basicConfig(level=logging.INFO)

HEADERS = {}

"""
This daemon listens on a dedicated queue for URLs to check.  For each
received URL, the program attempts to fetch the robots.txt file on the
target domain.  If robots.txt indicates that a resource is not available
to spiders, the URL is dropped and the status is written back to the DB.

If robots.txt permits spidering of the target URL, the message is forwarded
to the regular per-isp queues.

The content of the robots.txt file for each domain is cached for <n> days 
(configurable)

This script was written in python to take advantage of the standard library's
robots.txt parser.
"""

def get_robots_url(url):
	"""Split URL, add /robots.txt resource"""
	parts = urlparse.urlparse(url)
	return urlparse.urlunparse( parts[:2] + ('/robots.txt','','','') )

def check_robots(msg):
	data = json.loads(msg.body)
	ch.basic_ack(msg.delivery_tag)

	# get the robots.txt URL
	url = get_robots_url(data['url'])
	logging.info("Using robots url: %s", url)
	try:
		# fetch robots.txt
		robots_txt = requests.get(url, headers=HEADERS)
		# pass the content to the robots.txt parser
		rbp = robotparser.RobotFileParser()
		rbp.parse(robots_txt.text.splitlines())

		# check to see if we're allowed in - test using OrgProbe's useragent
		if not rbp.can_fetch(cfg.get('daemon','probe_useragent'), data['url']):
			logging.warn("Disallowed: %s", data['url'])
			# write rejection to DB
			c = conn.cursor()
			c.execute("""update urls set status = 'disallowed-by-robots-txt'
				where url = %s""", [ data['url'] ])
			c.close()
			conn.commit()
			return True
		else:
			# we're allowed in.
			logging.info("Allowed: %s", data['url'])
	except Exception,v:
		# if anything bad happens, log it but continue
		logging.error("Exception: %s", v)

	# pass the message to the regular location
	msgsend = amqp.Message(msg.body)
	new_key = msg.routing_key.replace('check','url')
	ch.basic_publish(msgsend, cfg.get('daemon','exchange'), new_key)
	logging.info("Message sent with new key: %s", new_key)
	return True

def main():
	global cfg, amqpconn, conn, ch

	# set up cache for robots.txt content
	requests_cache.install_cache('robots-txt',expire=cfg.getint('daemon','cache_ttl'))
	cfg = ConfigParser.ConfigParser()
	assert(len(cfg.read(['config.ini'])) == 1)

	# create MySQL connection
	mysqlopts = dict(cfg.items('mysql'))
	conn = MySQLdb.connect(**mysqlopts)

	HEADERS['User-agent'] = cfg.get('daemon','useragent')

	# Create AMQP connection
	amqpopts = dict(cfg.items('amqp'))
	amqpconn = amqp.Connection( **amqpopts)
	ch = amqpconn.channel()

	# create consumer, enter mainloop
	ch.basic_consume(cfg.get('daemon','queue'), consumer_tag='checker1', callback=check_robots)
	while True:
		ch.wait()

if __name__ == '__main__':
	main()
