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

def get_categories(conn, urlid):
    c = conn.cursor()
    c.execute("""select categories.id, categories.name, categories.display_name 
            from categories 
            inner join categories x on x.tree <@ categories.tree 
            inner join url_categories on x.id = url_categories.category_id
            where url_categories.urlid = %s
            order by categories.tree""",
            [ urlid ])
    out = [ row[0] for row in c ]
    c.close()
    return out

def update_elastic(row, categories=[]):
    data = {
        'id': row['urlid'],
        'title': row['title'],
        'tags': row['tags'],
        'url': row['url'], 
        'source': row['source'],
        'categories': categories
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

@register
def urls(conn):
    c = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    c.execute("""select urls.urlid, url, tags, source, title, description, blocked_dmoz.urlid as blocked_dmoz
        from urls
        left join site_description using (urlid)
        left join blocked_dmoz on blocked_dmoz.urlid = urls.urlid
        where urls.urlid in (select urlid from url_latest_status where status = 'blocked' and network_name in 
            (select name from isps where queue_name is not null)
            )
        order by urlid
        """)
    logging.info("Found: %s", c.rowcount)
    for row in c:
        categories = None
        if row['blocked_dmoz']:
            categories = get_categories(conn, row['urlid'])
        update_elastic(row, categories)

@register
def changes(conn):
    c = conn.cursor()
    c2 = conn.cursor(cursor_factory=psycopg2.extras.DictCursor)
    if args.since is None and args.interval is None:
        print "Required args: <since> or <interval>"
        sys.exit(1)

    if args.since:
        c.execute("select distinct urlid from url_status_changes where created > %s",
            [args.since])
    elif args.interval:
        c.execute("select distinct urlid from url_status_changes where created >= CURRENT_TIMESTAMP - INTERVAL %s",
            [args.interval])

    for row in c:
        # count up blocks from active networks
        urlid = row[0]
        logging.info("Processing urlid: %s", urlid)
        c2.execute("""select count(*) ct from url_latest_status where 
                status = 'blocked' AND 
                network_name in (select name from isps where queue_name is not null)
                AND urlid = %s""",
            [urlid])
        row2 = c2.fetchone()
        if row2['ct'] == 0:
            logging.info("url not blocked")
            # no active-network blocks in place for this URL
            c2.execute("delete from blocked_dmoz where urlid = %s",
                [urlid])
            if not args.dummy:
                r = requests.delete(args.elastic + '/urls/url/{0}'.format(row[0]))
                logging.info("DELETE status: %s", r.status_code)
        else:
            logging.info("url is blocked on %s networks", row2['ct'])
            # blocked on active networks

            # get data for elastic
            c2.execute("""select urlid, url, tags, source, title, description
                from urls
                left join site_description using (urlid)
                where urlid = %s
                """,
                [urlid])
            row2 = c2.fetchone()

            if not args.dummy:
                update_elastic(row2, get_categories(conn, urlid))

            # find out if the URL is in any categories
            c2.execute("select count(*) ct from url_categories where urlid = %s",
                [urlid])
            row2 = c2.fetchone()
            if row2['ct'] > 0:
                logging.info("Adding %s to dmoz", urlid)
                # it's in a category
                c2.execute("SAVEPOINT save1")
                try:
                    c2.execute("insert into blocked_dmoz(urlid) values (%s)",
                        [urlid])
                except:
                    # duplicate key error
                    logging.warn("Exception adding to blocked_dmoz")
                    c2.execute("ROLLBACK TO save1")
        if not args.dummy:
            conn.commit()
        else:
            logging.info("DUMMY mode: rollback")
            conn.rollback()





if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--db', default=os.getenv('DB'), 
        help="DB connection string")
    parser.add_argument('--elastic', help="Elastic server address")
    parser.add_argument('--verbose', '-v', action='store_true', default=False, 
        help="Verbose logging")
    parser.add_argument('--dummy', '-n', action='store_true', default=False, 
        help="Dummy mode")
    parser.add_argument('--since', default=None, help="Start timestamp for changes")
    parser.add_argument('--interval', default=None, help="timeperiod for changes")
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

    if args.dummy:
        logging.info("DUMMY mode on")

    conn = psycopg2.connect(args.db)

    loaders = args.loaders if 'all' not in args.loaders else LOADERS.keys()
    logging.info("Running loaders: %s", loaders)
    for loader in loaders:
        logging.info("Running loader: %s", loader)
        LOADERS[loader](conn)
