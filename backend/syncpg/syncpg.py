#!/usr/bin/env python

import os
import sys
import MySQLdb
import psycopg2

import argparse
import ConfigParser

parser = argparse.ArgumentParser()
parser.add_argument('-c', dest='config', default='syncpg.ini', help="Path to config file")
args = parser.parse_args()


def read_config(configfile):
    cfg = ConfigParser.ConfigParser()
    cfg.read([configfile])

    return cfg

class Copier(object):
    FIELDS = []
    TABLE = None
    BATCH = 500

    def __init__(self, src, dest):
        self.src = src
        self.dest = dest

    def copy(self):
        c = self.src.cursor()
        c2 = self.dest.cursor()

        c2.execute("select max(id) from {0}".format(self.TABLE))
        row = c2.fetchone()
        start = row[0] or 0

        c.execute("""select * from {0} where id > %s
            order by id limit {1}""".format(self.TABLE, self.BATCH),
            [start])
        n = 0
        for n, row in enumerate(c):
            assert len(row) == len(self.FIELDS)
            c2.execute("""insert into {0}({1}) values ({2})""".format(
                self.TABLE,
                ",".join(self.FIELDS),
                ",".join(["%s"] * len(self.FIELDS))
                ),
                row)

        dest.commit()
        print "Rows copied: ", n
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


def main():
    cfg = read_config(args.config)
    mysql = dict(cfg.items('mysql'))
    pg = dict(cfg.items('postgres'))
    src = MySQLdb.connect(**mysql)
    dest = psycopg2.connect(**pg)
    for copyclass in Results,UrlLatestStatus,ISPReports:
        print "Running: ", copyclass.__name__
        copier = copyclass(src, dest)
        copier.copy()

if __name__ == '__main__':
    main()
