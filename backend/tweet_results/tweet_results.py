#!/usr/bin/env python

import os
import sys
import json
import logging
import urllib
import collections
import ConfigParser

import amqplib.client_0_8 as amqp
import twitter

# setup logging to output stream

logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s\t%(levelname)s\t%(message)s",
        datefmt="[%Y-%m-%d %H:%M:%S]",
        )

# read the config file

cfg = ConfigParser.ConfigParser()
assert(len(cfg.read(['config.ini'])) == 1)

# get twitter options and setup API

twopts=dict(cfg.items('twitter'))
api = twitter.Api(
	consumer_key=twopts['consumerkey'],
	consumer_secret=twopts['secretkey'],
	access_token_key=twopts['accesstoken'], 
	access_token_secret=twopts['accesstokensecret']
	)

# optionally verify credentials
##user = api.VerifyCredentials()

# set up AMQP connection
amqpopts = dict(cfg.items('amqp'))
amqpconn = amqp.Connection( **amqpopts)
ch = amqpconn.channel()

# declare a temporary queue - we don't want to gather results
# while the daemon is offline, because this would flood the twitter feed
queue = ch.queue_declare(amqpopts['queue'])
ch.queue_bind(amqpopts['queue'], amqpopts['exchange'], amqpopts['routing_key'])

# selection of status (ok,blocked) which will be tweeted
statuses = cfg.get('selection','status').split(',')

def recv(msg):
	"""Callback for handling results notifications from the probes"""
	# decode the message
	data = json.loads(msg.body)

	if data['status'] in statuses:
		try:
			logging.info("Sending tweet: %s is %s on %s", data['url'], data['status'], data['network_name'])
			url = cfg.get('selection','blockedurl') + urllib.quote(data['url'])
			content = "{d[url]} is {d[status]} on {d[network_name]} ({url})".format(d=data, url=url)

			# do the actual twitter post
			post = api.PostUpdate(content)
			logging.info("Post sent: %s", post.id)
		except Exception,exc:
			logging.error("Exception: %s (%s)", str(exc), repr(exc))

# consumer loop.  Messages are auto-acknowledged.
ch.basic_consume(amqpopts['queue'], callback=recv, no_ack=True)
while True:
	ch.wait()

