#!/usr/bin/python

# a simple scheduler to run scripts.  These are normally
# run by cron on the live servers, but putting cron+syslog+mail
# into a docker container is clumsy.

import sys
import time
import logging
import argparse
import datetime
import subprocess
import ConfigParser

logger = logging.Logger('scheduler')
handler = logging.StreamHandler(sys.stdout)
handler.setFormatter(logging.Formatter("%(levelname)s\t%(asctime)s\t%(message)s", "[%Y-%m-%d %H:%M:%S]"))
logger.setLevel(logging.INFO)
logger.addHandler(handler)

class Job(object):
    def __init__(self, name, cmd, interval, delay, run_on_start=False):
        self.name = name
        self.cmd = cmd
        self.interval = interval
        self.delay = delay
        self.lastrun = None
        self.nextrun = self.trim_time(self.get_current_time() + datetime.timedelta(0, (self.interval+self.delay) * 60))
        self.run_on_start = run_on_start
        logger.info("Loaded %s, next run: %s", self.name, self.nextrun)

    def get_current_time(self):
        return datetime.datetime.now()

    @staticmethod
    def trim_time(dt):
        return dt.replace(second=0, microsecond=0)

    def check(self):
        if self.lastrun is None and self.run_on_start:
            return True
        if self.nextrun < self.get_current_time():
            return True
        return False
    
    def run(self):
        logger.debug("Preparing to run: %s, %s", self.name, self.cmd)
        self.lastrun = self.get_current_time()
        ret = subprocess.call(self.cmd, shell=True)
        self.nextrun = self.trim_time(self.lastrun + datetime.timedelta(0, self.interval*60))
        logger.info("Ran %s; exit status=%s; next run=%s", self.name, ret, self.nextrun)

def main():
    parser = argparse.ArgumentParser(description="Run scheduled jobs")
    parser.add_argument('--config','-c', help="path to config file")
    parser.add_argument('--verbose','-v', action='store_true', help="Verbose mode")
    parser.add_argument('--run-on-startup','-J', action='store_true', help="Run jobs on startup")
    args = parser.parse_args()

    if args.verbose:
        logger.setLevel(logging.DEBUG)

    cfg = ConfigParser.ConfigParser()
    cfg.read([args.config])

    jobs = []

    for group in cfg.sections():
        groupconfig = dict(cfg.items(group))
        if groupconfig.get('enabled', '1') == '0':
            continue
        jobs.append(Job(
            group,
            groupconfig['command'],
            int(groupconfig['interval']),
            int(groupconfig.get('delay', 0)),
            args.run_on_startup,
            ))

    while True:
        # main loop
        for job in jobs:
            if job.check():
                job.run()
                time.sleep(5)
        time.sleep(60)


if __name__ == '__main__':
    main()
