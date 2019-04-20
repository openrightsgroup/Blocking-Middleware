
import os
import re
import sys
import json
import time
import logging
import argparse
import datetime
import operator
import subprocess
import collections
import configparser

import psycopg2
import amqplib.client_0_8 as amqp

from NORM import Query, DBObject

logging.basicConfig(
    level=logging.INFO,
    datefmt='[%Y-%m-%d:%H:%M:%S]',
    format='%(asctime)s\t%(levelname)s\t%(message)s'
    )

CONFIG = configparser.ConfigParser()
EXCHANGE = 'org.blocked'
MAX_RESULTS = 200
MAX_AVG_QUEUE_LENGTH = 800
MAX_QUEUE_LENGTH = 600
DELAY = 30

class Test(DBObject):
    TABLE = 'tests.test_cases'
    FIELDS = [
        'name', 'status', 'tags', 'filter', 'sent',
        'total', 'received', 'isps', 'check_interval',
        'last_check', 'repeat_interval', 'last_run', 'batch_size', 'last_id',
        'message'
    ]

    @classmethod
    def get_runnable(klass, conn):
        q = Query(conn, """select test_cases.* from tests.test_cases
            where status >= 'RUNNING' and status <= 'WAITING'
            and (last_check is null or last_check + check_interval <= now())
            order by id""", [])
        for row in q:
            yield klass(conn, data=row)
        q.close()

    def get_urls(self):
        q = Query(self.conn, """select urls.* from urls
            where tags && %s::varchar[] and urlid > %s and urls.status = 'ok'
            order by urlid limit {0}""".format(self['batch_size']),
            [self['tags'], self['last_id']])
        for row in q:
            yield (row['urlid'], row['url'], row['hash'])
        q.close()

    def get_total_urls(self):
        q = Query(self.conn, """select count(urls.*) as ct from urls
            where tags && %s::varchar[] and urls.status = 'ok'""", 
            [self['tags']])
        row = q.fetchone()
        q.close()
        return row['ct']

    def update_sent(self, urlid):
        self['sent'] += 1
        self['last_id'] = urlid
        self.store()
        
    def update_last_check(self):
        self['last_check'] = datetime.datetime.now()
        self.store()

    def update_total(self):
        self['total'] = self.get_total_urls()
        self.store()

    def get_routing_key(self):
        return "test." + re.sub('[^\w]','', self['name'].lower())
        
    def set_status(self, newstatus, message=None):
        if newstatus == 'RUNNING' and self['status'] == 'NEW':
            self['last_run'] = datetime.datetime.now()
        self['status'] = newstatus
        if message is not None:
            self['status_message'] = message
        if message:
            logging.warn("Status: %s, %s", newstatus, message)
        self.store()

def update_lastpolled(conn, urlid):
    c = conn.cursor()
    c.execute("update urls set lastpolled=now() where urlid = %s", [ urlid ])
    c.close()


def get_changes(orig, new):
    orig_set, new_set = set(orig), set(new)

    return (orig_set.difference(new_set), new_set.difference(orig_set))

def read_queues():
    proc = subprocess.Popen(CONFIG.get('rabbit','ctl').split() + ['-q','list_bindings'], stdout=subprocess.PIPE)
    queues = collections.defaultdict(list)
    for row in proc.stdout:
        parts = row.split('\t')
        if parts[0] == '':
            continue
        queues[(parts[0],parts[4])].append(parts[2])
    proc.wait()
    logging.debug("Got list: %s", queues)
    return queues

def check_queues(conn):
    c = conn.cursor()
    proc = subprocess.Popen(CONFIG.get('rabbit','ctl').split() + ['-q','list_queues','name','messages'], stdout=subprocess.PIPE)
    queuelength = {}
    for row in proc.stdout:
        parts = row.strip().split('\t')
        if parts[0].startswith('url.') or parts[0] == 'results':
            logging.debug("Queue: %s; length: %s", parts[0], parts[1])
            c.execute("update tests.queue_status set message_count = %s, last_updated = now() where queue_name = %s",
                      [parts[1], parts[0]])
            if c.rowcount == 0:
                c.execute("insert into tests.queue_status (message_count, last_updated, queue_name) values(%s, now(), %s)",
                          [parts[1], parts[0]])

            queuelength[parts[0]] = int(parts[1])
    conn.commit()
    proc.wait()
    return queuelength

def qname(s):
    try:
        fmt = CONFIG.get('queues','queue_format')
    except:
        fmt = "url.{0}.public"

    return fmt.format(s)

def main():
    CONFIG.read(['test_runner.cfg'])

    dbopts = dict(CONFIG.items('db'))
    conn = psycopg2.connect(**dbopts)
    amqpopts = dict(CONFIG.items('amqp'))
    amqpconn = amqp.Connection(**amqpopts)
    ch = amqpconn.channel()

    while True:
        bindings = read_queues()
        queues = set(reduce(operator.add, bindings.values()))
    
        q = Query(conn, "select now() as now", [])
        row = q.fetchone()
        logging.debug("DB timestamp: %s", row['now'])
        q.close()

    
        for testcase in Test.get_runnable(conn):
            logging.info("Test case %s(%s)", testcase['name'], testcase['id'])
            logging.debug("Routing: %s / %s", EXCHANGE,testcase.get_routing_key())
            add_isps, remove_isps = get_changes(map(qname, testcase['isps']), bindings[(EXCHANGE,testcase.get_routing_key())])
            if add_isps:
                logging.info("Add: %s", add_isps)
            if remove_isps:
                logging.info("Remove: %s", remove_isps)
    
            testcase.update_total()
    
            for queue in add_isps:
                if queue in queues:
                    ch.queue_bind( queue, EXCHANGE, testcase.get_routing_key())
    
            for queue in remove_isps:
                ch.queue_unbind( queue, EXCHANGE, testcase.get_routing_key())

            queue_lengths = check_queues(conn)
            if queue_lengths['results'] > MAX_RESULTS:
                testcase.set_status('WAITING', "Results queue too long")
                conn.commit()
                continue

            if len(testcase['isps']) == 0:
                testcase.set_status('ERROR', 'No ISPs selected')
                conn.commit()
                continue
    
            avg_queue_length = sum([queue_lengths[qname(isp)] for isp in testcase['isps'] if isp in queue_lengths]) / float(len(testcase['isps']))
            if avg_queue_length > MAX_AVG_QUEUE_LENGTH:
                logging.warn()
                testcase.set_status('WAITING', "Avg queue too long: {0}".format(avg_queue_length))
                conn.commit()
                continue
                
            if any([queue_lengths[qname(isp)] > MAX_QUEUE_LENGTH for isp in testcase['isps'] if isp in queue_lengths]):
                logging.warn()
                testcase.set_status('WAITING', "Queue too long")
                conn.commit()
                continue
    
            testcase.set_status('RUNNING','')
            testcase.update_last_check()
            conn.commit()
    
            logging.info("Starting url send at id %s", testcase['last_id'])
    
            sendcount = 0
            for (urlid, url, urlhash) in testcase.get_urls():
                update_lastpolled(conn, urlid)
    
                body = json.dumps({'url':url, 'hash': urlhash})
                logging.debug("Sent %s (%d)", url, urlid)
                msg = amqp.Message(body)
                ch.basic_publish(msg, EXCHANGE, testcase.get_routing_key())
    
                testcase.update_sent(urlid)
                conn.commit()
                sendcount += 1
                
            logging.info("Sent %d urls", sendcount)
            if sendcount == 0:
                testcase.set_status('COMPLETE')
                conn.commit()
        else:
            queue_lengths = check_queues(conn)
               
        conn.commit()
        time.sleep(DELAY)

if __name__ == '__main__':
    main()
