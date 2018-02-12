#!/usr/bin/env python2
"""Request self-test from probes.  Should be run regularly via cron"""

import json
import logging
import ConfigParser
import uuid
import amqplib.client_0_8 as amqp

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s\t%(levelname)s\t%(message)s",
    datefmt="[%Y-%m-%d %H:%M:%S]",
    )

if __name__ == '__main__':
    cfg = ConfigParser.ConfigParser()
    assert(len(cfg.read(['config.ini'])) == 1)

    amqpopts = dict(cfg.items('amqp'))
    amqpconn = amqp.Connection(**amqpopts)
    ch = amqpconn.channel()
    ch.basic_qos(0,10,False)

    request_id = uuid.uuid4().hex
    logging.info("Requesting self-test {}".format(request_id))
    ch.basic_publish(
        msg=amqp.Message(json.dumps({
            "action": "run_selftest",
            "request_id": request_id
        })), 
        exchange="org.blocked", 
        routing_key="url.org"
    )
