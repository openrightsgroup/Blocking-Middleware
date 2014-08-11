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

def get_robots_url(url):
	parts = urlparse.urlparse(url)
	return urlparse.urlunparse( parts[:2] + ('/robots.txt','','','') )

def check_robots(msg):
	data = json.loads(msg.body)
	ch.basic_ack(msg.delivery_tag)

	url = get_robots_url(data['url'])
	logging.info("Using robots url: %s", url)
	try:
		robots_txt = requests.get(url, headers=HEADERS)
		rbp = robotparser.RobotFileParser()
		rbp.parse(robots_txt.text.splitlines())
		if not rbp.can_fetch(cfg.get('daemon','probe_useragent'), data['url']):
			logging.warn("Disallowed: %s", data['url'])
			# write rejection to DB
			c = conn.cursor()
			c.execute("""update urls set status = 'disallowed-by-robots-txt'
				where url = %s""", [ data['url'] ])
			c.close()
			conn.commit()
			return
		else:
			logging.info("Allowed: %s", data['url'])
	except Exception,v:
		logging.error("Exception: %s", v)

	msgsend = amqp.Message(msg.body)
	new_key = msg.routing_key.replace('check','url')
	ch.basic_publish(msgsend, 'org.blocked', new_key)
	logging.info("Message sent with new key: %s", new_key)
	return True

def main():
	global cfg, amqpconn, conn, ch

	requests_cache.install_cache('robots-txt',expire=86400)
	cfg = ConfigParser.ConfigParser()
	assert(len(cfg.read(['config.ini'])) == 1)

	mysqlopts = dict(cfg.items('mysql'))
	conn = MySQLdb.connect(**mysqlopts)

	HEADERS['User-agent'] = cfg.get('daemon','useragent')

	amqpopts = dict(cfg.items('amqp'))
	amqpconn = amqp.Connection( **amqpopts)
	ch = amqpconn.channel()
	ch.basic_consume(cfg.get('daemon','queue'), consumer_tag='checker1', callback=check_robots)
	while True:
		ch.wait()

if __name__ == '__main__':
	main()
