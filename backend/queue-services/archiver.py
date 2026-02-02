#!/usr/bin/env python

import json
import time
import logging
import argparse

import queuelib
from queuelib import QueueService

import waybackpy

"""
This daemon listens on a dedicated queue for Archival requests to be submitted to Wayback machine.

Should include some sort of retry logic
"""


class ArchiveService(QueueService):
    QUEUE_NAME = 'archive'

    def __init__(self):
        super(ArchiveService, self).__init__()

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        exch = self.cfg.get('daemon', 'exchange')
        for key in self.config['routing_keys'].split(','):
            self.ch.queue_bind(self.QUEUE_NAME, exch, key.strip())

    @classmethod
    def get_delay(cls, current):
        if current == 0:
            return 30
        if current == 30:
            return 60
        if current < 600:
            return current * 2
        return 600

    @classmethod
    def snapshot_url(cls, url):
        delay = 5

        for attempt in range(10):
            call = waybackpy.WaybackMachineSaveAPI(url)
            try:
                archive_url = call.save()
                return {'archive_url': archive_url, 'status': 'success', 'attempt': attempt}
            except waybackpy.exceptions.WaybackError as wbexc:
                logging.warning("wayback status: %s", repr(wbexc))
                logging.info("delaying for %d seconds", delay)
                time.sleep(delay)
                delay = cls.get_delay(delay)
        logging.error("Failed to ")
        return {'status': 'failed', 'delay': delay}

    def process_message(self, data):
        self.snapshot_url(data['url'])

        return True

def get_parser():
    parser = argparse.ArgumentParser(
        description="wayback snapshot service",
        )
    parser.add_argument('--test', action='store_true', help="test URL")
    parser.add_argument('--debug', action='store_true', help="prints request record data for each request")
    parser.add_argument('url', nargs='?')
    return parser


def main():
    parser = get_parser()
    args = parser.parse_args()

    queuelib.setup_logging()
    service = ArchiveService()
    if args.test:
        print(service.snapshot_url(args.url))
        return

    service.run()

if __name__ == '__main__':
    main()