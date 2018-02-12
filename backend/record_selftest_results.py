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
    
def main():

    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['config.ini'])) == 1)

    pgopts = dict(cfg.items('db'))
    with psycopg2.connect(**pgopts) as conn:
        amqpopts = dict(cfg.items('amqp'))
        amqpconn = amqp.Connection( **amqpopts)
        ch = amqpconn.channel()
        ch.basic_qos(0,10,False)

        def process_result(msg):
            logging.info(msg.body)
            ch.basic_ack(msg.delivery_tag)
            try:
                data = json.loads(msg.body)
                uuid = data["probe_uuid"]
                print(uuid)
                with conn.cursor() as cursor:
                    cursor.execute("select filter_enabled from probes where uuid = %s", 
                                   [uuid])
                    (filter_enabled_in_db,) = cursor.fetchone()
                    filter_enabled_on_probe = data["result"] == "filter_enabled"
                    if (filter_enabled_on_probe != filter_enabled_in_db):
                        logging.info("Updating filter status to: %s", filter_enabled_on_probe)
                        cursor.execute("update probes set filter_enabled = %s where uuid = %s", 
                                       [filter_enabled_on_probe, uuid])
                    conn.commit()
            except Exception as e:
                logging.exception(e)

        ch.basic_consume("selftest", consumer_tag='selftest', callback=process_result)
        while True:
            ch.wait()

if __name__ == '__main__':
    main()
