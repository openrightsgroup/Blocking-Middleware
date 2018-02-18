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
                update_db_if_necessary(
                    cursor=cursor,
                    msg_data=msg_data,
                    probe_uuid=probe_uuid
                )
        except Exception as e:
            logging.exception(e)

    return process_result


def update_db_if_necessary(cursor, msg_data, probe_uuid):
    cursor.execute("select filter_enabled, isp_id from probes where uuid = %s",
                   [probe_uuid])
    try:
        (filter_enabled_in_db, isp_id_in_db) = cursor.fetchone()
    except TypeError as e:
        raise Exception("Unregistered probe", e)
    logging.debug("probe: %s  filter: %s isp: %s",
                  probe_uuid, filter_enabled_in_db, isp_id_in_db)

    filter_enabled_on_probe = msg_data["result"] == "filter_enabled"
    if filter_enabled_on_probe != filter_enabled_in_db:
        logging.info("probe: %s Updating filter status to: %s",
                     probe_uuid, filter_enabled_on_probe)
        cursor.execute("update probes set filter_enabled = %s where uuid = %s",
                       [filter_enabled_on_probe, probe_uuid])
        if cursor.rowcount != 1:
            raise Exception("Failed to update probe filter status")

    isp_name_on_probe = msg_data['network_name']
    cursor.execute("select id from isps where name = %s",
                   [isp_name_on_probe])
    (isp_id_on_probe,) = cursor.fetchone()

    if isp_id_in_db != isp_id_on_probe:
        logging.info("probe: %s Updating isp_id from %s to %s",
                     probe_uuid, isp_id_in_db, isp_name_on_probe)
        cursor.execute("update probes set isp_id = %s where uuid = %s",
                       [isp_id_on_probe, probe_uuid])
        if cursor.rowcount != 1:
            raise Exception("Failed to update probe isp_id")


if __name__ == '__main__':
    main()
