
import os,sys
import getopt
import requests

import hmac, hashlib

optlist, optargs = getopt.getopt(sys.argv[1:],'', [
	'email=',
	'host=',
	'password=',
	'secret=',
	])
opts = dict(optlist)

class TestClient:
	MODES = ['user','user_status']
	PREFIX='/api/1.1/'

	def __init__(self, options):
		self.opts = options
		self.host = options.get('--host','localhost')
		self.secret = options.get('--secret','')

	def run(self, mode):
		assert mode in self.MODES
		return getattr(self, mode)()

	def user(self):
		rq = requests.post('http://' + self.host + self.PREFIX+'register/user',
			data={'email': self.opts['--email'],'password': self.opts['--password']}
			)
		return rq.content

	def user_status(self):
		rq = requests.post('http://' + self.host + self.PREFIX+'status/user',
			data={
				'email': self.opts['--email'],
				'signature': self.sign(self.opts['--email']),
				}
			)
		return rq.status_code, rq.content

	def sign(self, msg):
		hm = hmac.new(self.secret, msg, hashlib.sha512)
		return hm.hexdigest()
		

		
client = TestClient(opts)
print client.run(optargs[0])
