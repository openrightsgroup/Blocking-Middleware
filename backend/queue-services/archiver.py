#!/usr/bin/env python

import json
import time
import logging
import argparse

import queuelib
from queuelib import QueueService

import NORM

import waybackpy

"""
This daemon listens on a dedicated queue for Archival requests to be submitted to Wayback machine.

Should include some sort of retry logic
"""

class ArchivedUrl(NORM.DBObject):
    TABLE = 'archived_urls'
    FIELDS = [
        'urlid',
        'url',
        'snapshot_url',
        # 'status',  # PENDING, COMPLETE, ERROR ?
    ]
    
    def get_urlid(self, url):
        q = NORM.Query(self.conn,
                       "select urlid from public.urls where url = %s",
                       [url])
        row = q.fetchone()
        if row is None:
            return None
        return row[0]
    
    @classmethod
    def get_latest_snapshot(cls, conn, urlid):
        return cls.select_one(conn, urlid=urlid, _order='-id', _limit=1)


class ArchiveService(QueueService):
    QUEUE_NAME = 'archive'
    DEFAULT_ROUTING_KEYS = "archive"
    
    def __init__(self):
        super(ArchiveService, self).__init__()

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        exch = self.cfg.get('daemon', 'exchange')
        
        routing_keys = self.config['routing_keys'] or self.DEFAULT_ROUTING_KEYS
        for key in routing_keys.split(','):
            self.ch.queue_bind(self.QUEUE_NAME, exch, key.strip())

    @classmethod
    def get_delay(cls, current):
        if current == 0:
            return 30
        if current >= 600:
            return 600
        return current * 2

    def testing_snapshot(self, url):
        from urllib.parse import urlparse
        parts = urlparse(url)
        archive_url = f"http://localhost:8401/save/datecode/{parts.netloc}{parts.path}"
        logging.debug("TESTING: %s -> %s", url, archive_url)
        return archive_url


    def snapshot_url(self, url):
        delay = 0

        for attempt in range(10):
            call = waybackpy.WaybackMachineSaveAPI(url)
            try:
                if self.is_testing():
                    archive_url = self.testing_snapshot(url)
                else:
                    archive_url = call.save()
                archobj = ArchivedUrl(self.conn)
                archobj.update({
                    'snapshot_url': archive_url,
                    'urlid': archobj.get_urlid(url),
                    'url': url,
                })
                archobj.store()
                self.conn.commit()
                return {'archive_url': archive_url, 'status': 'success', 'attempt': attempt}
            except waybackpy.exceptions.WaybackError as wbexc:
                logging.warning("wayback status: %s", repr(wbexc))
                logging.info("delaying for %d seconds", delay)
                time.sleep(delay)
                delay = self.get_delay(delay)
        logging.error("Failed to snapshot")
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

    queuelib.setup_logging(loglevel=logging.DEBUG if args.debug else logging.INFO)
    service = ArchiveService()
    if args.test:
        print(service.snapshot_url(args.url))
        return

    service.run()

if __name__ == '__main__':
    main()
