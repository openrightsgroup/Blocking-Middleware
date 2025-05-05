#!/usr/bin/env python2

import re
import sys
import json
import logging
import hashlib
import argparse
import resource
import collections

import requests

import queuelib
from queuelib import QueueService

from NORM import DBObject,Query

"""
This daemon listens on a dedicated queue for URL results, and compares against
other results to detect anomalies.

"""

class AnomalyCheckResult(DBObject):
    TABLE = 'anomaly_check_results'
    FIELDS = ['urlid', 'result_json', 'review', 'reviewed_by']

class AnomalyCheckResponse(DBObject):
    TABLE = 'anomaly_check_responses'
    FIELDS = ['result_id', 'region', 'response_json']

class AnomalyDetector(object):
    def __init__(self):
        self.logger = logging.getLogger(__class__.__name__)
        pass

    def detect(self, result):
        pass

    def get_results(self, url):
        pass

    def collate_analysis(self, sample1, sample2):
        counter = collections.Counter()
        for score in self.analyze(sample1, sample2):
            self.logger.info("Got: %s", score)
            counter.update(**score)
        self.logger.info("Return: %s", counter)
        return counter

    def analyze(self, sample1, sample2):
        if sample1['rsp']['status'] != sample2['rsp']['status']:
            yield {'status_mismatch': 1}

        if 403 in (sample1['rsp']['status'], sample2['rsp']['status']):
            yield {'status_forbidden': 1}

        if len(sample1['rsp']['headers']) != len(sample2['rsp']['headers']):
            yield {'header_count_mismatch': abs(len(sample1['rsp']['headers']) - len(sample2['rsp']['headers']))}

        h1 = dict(sample1['rsp']['headers'])  # not strictly correct, headers can repeat
        h2 = dict(sample2['rsp']['headers'])
        for k in h1:
            if k in h2:
                if h1[k] != h2[k]:
                    yield {'header_value_mismatch': 1}

        yield {'headers_removed': len(set([x[0] for x in sample1['rsp']['headers']]) - set([x[0] for x in sample2['rsp']['headers']]))}
        yield {'headers_added': len(set([x[0] for x in sample2['rsp']['headers']]) - set([x[0] for x in sample1['rsp']['headers']]))}

        if sample1['rsp']['hash'] and sample2['rsp']['hash']:
            if sample1['rsp']['hash'] != sample2['rsp']['hash']:
                yield {'content_hash_mismatch': 1}

        if sample1['rsp']['content'] and sample2['rsp']['content']:
            l1, l2 = len(sample1['rsp']['content']), len(sample2['rsp']['content'])
            yield {'content_body_length_ratio': float(l2) / l1}

        # TODO: body tests, regexp matches looking for geo-block related messages
        # i.e "This content is not available in your region" from nydailynews.com


class AnomalyDetectorService(QueueService):
    QUEUE_NAME = 'anomalycheck'
    def __init__(self):
        super(AnomalyDetectorService, self).__init__()
        self.count = 0
        # self.proxies = dict(self.cfg.items('anomaly_proxies'))

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        exch = self.cfg.get('daemon', 'exchange')
        for key in self.config['routing_keys'].split(','):
            self.ch.queue_bind(self.QUEUE_NAME, exch, key.strip())

    def process_message(self,data):
        try:
            result = self.test_url(data['url'], self.config['proxy'])
            logging.info("Stored result: %s", result)
        except Exception as v:
            logging.warning("Error in anomaly detection: %s", repr(v))

        self.count += 1
        logging.info("URL: %s; rss: %s; count: %s", data['url'], resource.getrusage(0).ru_maxrss, self.count)

        if self.count > 500:
            logging.warning("Exiting after 500 requests")
            sys.exit(0)

        return True

    def fetch_url(self, url, proxy):
        proxies = None
        if proxy:
            proxies = {'http': proxy, 'https': proxy}

        logging.info("Using proxy: %s", proxies)
        req = requests.get(url,
                           proxies=proxies,
                           headers={'User-agent': self.config['useragent']})
        r = self.create_request_record(req)

        return r

    @staticmethod
    def sha256(s):
        if isinstance(s, str):
            s = s.encode('utf-8')
        return hashlib.sha256(s).hexdigest()

    @classmethod
    def create_request_record(cls, r):
        """Copied from orgprobe: creates compatible request record from requests.get output"""
        rq = r.request
        return {
            'req': {
                'url': rq.url,
                'headers': [(k,v) for k,v in rq.headers.items()],
                'body': rq.body or None,
                'hash': cls.sha256(rq.body) if rq.body else None,
                'method': rq.method
            },
            'rsp': {
                'headers': [(k,v) for k,v in r.headers.items()],
                'status': r.status_code,
                # 'ssl_fingerprint': r.ssl_fingerprint,
                # 'ssl_verified': r.ssl_verified,
                # 'ip': r.peername,
                'content': r.text,
                'text_summary': cls.text_summary(r.text),
                'hash': cls.sha256(r.content)
            }
        }

    @staticmethod
    def text_summary(text):
        s = re.sub(r'<(script|style).*?</(script|style)>', '', text, flags=re.DOTALL)
        s = re.sub(r'<.*?>', '', s, flags=re.DOTALL)
        s = re.sub(r'\s\s+', '\n', s, flags=re.DOTALL)
        s = s.strip()
        logging.info("Summary: %s", s[:300])
        return s[:200]

    def test_url(self, url, proxy):
        """ For ad-hoc testing through proxy"""

        r1 = self.fetch_url(url, None)
        r2 = self.fetch_url(url, proxy)

        result = AnomalyDetector().collate_analysis(r1, r2)

        if self.conn:
            logging.info("Looking up urlid: %s", url)
            q = Query(self.conn, "select urlid from public.urls where url = %s", [url])
            row = q.fetchone()
            q.close()
            rec = AnomalyCheckResult(self.conn)
            rec.update({
                'urlid': row['urlid'],
                'result_json': json.dumps(result)
            })
            rec.store()
            rsp1 = AnomalyCheckResponse(self.conn)
            rsp1.update({
                'result_id': rec['id'],
                'region': self.config['local_region'],
                'response_json': json.dumps(r1)
            })
            rsp1.store()
            rsp2 = AnomalyCheckResponse(self.conn)
            rsp2.update({
                'result_id': rec['id'],
                'region': self.config['proxy_region'],
                'response_json': json.dumps(r2)
            })
            rsp2.store()
            self.conn.commit()

        return result


def get_parser():
    parser = argparse.ArgumentParser(
        description="block analysis",
        )
    parser.add_argument('--test', action='store_true', help="test URL")
    parser.add_argument('--debug', action='store_true', help="prints request record data for each request")
    parser.add_argument('--proxy', help="proxy address")
    parser.add_argument('url', nargs='?')
    return parser


def main():
    parser = get_parser()
    args = parser.parse_args()

    queuelib.setup_logging()
    service = AnomalyDetectorService()
    if args.test:
        if not args.proxy:
            print("Proxy argument required")
            sys.exit(1)
        print(service.test_url(args.url, args.proxy))
        return

    service.run()

if __name__ == '__main__':
    main()
