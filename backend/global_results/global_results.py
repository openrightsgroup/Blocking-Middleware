#!/usr/bin/env python2

import os
import sys
import json
import logging
import ConfigParser

import amqplib.client_0_8 as amqp

from signing import RequestSigner
from api import APIRequest, StatusUrlRequest

logging.basicConfig(
	level=logging.INFO,
	format="%(asctime)s\t%(levelname)s\t%(message)s",
	datefmt="[%Y-%m-%d %H:%M:%S]",
	)

class GlobalResultChecker(object):
    def __init__(self, ch):
        self.ch = ch

    def global_check(self, msg):
        data = json.loads(msg.body)
        
        # msg contains: url, server, username, secret, resultqueue, country
        logging.info("Checking %s on %s; user=%s", data['url'], data['server'], data['username'])

        signer = RequestSigner(data['secret'])
        req = StatusUrlRequest(
            signer,
            email=data['username'],
            url=data['url']
            )
        req.host = data['server']

        status, response = req.execute()
        response['country'] = data['country']

        logging.info("Check of %s returns %s", data['server'], status)

        msgsend = amqp.Message(json.dumps(results))

        self.ch.basic_publish(msgsend, self.config.get('daemon','exchange'), 
            data['resultqueue'])
        logging.info("Reply sent to %s", data['resultqueue'])
    

def main():
	cfg = ConfigParser.ConfigParser()
	assert(len(cfg.read(['config.ini'])) == 1)

	# Create AMQP connection
	amqpopts = dict(cfg.items('amqp'))
	amqpconn = amqp.Connection( **amqpopts)
	ch = amqpconn.channel()

    checker = GlobalResultChecker(ch)

	# create consumer, enter mainloop
	ch.basic_consume(cfg.get('daemon','queue'), consumer_tag='global1', 
        callback=checker.global_check)
	while True:
		ch.wait()


if __name__ == '__main__':
    main()
