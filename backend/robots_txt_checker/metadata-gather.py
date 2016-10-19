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
import bs4

import amqplib.client_0_8 as amqp
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s\t%(levelname)s\t%(message)s",
    datefmt="[%Y-%m-%d %H:%M:%S]",
    )

"""
This daemon listens on a dedicated queue for URLs fetch, and extracts metadata from the HTML.

The metadata is saved to the site_description table.

"""
class MetadataGatherer(object):
    def __init__(self, config, conn, ch):
        self.config = config
        self.conn = conn
        self.ch = ch
        self.headers = {'User-agent': self.config.get('daemon','useragent')}

    def save_description(self, url, data):
        c = self.conn.cursor()
        #c.execute("update urls set description = %s where url = %s", )
        c.execute("""insert into site_description(urlid, created, description) 
            select urlID, now(), %s from urls where url = %s""",
                [json.dumps(data), url]
            )
        c.close()
        self.conn.commit()

    def get_metadata(self,msg):
        data = json.loads(msg.body)
        self.ch.basic_ack(msg.delivery_tag)
        # now fetch the page to extract data
        try:
            descdata = {}
            req2 = requests.get(data['url'], headers=self.headers)
            if req2.headers.get('content-type').startswith('text/html'):
                doc = bs4.BeautifulSoup(req2.content)

                try:
                    descdata['title'] = doc.find('title').text
                except Exception,v:
                    logging.info("Unable to extract title: %s", repr(v))

                for field in ('keywords','description','twitter:site'):
                    try:
                        descdata[field] = doc.find('meta', {'name':field})['content']
                    except Exception,v:
                        logging.info("Unable to extract %s: %s", field, repr(v))

                for field in ('og:title','og:description'):
                    try:
                        descdata[field] = doc.find('meta', {'property':field})['content']
                    except Exception,v:
                        logging.info("Unable to extract %s: %s", field, repr(v))

                self.save_description(data['url'], descdata)
        except Exception,v:
            logging.warn("Error in page data retrieval: %s", repr(v))

        return True

def main():

    # set up cache for robots.txt content
    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['config.ini'])) == 1)

    # create MySQL connection
    mysqlopts = dict(cfg.items('mysql'))
    conn = MySQLdb.connect(**mysqlopts)

    # Create AMQP connection
    amqpopts = dict(cfg.items('amqp'))
    amqpconn = amqp.Connection( **amqpopts)
    ch = amqpconn.channel()

    gather = MetadataGatherer(cfg, conn, ch)

    ch.queue_declare("metadata", durable=True)
    ch.queue_bind("metadata", "org.blocked", "url.org")
    ch.queue_bind("metadata", "org.blocked", "url.public")

    # create consumer, enter mainloop
    ch.basic_consume("metadata", consumer_tag='metadata1', callback=gather.get_metadata)
    while True:
        ch.wait()
