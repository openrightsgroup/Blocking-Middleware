#!/usr/bin/env python

import sys
import yaml
import json
import datetime
import argparse


def get_parser():
    parser = argparse.ArgumentParser(description="Export rules to json config")
    parser.add_argument('--release', '-r', type=int, default=1, help="Release counter")
    return parser

def main():
    parser = get_parser()
    args = parser.parse_args()

    with open('rules.yml') as fp:
        srcdata = yaml.load(fp)

    output = {
        'rules': srcdata['isps'],
        }
    output.update(srcdata['info'])
    output['version'] = "{}{:02}".format(datetime.date.today().strftime('%Y%m%d'),
                                         args.release)

    json.dump(output, sys.stdout, indent='  ')

if __name__ == '__main__':
    main()
