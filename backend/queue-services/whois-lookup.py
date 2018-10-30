#!/usr/bin/env python2

import sys
import logging
import psycopg2
import resource
import urlparse
import subprocess

import queuelib
from queuelib import QueueService


"""
This daemon listens on a dedicated queue for URLs fetch, and saves the whois expiry date

"""

class WhoisLookup(QueueService):
    AGE = 180
    QUEUE_NAME = 'whois'

    def __init__(self):
        super(WhoisLookup, self).__init__()
        self.count = 0

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        self.ch.queue_bind(self.QUEUE_NAME, "org.blocked", "url.org")
        #self.ch.queue_bind(self.QUEUE_NAME, "org.blocked", "url.public")

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
        ret = None
        for line in proc.stdout:
            logging.debug("Line: %s", line.strip())
            if not ': ' in line:
                continue
            field, value = line.strip().split(': ')
            if field in ('Registry Expiry Date','Expiry date'):
                ret = value.strip()
        rc = proc.wait()
        return ret

    def save_expiry(self, url, expiry):
        c = self.conn.cursor()
        logging.info("Saving expiry: %s for url: %s", expiry, url)
        c.execute("update urls set whois_expiry = %s, whois_expiry_last_checked = now() where url = %s", [expiry, url] )
        c.close()

    def process_message(self,data):
        # now fetch the page to extract data
        try:
            if self.check_expiry_cache(data['url']):
                parts = urlparse.urlparse(data['url'])
                domain = parts.netloc

                expiry = self.get_domain_expiry(domain)

                self.save_expiry(data['url'], expiry)
        except Exception,v:
            logging.warn("Error in page data retrieval: %s", repr(v))
        self.conn.commit()

        self.count += 1
        logging.info("URL: %s; rss: %s; count: %s", data['url'], resource.getrusage(0).ru_maxrss, self.count)

        if self.count > 500:
            logging.warn("Exiting after 500 requests")
            sys.exit(0)

        return True

def main():
    queuelib.setup_logging()
    whois = WhoisLookup()
    whois.run()

if __name__ == '__main__':
    main()
