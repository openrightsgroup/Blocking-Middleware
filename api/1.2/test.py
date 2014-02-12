
import os,sys
import getopt
import requests

import hmac, hashlib
import logging



optlist, optargs = getopt.getopt(sys.argv[1:],'v', [
	'email=',
	'host=',
	'port=',
	'password=',
	'secret=',
	'url='
	])
opts = dict(optlist)

logging.basicConfig(
	level = logging.DEBUG if '-v' in opts else logging.INFO,
	)

class TestClient:
	MODES = ['user','user_status','submit']
	PREFIX='/api/1.2/'

	def __init__(self, options):
		self.opts = options
		self.host = options.get('--host','localhost')
		self.port = options.get('--port','80')
		self.secret = options.get('--secret','')

	def run(self, mode):
		assert mode in self.MODES
		return getattr(self, mode)()

	def user(self):
		rq = requests.post('http://' + self.host +":"+self.port+ self.PREFIX+'register/user',
			data={'email': self.opts['--email'],'password': self.opts['--password']}
			)
		return rq.status_code, rq.content

	def user_status(self):
		rq = requests.get('http://' + self.host+":"+self.port + self.PREFIX+'status/user',
			params={
				'email': self.opts['--email'],
				'signature': self.sign(self.opts['--email']),
				}
			)
		return rq.status_code, rq.content

	def submit(self):
		rq = requests.post('http://' + self.host+":"+self.port + self.PREFIX + 'submit/url',
		data = {
			'email': opts['--email'],
			'url': opts['--url'],
			'signature': self.sign(opts['--url']),
			})
		return rq.status_code, rq.content

	def sign(self, msg):
		hm = hmac.new(self.secret, msg, hashlib.sha512)
		return hm.hexdigest()
		

		
client = TestClient(opts)
print client.run(optargs[0])
