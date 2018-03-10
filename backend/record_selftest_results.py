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
        conn.autocommit = True
        amqpopts = dict(cfg.items('amqp'))
        amqpconn = amqp.Connection(**amqpopts)

        ch = amqpconn.channel()
        ch.basic_qos(0,10,False)
        ch.basic_consume(
            queue="selftest",
            consumer_tag='selftest',
            callback=build_result_processor(ch, conn)
        )

        while True:
            ch.wait()


def build_result_processor(ch, conn):
    def process_result(msg):
        logging.debug(msg.body)
        ch.basic_ack(msg.delivery_tag)
        try:
            msg_data = json.loads(msg.body)
            probe_uuid = msg_data["probe_uuid"]
            logging.info("probe: %s Handling self-test", probe_uuid)
            with conn.cursor() as cursor:
                update_db(
                    cursor=cursor,
                    msg_data=msg_data,
                    probe_uuid=probe_uuid
                )
        except Exception as e:
            logging.exception(e)

    return process_result


def update_db(cursor, msg_data, probe_uuid):
    isp_name = msg_data['network_name']
    filter_enabled = msg_data["result"] == "filter_enabled"

    logging.info("probe: %s Filter status: %s isp_name: %s", 
                 probe_uuid, 
                 filter_enabled,
                 isp_name)
    cursor.execute(
        """
            update probes 
            set filter_enabled = %s, isp_id = isps.id 
            from isps
            where isps.name = %s
            and probes.uuid = %s
        """,
        [filter_enabled, isp_name, probe_uuid]
    )
    if cursor.rowcount != 1:
        raise Exception("Failed to update probe: {}  ".format(probe_uuid) + 
                        "Unknown probe UUID or ISP name?")

if __name__ == '__main__':
    main()
