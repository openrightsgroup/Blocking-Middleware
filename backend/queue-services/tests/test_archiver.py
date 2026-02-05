
import unittest

from archiver import ArchiveService

class ArchiverTests(unittest.TestCase):
    def setUp(self):
        pass
    
    def test_testing_snapshot(self):
        svc = ArchiveService()
        ret = svc.testing_snapshot("https://www.example.com/page.html")
        self.assertEqual(ret, "http://localhost:8401/save/datecode/www.example.com/page.html")

    def test_delay(self):
        svc = ArchiveService()

        self.assertEqual(svc.get_delay(0), 30)
        self.assertEqual(svc.get_delay(30), 60)
        self.assertEqual(svc.get_delay(60), 120)
        self.assertEqual(svc.get_delay(960), 600)

    def test_testing_flag(self):
        ArchiveService.TESTING = True
        svc = ArchiveService()
        
        self.assertEqual(svc.is_testing(), True)
