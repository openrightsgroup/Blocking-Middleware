#!/usr/bin/python

import os
import sys
import hmac
import json
import time
import random
import hashlib
import logging
import datetime

import ConfigParser
import amqplib.client_0_8 as amqp

SIG_KEYS = ["probe_uuid", "url", "status", "date", "config"]

logging.basicConfig(
	level=logging.INFO,
	format="%(asctime)s\t%(levelname)s\t%(message)s",
	datefmt="[%Y-%m-%d %H:%M:%S]",
	)

cfg = ConfigParser.ConfigParser()
cfg.read(['fakeprobe.ini'])


def sign(self, *args):
	msg = ':'.join([str(x) if not isinstance(x, (str,unicode)) else x for x in args])
	logging.debug("Using signature string: %s", msg)
	hm = hmac.new(cfg.get('probe','secret'), msg, hashlib.sha512)
	return hm.hexdigest()

def recvmsg(msg):
	data = json.loads(msg.body)
	ch.basic_ack(msg.delivery_tag)
	logging.info("Got URL: %s", data['url'])
	time.sleep(random.randint(10,50)/10.0)
	urlhash = data['hash']

	if random.randint(0,1) == 0:
		result = 'blocked'
	else:
		result = 'ok'
	logging.info("Result: %s", result)
	
	report = {
		'network_name': cfg.get('probe','network'),
		'ip_network': '127.0.1.1',
		'url': data['url'],
		'http_status': 200,
		'status': result,
		'probe_uuid': cfg.get('probe','uuid'),
		'config': 1,
		'category': '',
		'blocktype': '',
	}
	report['date'] = datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
	report['signature'] = sign(report, SIG_KEYS)

	msgbody = json.dumps(report)
	msgout = amqp.Message(msgbody)
	key = 'results.'+cfg.get('probe','network')+ ('.'+urlhash if urlhash is not None else '')
	logging.info("Sending result with key: %s", key)
	ch.basic_publish(msgout, cfg.get('probe','results'), key)

amqpopts = dict(cfg.items('amqp'))
amqpconn = amqp.Connection(**amqpopts)
ch = amqpconn.channel()
ch.basic_consume('url.'+cfg.get('probe','network')+'.org', consumer_tag='checker1', callback=recvmsg)
while True:
	ch.wait()
