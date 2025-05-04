
import os
import sys
import json
import logging
import psycopg2

try:
    import ConfigParser
except ImportError:
    import configparser as ConfigParser

import amqplib.client_0_8 as amqp

class QueueAckPosition(object): pass
class QueueAckBefore(QueueAckPosition): pass
class QueueAckAfter(QueueAckPosition): pass

def setup_logging():
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s\t%(levelname)s\t%(message)s",
        datefmt="[%Y-%m-%d %H:%M:%S]",
        )

class QueueService(object):
    ACK = QueueAckBefore
    QUEUE_NAME = None
    QUEUE_QOS = 10

    def __init__(self):
        self.conn = None
        self.configure()
        pass

    def setup_bindings(self):
        pass

    def process_message(self, message):
        pass

    def configure(self):
        self.cfg = ConfigParser.ConfigParser()
        assert(len(self.cfg.read(['config.ini'])) == 1)

        try:
            self.config = dict(self.cfg.items(self.__class__.__name__.lower()))
        except ConfigParser.NoSectionError:
            self.config = {}

    def connect_db(self):
        # create db connection
        pgopts = dict(self.cfg.items('db'))
        self.conn = psycopg2.connect(**pgopts)

    def connect(self):
        self.connect_db()

        # Create AMQP connection
        amqpopts = dict(self.cfg.items('amqp'))
        self.amqpconn = amqp.Connection( **amqpopts)

        ch = self.amqpconn.channel()
        ch.basic_qos(0,self.QUEUE_QOS,False)
        self.ch = ch

    def recv(self, msg):
        data = json.loads(msg.body)

        if self.ACK == QueueAckBefore:
            self.ch.basic_ack(msg.delivery_tag)

        ret = self.process_message(data)

        if self.ACK == QueueAckAfter:
            self.ch.basic_ack(msg.delivery_tag)

        return ret

    def run(self):
        self.connect()

        self.setup_bindings()

        self.ch.basic_consume(self.QUEUE_NAME, 
                              consumer_tag=self.QUEUE_NAME+'1', 
                              callback=self.recv)
        while True:
            self.ch.wait()
