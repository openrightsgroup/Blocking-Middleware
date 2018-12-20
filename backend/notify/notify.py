#!/usr/bin/python

import os
import sys
import jinja2

import argparse

parser = argparse.ArgumentParser()
parser.add_argument('--template','-t', default='notify.j2')
args = parser.parse_args()

j2env = jinja2.Environment(
        loader=jinja2.FileSystemLoader(os.path.join(os.path.dirname(sys.argv[0]), 'templates'))
        )
tmpl = j2env.get_template(args.template)

data = {k.replace('NAGIOS_','').lower():v 
        for (k,v) in os.environ.iteritems()
        if k.startswith('NAGIOS_')}
s = tmpl.render(**data)

print s


