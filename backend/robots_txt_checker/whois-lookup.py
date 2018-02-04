#!/usr/bin/env python2

import os
import sys
import json
import logging
import psycopg2
import resource
import urlparse
import subprocess
import ConfigParser

import requests

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
class WhoisLookup(object):
    AGE = 180
    def __init__(self, config, conn, ch):
        self.config = config
        self.conn = conn
        self.ch = ch
        self.count = 0

    def check_expiry_cache(self, url):
        """Returns True if cache is expired"""
        c = self.conn.cursor()
        c.execute("""select whois_expiry_last_checked 
                     from urls
                     where url = %s and (whois_expiry is null or whois_expiry_last_checked < now() - interval '{0} DAY')""".format(self.AGE),
                     [url])
        row = c.fetchone()
        c.close()
        if row is None:
            return False
        else:
            return True

    def get_domain_expiry(self, domain):
        proc = subprocess.Popen(['/usr/bin/whois', domain], stdout=subprocess.PIPE)
        for line in proc.stdout:
            if not ': ' in line:
                continue
            field, value = line.strip().split(': ')
            if field == 'Registry Expiry Date':
                ret = value
        rc = proc.wait()
        if rc == 0:
            return ret
        raise RuntimeError(rc)

    def save_expiry(self, url, expiry):
        c = self.conn.cursor()
        c.execute("update urls set whois_expiry = %s, whois_expiry_last_checked = now() where url = %s", [expiry, url] )
        c.close()
        self.conn.commit()

    def get_expiry(self,msg):
        data = json.loads(msg.body)
        self.ch.basic_ack(msg.delivery_tag)
        # now fetch the page to extract data
        try:
            if self.check_expiry_cache(data['url']):
                parts = urlparse.urlparse(data['url'])
                domain = parts.netloc

                expiry = self.get_domain_expiry(domain)

                self.save_expiry(data['url'], expiry)
        except Exception,v:
            logging.warn("Error in page data retrieval: %s", repr(v))

        self.count += 1
        logging.info("URL: %s; rss: %s; count: %s", data['url'], resource.getrusage(0).ru_maxrss, self.count)

        if self.count > 500:
            logging.warn("Exiting after 500 requests")
            sys.exit(0)

        return True

def main():

    # set up cache for robots.txt content
    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['config.ini'])) == 1)

    # create MySQL connection
    pgopts = dict(cfg.items('db'))
    conn = psycopg2.connect(**pgopts)

    # Create AMQP connection
    amqpopts = dict(cfg.items('amqp'))
    amqpconn = amqp.Connection( **amqpopts)
    ch = amqpconn.channel()
    ch.basic_qos(0,10,False)

    whois = WhoisLookup(cfg, conn, ch)

    ch.queue_declare("whois", durable=True, auto_delete=False)
    ch.queue_bind("whois", "org.blocked", "url.org")
    ch.queue_bind("whois", "org.blocked", "url.public")

    # create consumer, enter mainloop
    ch.basic_consume("whois", consumer_tag='whois1', callback=whois.get_expiry)
    while True:
        ch.wait()

if __name__ == '__main__':
    main()
