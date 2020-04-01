
import time
import logging
import hashlib

import queuelib
from queuelib import QueueService

import urlparse
import requests

class RequestSigner(object):
    def __init__(self, secret):
        self.secret = secret.encode('utf8')

    def sign(self, *args):
        msg = ':'.join(
            [str(x) if not isinstance(x, str_types) else x for x in args])
        logging.debug("Using signature string: %s", msg)
        hm = hmac.new(self.secret, msg.encode('utf8'), hashlib.sha512)
        return hm.hexdigest()

    def get_signature(self, args, keys):
        return self.sign(*[args[x] for x in keys])

    def timestamp(self):
        return datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')

class CloudflareProbe(QueueService):
    CLOUDFLARE_API = 'https://family.cloudflare-dns.com/dns-query'
    QUEUE_NAME = 'url.cloudflare.org'
    HEADERS = {
            'Accept': 'application/dns-json',
            'User-Agent': 'CloudflareProbe/1.0 (+https://www.blocked.org.uk)'
            }

    def __init__(self):
        super(self, CloudflareProbe).__init__()
        self.signer = RequestSigner(self.cfg.get('cf-probe','probe_secret'))
        self.network = self.cfg.get('cf-probe','network_name')

    def setup_bindings(self):
        self.session = requests.session()

    def connect_db(self):
        # no DB connection needed
        pass

    def process_message(self, data):
        url = data['url']
        parsed_url = urlparse.urlparse(url)
        domain = parsed_url.netloc.lower()
        if domain.startswith('www.'):
            domain = domain.split('.', 1)[-1]

        req = self.session.get(self.CLOUDFLARE_API, , headers=self.HEADERS, params={'name': domain, 'type':'A'})
        if req.status_code != 200:
            logging.warn("Response %s for domain %s", req.status_code, domain)
            return True


        cf = req.json()
        logging.info("%s: %s", req.status_code, str(cf))
        logging.info("URL: %s, status: %s", url, str(cf['Status']))

        if cf['Status'] == 5:
            status = 'blocked'
        else:
            status = 'ok'

    
        rsp = {
            'url': url,
            'status': status,
            'network_name': self.network,
            'probe_uuid': self.cfg.get('cf-probe', 'probe_uuid'),
            'date': self.signer.timestamp(),
            'config': -1,
            'ip_network': None,
            'http_status': None,
        }
        rsp['signature'] = self.signer.get_signature(
            args=report,
            keys=["probe_uuid", "url", "status", "date", "config"])

        urlhash = data.get('hash')
        msg = amqp.Message(json.dumps(rsp))
        routing_key = 'results.' + self.network + '.' + \
            urlhash if urlhash is not None else ''
        self.ch.basic_publish(msg, self.cfg.get('cf-probe','exchange'), routing_key)

        
        time.sleep(0.1)
        return True



def main():
    queuelib.setup_logging()
    cfprobe = CloudflareProbe()
    cfprobe.run()

if __name__ == '__main__':
    main()



