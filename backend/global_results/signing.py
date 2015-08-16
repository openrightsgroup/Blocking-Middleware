import hmac
import hashlib
import logging
import datetime

class RequestSigner(object):
    def __init__(self, secret):
        self.secret = secret

    @staticmethod
    def encode(s):
        if isinstance(s, unicode):
            return s.encode('utf-8')
        elif not isinstance(s,str):
            return str(s)
        return s

    def sign(self, *args):
        msg = ':'.join([self.encode(x) for x in args])
        logging.debug("Using signature string: %s", msg)
        hm = hmac.new(self.secret, msg, hashlib.sha512)
        return hm.hexdigest()

    def get_signature(self, args, keys):
        return self.sign(*[args[x] for x in keys])

    def timestamp(self):
        return datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
