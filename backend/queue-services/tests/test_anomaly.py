
import unittest
import logging

import anomaly

from .anomaly_fixtures import *


class AnomalyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        logging.basicConfig(level=logging.DEBUG)
        cls.sample1 = {
            'network_name': 'ISP1-unfiltered',
            'request_data': {
                'req': {
                    'url': "http://www.example.com",
                    'headers': [],
                    'body': None,
                    'hash': None,
                    'method': "GET"
                    },
                'rsp': {
                    'headers': [
                        ['Content-type', 'text/html; encoding=utf-8'],
                        ['Content-length', 45]
                        ],
                    'status': 200,
                    'ssl_fingerprint': None,
                    'ssl_verified': False,
                    'ip': '81.11.23.101',
                    'content': 'content body is here',
                    'hash': None,
                    }
                }
            }

        cls.sample2 = {
            'network_name': 'ISP2-unfiltered',
            'request_data': {
                'req': {
                    'url': "http://www.example.com",
                    'headers': [],
                    'body': None,
                    'hash': None,
                    'method': "GET"
                    },
                'rsp': {
                    'headers': [
                        ['Content-type', 'text/html'],
                        ['Content-length', 45]
                        ],
                    'status': 403,
                    'ssl_fingerprint': None,
                    'ssl_verified': False,
                    'ip': '99.99.99.99',
                    'content': 'content body is forbidden',
                    'hash': None,
                    }
                }
            }
        cls.sample3 = {
            'network_name': 'ISP-region2-unfiltered',
            'request_data': {
                'req': {
                    'url': "http://www.example.com",
                    'headers': [],
                    'body': None,
                    'hash': None,
                    'method': "GET"
                    },
                'rsp': {
                    'headers': [
                        ['Content-type', 'text/html'],
                        ['X-Disallowed-Type', 'geo'],
                        ['Content-length', 46]
                        ],
                    'status': 200,
                    'ssl_fingerprint': None,
                    'ssl_verified': False,
                    'ip': '101.99.99.99',
                    'content': 'content body is forbidden from this area',
                    'hash': None,
                    }
                }
            }

    def setUp(self):
        self.detector = anomaly.AnomalyDetector()
        pass

    def testAnalyze1(self):
        self.detector.collate_analysis(self.sample1['request_data'], self.sample1['request_data'])

    def testAnalyze2(self):
        self.detector.collate_analysis(self.sample1['request_data'], self.sample2['request_data'])

    def testAnalyze3(self):
        self.detector.collate_analysis(self.sample1['request_data'], self.sample3['request_data'])

    def testAnalyzeReal(self):
        self.detector.collate_analysis(AnomalyTestFixtures.UNBLOCKED, AnomalyTestFixtures.BLOCKED)
