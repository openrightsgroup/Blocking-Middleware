#!/usr/bin/env python

import os
import sys
import MySQLdb
import psycopg2

import argparse
import logging
import ConfigParser

parser = argparse.ArgumentParser()
parser.add_argument('-c', dest='config', default='syncpg.ini', help="Path to config file")
parser.add_argument('--loop', default=False, action='store_true', help="Loop until no rows copied")
args = parser.parse_args()

logging.basicConfig(
    level=logging.INFO,
    datefmt="[%Y-%m-%d %H:%M:%S]",
    format="%(asctime)s\t%(levelname)s\t%(message)s"
    )

def read_config(configfile):
    cfg = ConfigParser.ConfigParser()
    cfg.read([configfile])

    return cfg

class Copier(object):
    FIELDS = []
    TABLE = None
    BATCH = 1500
    KEY = "id"

    def __init__(self, src, dest):
        self.src = src
        self.dest = dest

    def copy(self):
        c = self.src.cursor()
        c2 = self.dest.cursor()

        c2.execute("select max({1}) from {0}".format(self.TABLE, self.KEY))
        row = c2.fetchone()
        start = row[0] or 0
        logging.info("Start at: %s", start)

        c.execute("select {3} from {0} where {2} > %s order by {2} limit {1}".format(
            self.TABLE, self.BATCH, self.KEY,
            ",".join(self.FIELDS)
            ),
            [start])
        rows = c.fetchall()
        n = len(rows)
        c2.executemany("""insert into {0}({1}) values ({2})""".format(
            self.TABLE,
            ",".join(self.FIELDS),
            ",".join(["%s"] * len(self.FIELDS))
            ),
            rows)

        #for n, row in enumerate(c):
        #    assert len(row) == len(self.FIELDS)
        #    c2.execute("""insert into {0}({1}) values ({2})""".format(
        #        self.TABLE,
        #        ",".join(self.FIELDS),
        #        ",".join(["%s"] * len(self.FIELDS))
        #        ),
        #        row)

        self.dest.commit()
        logging.info("Rows copied: %s", n)
        return n

class Results(Copier):
    TABLE = 'results'
    FIELDS = "id urlid probeid config ip_network status http_status network_name created filter_level category blocktype".split()

class UrlLatestStatus(Copier):
    TABLE = 'url_latest_status'
    FIELDS = "id urlid network_name status created category blocktype".split()

class ISPReports(Copier):
    TABLE = 'isp_reports'
    FIELDS = "id name email urlid network_name created message report_type unblocked notified send_updates last_updated".split()

class URLs(Copier):
    TABLE = 'urls'
    FIELDS = "urlid url hash source lastpolled inserted status".split()
    KEY = "urlid"

def main():
    cfg = read_config(args.config)
    mysql = dict(cfg.items('mysql'))
    pg = dict(cfg.items('postgres'))
    src = MySQLdb.connect(**mysql)
    dest = psycopg2.connect(**pg)
    classes = [UrlLatestStatus,ISPReports,URLs]
    while True:
        rows = []
        for copyclass in classes:
            logging.info("Running: %s", copyclass.__name__)
            copier = copyclass(src, dest)
            ret = copier.copy()
            if ret == 0:
                # remove from list when done
                classes.remove(copyclass)
            rows.append(ret)
        if not args.loop:
            break
        if not any(rows):
            # finished
            break

if __name__ == '__main__':
    main()
