
import psycopg2
import logging

import queuelib
from queuelib import QueueService

import urlparse
import requests

class CategoryImporter(QueueService):
    CATEGORIFY_API = 'https://categorify.org/api'
    QUEUE_NAME = 'category'

    def setup_bindings(self):
        self.ch.queue_declare("category", durable=True, auto_delete=False)
        self.ch.queue_bind("category", "org.blocked", "url.org")
        #self.ch.queue_bind("category", "org.blocked", "url.public")

    def process_message(self, data):
        url = data['url']
        parsed_url = urlparse.urlparse(url)
        domain = parsed_url.netloc.lower()
        if domain.startswith('www.'):
            domain = domain.split('.', 1)[-1]

        req = requests.get(self.CATEGORIFY_API, params={'website': domain})
        if req.status_code != 200:
            logging.warn("Response %s for domain %s", req.status_code, domain)
            return True


        category = req.json()
        logging.debug("%s: %s", req.status_code, str(category))
        logging.info("URL: %s, categories: %s", url, str(category['category']))
        try:
            self.store_category(url, category['category'] + [category['rating']['value']])
        except KeyError:
            self.store_category(url, category['category'])

    def store_category(self, url, categories):
        c = self.conn.cursor()

        for cat in categories:
            c.execute("SAVEPOINT save1")
            try:
                c.execute("insert into categories(name, display_name, namespace) values (%s, %s, %s) returning id as id",
                          [ cat, cat, 'categorify' ])
                row = c.fetchone()
                cat_id = row[0]
            except psycopg2.DatabaseError:
                logging.warn("Duplicated category: %s", cat)
                c.execute("ROLLBACK TO save1")
                c.execute("select id from categories where name = %s and namespace = 'categorify'", [cat])
                row = c.fetchone()
                cat_id = row[0]

            try:
                c.execute("insert into url_categories(urlid, category_id, created) select urlid, %s, now() from urls where url = %s", 
                          [  cat_id, url, ])
                logging.info("Added assignment: %s -> %s", url, cat)
            except psycopg2.DatabaseError as exc:
                logging.warn("Exception: %s", repr(exc))
                logging.warn("Duplicate assignment: %s -> %s", url, cat)
                c.execute("ROLLBACK TO save1")

        c.close()
        self.conn.commit()


def main():
    queuelib.setup_logging()
    gather = CategoryImporter()
    gather.run()

if __name__ == '__main__':
    main()



