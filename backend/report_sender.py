import os
import sys
import json
import logging
import MySQLdb
import urlparse
import ConfigParser

import requests

import amqplib.client_0_8 as amqp

logging.basicConfig(
	level=logging.INFO,
	format="%(asctime)s\t%(levelname)s\t%(message)s",
	datefmt="[%Y-%m-%d %H:%M:%S]",
	)

class ReportsSender(object):
    def __init__(self, config, conn, ch):
        self.config = config
        self.conn = conn
        self.ch = ch

    def send_report(self, msg):
		self.ch.basic_ack(msg.delivery_tag)

        c = self.conn.cursor()
        c.execute("select * from reports where id = %s",
            [msg])
        report = c.fetchone()
        
        if not data['complete']:
            logging.error("Report not complete: %s", msg)
            return True

        # send report
        req = requests.post(self.config.get('ooni','base_url'),
            data=report['data'])
        upstream_id = req.json()['report_id']
        logging.info("Got upstream: %s", upstream_id)

        c.execute("select report_entries.* from report_entries where report_id=%s",
            [report['id']])
        for entry in c:
            req2 = requests.put(self.config.get('ooni','base_url'),
                data={'report_id': upstream_id, 'content': entry['data']})

            logging.info("Sent entry: %s, report: %s, upstream: %s",
                entry['id'], report['id'], upstream_id)


        req3 = requests.post(
            self.config.get('ooni','base_url') + '/{}/close'.format(upstream_id))
        logging.info("Close: %s", req3.status_code)

        return True

        

def main():

	cfg = ConfigParser.ConfigParser()
	assert(len(cfg.read(['report_sender.ini'])) == 1)


	# create MySQL connection
	mysqlopts = dict(cfg.items('mysql'))
	conn = MySQLdb.connect(**mysqlopts)

	# Create AMQP connection
	amqpopts = dict(cfg.items('amqp'))
	amqpconn = amqp.Connection( **amqpopts)
	ch = amqpconn.channel()

	sender = ReportsSender(config, conn, ch)

	# create consumer, enter mainloop
	ch.basic_consume(cfg.get('daemon','queue'), consumer_tag='sender1', 
        callback=sender.send_report)

	while True:
		ch.wait()

if __name__ == '__main__':
	main()
