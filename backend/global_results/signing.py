import hmac
import hashlib
import logging
import datetime

class RequestSigner(object):
	def __init__(self, secret):
		self.secret = secret

	def sign(self, *args):
		msg = ':'.join([str(x) if not isinstance(x, (str,unicode)) else x for x in args])
		logging.debug("Using signature string: %s", msg)
		hm = hmac.new(self.secret, msg, hashlib.sha512)
		return hm.hexdigest()

	def get_signature(self, args, keys):
		return self.sign(*[args[x] for x in keys])

	def timestamp(self):
		return datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')
