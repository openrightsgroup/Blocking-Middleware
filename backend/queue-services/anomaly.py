#!/usr/bin/env python2

import sys
import json
import logging
import hashlib
import argparse
import resource
import collections

import requests
import amqplib.client_0_8 as amqp

import queuelib
from queuelib import QueueService



"""
This daemon listens on a dedicated queue for URL results, and compares against
other results to detect anomalies.


"""

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

        h1 = dict(sample1['rsp']['headers'])  # not strictly corrent, headers can repeat
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
        super(AnomalyDetector, self).__init__()
        self.count = 0
        self.proxies = dict(self.cfg.items('anomaly_proxies'))

    def setup_bindings(self):
        self.ch.queue_declare(self.QUEUE_NAME, durable=True, auto_delete=False)
        self.ch.queue_bind(self.QUEUE_NAME, "org.blocked", "anomalydetector")

    def process_message(self,data):
        try:
            result = test_url(data['url'], self.config['proxy'])
            ret = {
                'url': data['url'],
                'anomaly-report': result
                }
            retbody = json.encode(ret)
            msg = amqp.Message(retbody)
            self.ch.basic_publish(msg, self.cfg.get('daemon', 'exchange'), 'anomaly.response.' + hash(data['url']))

        except Exception as v:
            logging.warn("Error in anomaly detection: %s", repr(v))

        self.count += 1
        logging.info("URL: %s; rss: %s; count: %s", data['url'], resource.getrusage(0).ru_maxrss, self.count)

        if self.count > 500:
            logging.warn("Exiting after 500 requests")
            sys.exit(0)

        return True

def get_parser():
    parser = argparse.ArgumentParser(
        description="block analysis",
        )
    parser.add_argument('--test', action='store_true', help="test URL")
    parser.add_argument('--debug', action='store_true', help="prints request record data for each request")
    parser.add_argument('--proxy', help="proxy address")
    parser.add_argument('url', nargs='?')
    return parser

def hash(s):
    return hashlib.sha256(s).hexdigest()

def create_request_record(r):
    """Copied from orgprobe: creates compatible request record from requests.get output"""
    rq = r.request
    return {
        'req': {
            'url': rq.url,
            'headers': [(k,v) for k,v in rq.headers.items()],
            'body': rq.body or None,
            'hash': hash(rq.body) if rq.body else None,
            'method': rq.method
        },
        'rsp': {
            'headers': [(k,v) for k,v in r.headers.items()],
            'status': r.status_code,
            # 'ssl_fingerprint': r.ssl_fingerprint,
            # 'ssl_verified': r.ssl_verified,
            # 'ip': r.peername,
            'content': r.text,
            'hash': hash(r.content)
        }
    }

def get_url(url, proxy, debug=False):
    proxies = None
    if proxy:
        proxies = {'http': proxy, 'https': proxy}

    logging.info("Using proxy: %s", proxies)
    req = requests.get(url, proxies=proxies,
                       headers={'User-agent': "BlockedAnomaly/0.1.0(+www.blocked.org.uk)"})
    r = create_request_record(req)
    if debug:
        print("{} req output ===>".format("Proxied" if proxy else "Non-proxied"))
        print(r)
        print("---")
    return r

def test_url(url, proxy, debug=False):
    """ For ad-hoc testing through proxy"""

    r1 = get_url(url, None, debug)
    r2 = get_url(url, proxy, debug)

    result = AnomalyDetector().collate_analysis(r1, r2)
    return result


def main():
    parser = get_parser()
    args = parser.parse_args()
    if args.test:
        logging.basicConfig(level=logging.INFO)
        if not args.proxy:
            print("Proxy argument required")
            sys.exit(1)
        print(test_url(args.url, args.proxy, args.debug))
        return

    # otherwise run queue server
    queuelib.setup_logging()
    gather = AnomalyDetectorService()
    gather.run()

if __name__ == '__main__':
    main()
