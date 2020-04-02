
import time
import hmac
import json
import logging
import hashlib
import datetime

import queuelib
from queuelib import QueueService, amqp

import urlparse
import requests

import argparse

class RequestSigner(object):
    def __init__(self, secret):
        self.secret = secret.encode('utf8')

    def sign(self, *args):
        msg = ':'.join(
            [str(x) if not isinstance(x, (unicode,str)) else x for x in args])
        logging.debug("Using signature string: %s", msg)
        hm = hmac.new(self.secret, msg.encode('utf8'), hashlib.sha512)
        return hm.hexdigest()

    def get_signature(self, args, keys):
        return self.sign(*[args[x] for x in keys])

    def timestamp(self):
        return datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')

class CloudflareProbe(QueueService):
    CLOUDFLARE_API = 'https://family.cloudflare-dns.com/dns-query'
    QUEUE_NAME = 'url.cloudflare_family.org'
    HEADERS = {
            'Accept': 'application/dns-json',
            'User-Agent': 'CloudflareProbe/1.0 (+https://www.blocked.org.uk)'
            }

    def __init__(self):
        super(CloudflareProbe, self).__init__()
        self.signer = RequestSigner(self.cfg.get('cf-probe','probe_secret'))
        self.network = self.cfg.get('cf-probe','network_name')

    def setup_bindings(self):
        self.session = requests.session()

    def connect_db(self):
        # no DB connection needed
        pass

    def process_message(self, data):
        url = data['url']
        logging.info("Url: %s", url)
        parsed_url = urlparse.urlparse(url)
        domain = parsed_url.netloc.lower()
        if domain.startswith('www.'):
            domain = domain.split('.', 1)[-1]

        try:
            req = self.session.get(self.CLOUDFLARE_API, headers=self.HEADERS, params={'name': domain, 'type':'A'})
            if req.status_code != 200:
                logging.warn("Response %s for domain %s", req.status_code, domain)
                return True


            cf = req.json()
            logging.info("%s: %s", req.status_code, str(cf))
            logging.info("URL: %s, status: %s", url, str(cf['Status']))
            logging.debug("Data: %s", str(cf))

            if cf['Status'] == 5:
                status = 'blocked'
            elif cf['Status'] == 0:
                status = 'ok'
            else:
                status = 'dnserror'
        except Exception as exc:
            logging.error("Exception: %s", str(exc))
            status = 'error'

    
        rsp = {
            'url': url,
            'status': status,
            'network_name': self.network,
            'probe_uuid': self.cfg.get('cf-probe', 'probe_uuid'),
            'date': self.signer.timestamp(),
            'blocktype': 'PARENTAL',
            'config': -1,
            'ip_network': None,
            'http_status': None,
        }
        rsp['signature'] = self.signer.get_signature(
            args=rsp,
            keys=["probe_uuid", "url", "status", "date", "config"])

        urlhash = data.get('hash')
        msg = amqp.Message(json.dumps(rsp))
        routing_key = 'results.' + self.network + '.' + \
            (urlhash if urlhash is not None else '')
        self.ch.basic_publish(msg, self.cfg.get('cf-probe','exchange'), routing_key)

        
        time.sleep(0.1)
        return True



def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--verbose','-v', action='store_true')
    parser.add_argument('queue', nargs='?')
    args = parser.parse_args()
    queuelib.setup_logging()
    if args.queue:
        CloudflareProbe.QUEUE_NAME = CloudflareProbe.QUEUE_NAME.replace('.org', '.'+args.queue)
    cfprobe = CloudflareProbe()
    logging.info("Listening on: %s", cfprobe.QUEUE_NAME)
    cfprobe.run()

if __name__ == '__main__':
    main()



