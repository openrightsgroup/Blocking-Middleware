#!/usr/bin/env python

import sys
import yaml
import json
import datetime
import argparse

ENABLE_CATEGORY_LIST = False


def get_parser():
    parser = argparse.ArgumentParser(description="Export rules to json config")
    parser.add_argument('source', default='rules.yml', help='Source YAML')
    parser.add_argument('--date', default=datetime.date.today().strftime('%Y%m%d'), help="Datecode")
    parser.add_argument('--release', '-r', type=int, default=1, help="Release counter")
    return parser


def export_isp(isp):
    cp = isp.copy()
    # not using values because we want to maintain order
    cp['match'] = [isp['match'][x] for x in sorted(isp['match'])]
    if 'blocktype' in cp:
        cp['blocktype'] = [isp['blocktype'][x] for x in sorted(isp['blocktype'])]
    if 'category' in cp:
        if isinstance(cp['category'], list):
            cp['categorizers'] = cp['category']
            cp['category'] = cp['categorizers'][0]
        if isinstance(cp['category'], dict):
            names = list(cp['category'].keys())
            names.sort()
            cp['categorizers'] = [cp['category'][x] for x in names]
            cp['category'] = cp['categorizers'][0]
        if not ENABLE_CATEGORY_LIST:
            del cp['categorizers']
    return cp


def main():
    parser = get_parser()
    args = parser.parse_args()

    with open(args.source) as fp:
        srcdata = yaml.safe_load(fp)

    output = {
        'rules': [export_isp(isp) for isp in srcdata['isps']],
        }
    output.update(srcdata['info'])
    output['version'] = "{}{:02}".format(args.date,
                                         args.release)

    json.dump(output, sys.stdout, indent=2)

if __name__ == '__main__':
    main()
