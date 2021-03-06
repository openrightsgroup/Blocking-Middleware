import re
import time
import psycopg2
import logging

import queuelib
from queuelib import QueueService

import urlparse
import requests

class SuspensionCategorizer(QueueService):
    QUEUE_NAME = 'suspensions'
    PATTERNS = {
        'MHRA': r'This domain has been suspended on advice from the Medicines and Healthcare products Regulatory Agency \(MHRA\)\.',
        'FCA': r'This domain has been suspended on request from the Financial Conduct Authority \(FCA\)\.',
        'PIPCU': r'This domain has been suspended on advice from the Police Intellectual.*Property Crime Unit \(PIPCU\)\.'
    }

    def setup_bindings(self):
        self.ch.queue_declare("suspensions", durable=True, auto_delete=False)
        self.ch.queue_bind("suspensions", "org.results", "results.#")
        self.session = requests.session()


    def get_category(self, content):
        for (name, pattern) in self.PATTERNS.items():
            if re.search(pattern, content, re.S|re.M):
                return name


    def process_message(self, data):
        if data.get('blocktype') != 'SUSPENSION':
            return True

        logging.info("Got result for %s", data['url'])

        req = self.session.get(data['url'])
        category = self.get_category(req.content)
        logging.info("Got category: %s", category)
        if not category:
            return True

        count = 0

        c = self.conn.cursor()
        c.execute("update url_latest_status "
                  "set category=%s "
                  "where urlid = (select urlid from urls where url = %s) "
                  "  and blocktype = 'SUSPENSION' ",
                  [category, data['url']])
        count += c.rowcount
        logging.info("Updated %s uls", count)
        c.close()
        self.conn.commit()
        return True


def main():
    queuelib.setup_logging()
    categorizer = SuspensionCategorizer()
    categorizer.run()

if __name__ == '__main__':
    main()
