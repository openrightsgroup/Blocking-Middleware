import os
import sys
import json
import logging
import tempfile
import subprocess
import MySQLdb
import MySQLdb.cursors
import ConfigParser


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
        logging.debug("Got msg: %s", msg)
        try:

            c = self.conn.cursor(cursorclass=MySQLdb.cursors.DictCursor)
            c.execute("select * from reports where id = %s",
                [msg.body])
            report = c.fetchone()
            
            if not report['complete']:
                logging.error("Report not complete: %s", msg)
                return True

            report_data = json.loads(report['data'])

            tmpfile = tempfile.NamedTemporaryFile(delete=False)
            tmpfile.write(report_data['content'])
            
        
            c.execute("select report_entries.* from report_entries where report_id=%s",
                [report['id']])
            for entry in c:
                tmpfile.write(entry['data'])
            tmpfile.close()

            logging.info("Data for report %s written to %s",
                report['id'], tmpfile.name)


            ret = subprocess.call([self.config.get('ooni','oonireport'), 
                "upload", tmpfile.name ])
            logging.info("oonireport returns: %s", ret)

            os.unlink(tmpfile.name)
            self.ch.basic_ack(msg.delivery_tag)
        except Exception,v:
            logging.error("Got error: %s", repr(v))
            self.ch.basic_reject(msg.delivery_tag)

        sys.exit(0)
        return True

        

def main():

    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['report_sender.ini'])) == 1)


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
