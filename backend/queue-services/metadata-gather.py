#!/usr/bin/env python2

import sys
import json
import logging
import resource

import queuelib
from queuelib import QueueService

import requests
import bs4


"""
This daemon listens on a dedicated queue for URLs fetch, and extracts metadata from the HTML.

The metadata is saved to the site_description table.

"""

class MetadataGatherer(QueueService):
    QUEUE_NAME = 'metadata'
    def __init__(self):
        super(MetadataGatherer, self).__init__()
        self.count = 0
        self.headers = {'User-agent': self.config.get('useragent')}

    def save_description(self, url, data):
        c = self.conn.cursor()
        #c.execute("update urls set description = %s where url = %s", )
        c.execute("""insert into site_description(urlid, created, description) 
            select urlID, now(), %s from urls where url = %s""",
                [json.dumps(data), url]
            )
        if data.get('title'):
            c.execute("update urls set title=%s where url = %s",
                [data['title'], url])
        c.close()
        self.conn.commit()

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        self.ch.queue_bind(self.QUEUE_NAME, "org.blocked", "url.org")
        self.ch.queue_bind(self.QUEUE_NAME, "org.blocked", "url.public")

    def process_message(self,data):
        # now fetch the page to extract data
        try:
            descdata = {}
            req2 = requests.get(data['url'], headers=self.headers, timeout=5)
            if req2.headers.get('content-type').startswith('text/html'):
                doc = bs4.BeautifulSoup(req2.content)

                try:
                    descdata['title'] = doc.find('title').text
                except Exception,v:
                    logging.debug("Unable to extract title: %s", repr(v))

                for field in ('keywords','description','twitter:site'):
                    try:
                        descdata[field] = doc.find('meta', {'name':field})['content']
                    except Exception,v:
                        logging.debug("Unable to extract %s: %s", field, repr(v))

                for field in ('og:title','og:description'):
                    try:
                        descdata[field] = doc.find('meta', {'property':field})['content']
                    except Exception,v:
                        logging.debug("Unable to extract %s: %s", field, repr(v))

                self.save_description(data['url'], descdata)
        except Exception,v:
            logging.warn("Error in page data retrieval: %s", repr(v))

        self.count += 1
        logging.info("URL: %s; rss: %s; count: %s", data['url'], resource.getrusage(0).ru_maxrss, self.count)

        if self.count > 500:
            logging.warn("Exiting after 500 requests")
            sys.exit(0)

        return True

def main():
    queuelib.setup_logging()
    gather = MetadataGatherer()
    gather.run()

if __name__ == '__main__':
    main()
