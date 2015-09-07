import os
import sys
import json
import logging
import MySQLdb
import MySQLdb.cursors
import urlparse
import ConfigParser


import amqplib.client_0_8 as amqp

class SocksSupportUnavailable(Exception): pass

logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s\t%(levelname)s\t%(message)s",
    datefmt="[%Y-%m-%d %H:%M:%S]",
    )

class ReportsSender(object):
    def __init__(self, config, conn, ch):
        self.config = config
        self.conn = conn
        self.ch = ch

    def get_session(self):
        try:
            proxy = self.config.get('socks','proxy')
        except ConfigParser.Error:
            proxy = None
        if proxy:
            try:
                import requesocks as requests
                session = requests.session()
                session.proxies = {
                    'http': proxy,
                    'https': proxy,
                    'httpo': proxy
                    }
                return session
            except ImportError:
                raise SocksSupportUnavailable
        else:
            import requests
            return requests.session()

            

    def send_report(self, msg):
        logging.debug("Got msg: %s", msg)
        self.ch.basic_ack(msg.delivery_tag)

        c = self.conn.cursor(cursorclass=MySQLdb.cursors.DictCursor)
        c.execute("select * from reports where id = %s",
            [msg.body])
        report = c.fetchone()
        
        if not report['complete']:
            logging.error("Report not complete: %s", msg)
            return True
    
        session = self.get_session()

        # send report
        req = session.post(self.config.get('ooni','base_url'),
            data=report['data'])
        upstream_id = req.json()['report_id']
        logging.info("Got upstream: %s", upstream_id)

        c.execute("select report_entries.* from report_entries where report_id=%s",
            [report['id']])
        for entry in c:
            req2 = session.put(self.config.get('ooni','base_url'),
                data={'report_id': upstream_id, 'content': entry['data']})

            logging.info("Sent entry: %s, report: %s, upstream: %s",
                entry['id'], report['id'], upstream_id)


        req3 = session.post(
            self.config.get('ooni','base_url') + '/{0}/close'.format(upstream_id))
        logging.info("Close: %s", req3.status_code)

        return True

        

def main():

    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['report_sender.ini'])) == 1)

    try:
        if cfg.get('socks','proxy'):
                import requesocks as requests
        else:
            import requests
    except ImportError:
            import requests

    # create MySQL connection
    mysqlopts = dict(cfg.items('mysql'))
    conn = MySQLdb.connect(cursorclass=MySQLdb.cursors.DictCursor, **mysqlopts)

    # Create AMQP connection
    amqpopts = dict(cfg.items('amqp'))
    amqpconn = amqp.Connection( **amqpopts)
    ch = amqpconn.channel()

    sender = ReportsSender(cfg, conn, ch)

    # create consumer, enter mainloop
    logging.info("Waiting on queue %s", cfg.get('daemon','queue'))
    ch.basic_consume(cfg.get('daemon','queue'), consumer_tag='sender1', 
        callback=sender.send_report)

    while True:
        ch.wait()

if __name__ == '__main__':
    main()
