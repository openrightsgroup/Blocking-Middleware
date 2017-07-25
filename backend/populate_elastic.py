#!/usr/bin/env

import os
import sys
import json
import logging
import argparse

import psycopg2
import psycopg2.extras
import requests

LOADERS = {}

def register(s):
    LOADERS[s.__name__] = s

def debug_response(data, r):
    logging.debug("%s", json.dumps(data))

    for k,v in r.request.headers.iteritems():
        logging.debug("req %s: %s", k, v)


    for k,v in r.headers.iteritems():
        logging.debug("rsp %s: %s", k,v)
    logging.debug("%s", r.content)

@register
def categories(conn):
    c = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    c.execute("""select id, display_name, name, 
        block_count, blocked_url_count, total_block_count, total_blocked_url_count
        from categories
        where total_block_count >0
        order by name""")

    for row in c:
        parts = row['display_name'].split('/')
        data = { 
            'id': row['id'],
            'name': row['name'],
            'block_count': row['block_count'],
            'blocked_url_count': row['blocked_url_count'],
            'total_block_count': row['total_block_count'],
            'total_blocked_url_count': row['total_blocked_url_count'],
            'display_name':  parts
        }

        r = requests.put(args.elastic + '/categories/category/{0}'.format(row['id']),
            data=json.dumps(data),
            headers={
                'Content-Type': 'application/json'
            })

        logging.info("Added category %s: %s -> %s", row['id'], row['name'],
            r.status_code)
        if r.status_code not in (200,201):
            debug_response(data, r)

@register
def urls(conn):
    c = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    c.execute("""select urlid, url, tags, source, title, description
        from urls
        left join site_description using (urlid)
        where urlid in (select urlid from url_latest_status where status = 'blocked' and network_name in 
            (select name from isps where queue_name is not null)
            )
        order by urlid
        """)
    logging.info("Found: %s", c.rowcount)
    for row in c:
        data = {
            'id': row['urlid'],
            'title': row['title'],
            'tags': row['tags'],
            'url': row['url'], 
            'source': row['source']
            }
        try:
            desc = json.loads(row['description'])
            if 'keywords' in desc:
                parts = desc['keywords'].split(',')
                if len(parts) > 1:
                    data['keywords'] = [x.strip() for x in parts]
                else:
                    data['keywords'] = [x.strip() for x in desc['keywords'].split()]
            if 'description' in desc:
                data['description'] = desc['description']
        except Exception,v:
            logging.warn("URL error: %s from %s", repr(v), row['urlid'])

        r = requests.put(args.elastic + '/urls/url/{0}'.format(row['urlid']),
            data=json.dumps(data),
            headers={
                'Content-Type': 'application/json'
            })

        logging.info("Added url %s: %s -> %s", row['urlid'], row['url'],
            r.status_code)
        if r.status_code not in (200,201):
            debug_response(data, r)

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--db', default=os.getenv('DB'), 
        help="DB connection string")
    parser.add_argument('--elastic', help="Elastic server address")
    parser.add_argument('--verbose', '-v', action='store_true', 
        default=False, help="Verbose logging")
    parser.add_argument(dest='loaders', nargs="*", default='all',
        choices=LOADERS.keys() + ['all'], help="loaders to run")
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.INFO if args.verbose else logging.WARN,
        datefmt="[%Y-%m-%d %H:%M:%S]",
        format='%(asctime)s\t%(name)s\t%(levelname)s\t%(message)s'
        )
    logging.getLogger("urllib3").setLevel(logging.ERROR)
    logging.getLogger("requests.packages").setLevel(logging.ERROR)

    conn = psycopg2.connect(args.db)

    for loader in args.loaders if 'all' not in args.loaders else LOADERS.keys():
        logging.info("Running loader: %s", loader)
        LOADERS[loader](conn)
