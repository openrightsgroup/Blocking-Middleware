
import os,sys
import getopt
import requests
import datetime
from pprint import pprint

import hmac, hashlib
import logging



optlist, optargs = getopt.getopt(sys.argv[1:],'v', [
	'email=',
	'host=',
	'port=',
	'password=',
	'secret=',
	'url=',
	'ip=',
	'status=',
        'fuzzdate',
	'network=',
	'batchsize=',
	'new',

        # probe registration
        'probeseed=',
        'probehmac=',
        'probeuuid=',
		'https',
        'info=',
	])
opts = dict(optlist)

logging.basicConfig(
	level = logging.DEBUG if '-v' in opts else logging.INFO,
	)

class TestClient:
	MODES = ['user','user_status','submit','prepare_probe','register_probe','ip','list_users','stats','get']
	PREFIX='/1.2/'

	def __init__(self, options):
		self.opts = options
		self.proto = 'https' if '--https' in options else 'http'
		self.host = options.get('--host','localhost')
		self.port = options.get('--port','80')
		self.secret = options.get('--secret','')
		if '--new' in options:
			self.prefix = self.PREFIX+'api.php/'
		else:
			self.prefix = self.PREFIX

        def timestamp(self):
                if '--fuzzdate' in opts:
                        return datetime.datetime.now().replace(hour=1).strftime('%Y-%m-%d %H:%M:%S')
                return datetime.datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')

	def run(self, mode):
		assert mode in self.MODES
		return getattr(self, mode)()




	def user(self):
		rq = requests.post('https://' + self.host +":"+self.port+ self.prefix+'register/user',
			data={'email': self.opts['--email'],'password': self.opts['--password']}
			)
		return rq.status_code, rq.content

	def user_status(self):
                ts = self.timestamp()
		rq = requests.get('https://' + self.host+":"+self.port + self.prefix+'status/user',
			params={
				'email': self.opts['--email'],
                                'date': ts,
				'signature': self.sign(self.opts['--email'], ts),
				}
			)
		return rq.status_code, rq.content

        def prepare_probe(self):
                ts = self.timestamp()
		rq = requests.post('https://' + self.host+":"+self.port + self.prefix+'prepare/probe',
			data={
				'email': self.opts['--email'],
                                'date': ts,
				'signature': self.sign(self.opts['--email'], ts),
				}
			)
		return rq.status_code, rq.content

        def register_probe(self):
                uuid = hashlib.md5(self.opts['--probeseed'] + '-' + self.opts['--probehmac']).hexdigest()
		logging.info("Sending UUID: %s", uuid)
		rq = requests.post('https://' + self.host+":"+self.port + self.prefix+'register/probe',
			data={
				'email': self.opts['--email'],
                                'probe_seed': self.opts['--probeseed'],
                                'probe_uuid': uuid,
				'signature': self.sign(uuid),
				}
			)
		return rq.status_code, rq.content

	def get(self):
		rq = requests.get(self.get_url('request/httpt'),
			params = {
				'probe_uuid': self.opts['--probeuuid'],
				'batchsize': self.opts.get('--batchsize',1),
				'network_name': self.opts['--network'],
				'signature': self.sign(self.opts['--probeuuid']),
			})
		return rq.status_code, rq.content


	def submit(self):
		rq = requests.post('https://' + self.host+":"+self.port + self.prefix + 'submit/url',
		data = {
			'email': opts['--email'],
			'url': opts['--url'],
			'additional_data': opts.get('--info',''),
			'signature': self.sign(opts['--url']),
			})
		return rq.status_code, rq.content

	def ip(self):
		ts = self.timestamp()
		rq = requests.get(self.proto + '://' + self.host+":"+self.port + self.prefix + 'status/ip' + \
			('/'+opts['--ip'] if '--ip' in opts else ''),
			params = {
				'date': ts,
				'signature': self.sign(ts),
				'probe_uuid': opts['--probeuuid'],
			}
		)
		return rq.status_code, rq.content

	def list_users(self):
		ts = self.timestamp()
		rq = requests.get('https://' + self.host+":"+self.port + self.prefix + 'list/users' + \
		    ('/'+opts['--status'] if '--status' in opts else ''),
		    params = {
			    'date': ts,
			    'signature': self.sign(ts),
			    'email': opts.get('--email'),
		    }
		)
		return rq.status_code, rq.content

	def get_url(self, endpoint):
		return self.proto + '://' + self.host+":"+self.port + self.prefix  + endpoint

	def stats(self):
		ts = self.timestamp()
		rq = requests.get(self.get_url('status/stats'),
			params = {
				'date': ts,
				'signature': self.sign(ts),
				'email': opts.get('--email')
			}
		)
		return rq.status_code, rq.content


	def sign(self, *args):
                msg = ':'.join([str(x) for x in args])
		hm = hmac.new(self.secret, msg, hashlib.sha512)
		return hm.hexdigest()



client = TestClient(opts)
print client.run(optargs[0])
