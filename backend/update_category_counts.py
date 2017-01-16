#!/usr/bin/env python

# vim: sw=4 ts=8 sts=4 et : 

import time
import argparse
import collections
import psycopg2

from psycopg2.extras import DictCursor

parser = argparse.ArgumentParser()
parser.add_argument('--dbname', help="Database name")
parser.add_argument('--dbuser', help="Database user")
parser.add_argument('--dbpass', help="Database pass")
args = parser.parse_args()

conn = psycopg2.connect(host='localhost',user=args.dbuser,dbname=args.dbname, password=args.dbpass)
c = conn.cursor(cursor_factory=DictCursor)

st0 = time.time()
if True:
    c.execute("""create temporary table cat_tmp  as 
    select url_categories.category_id id, count(distinct uls.urlid) as blocked_url_count, count(distinct uls.urlid ||'-'|| uls.network_name) as block_count 
    from url_categories 
    inner join url_latest_status uls on uls.urlid = url_categories.urlid 
    inner join isps on isps.name = uls.network_name and isps.queue_name is not null
    where status = 'blocked' 
    group by url_categories.category_id;""")
    c.execute("alter table cat_tmp add primary key(id)")

    c.execute("update categories set block_count=0, blocked_url_count=0, total_block_count=0, total_blocked_url_count=0")
    c.execute("""update categories 
    set 
    block_count = cat_tmp.block_count, 
    blocked_url_count = cat_tmp.blocked_url_count
    from cat_tmp
    where cat_tmp.id = categories.id
    
    """)
    conn.commit()
    
print "Rebuild time: ", time.time() - st0



block_count = collections.defaultdict(lambda: 0)
blocked_url_count = collections.defaultdict(lambda: 0)
st = time.time()
ids = {}
c  = conn.cursor (cursor_factory=DictCursor)
c.execute("select id,tree, block_count, blocked_url_count from categories order by tree")
for row in c:
    key = row['tree'].split('.')
    ids[tuple(key)] = row['id']
    #print "Key", key, row['block_count'], row['blocked_url_count']
    for k in [tuple(key[:x]) for x in range(1, len(key)+1)]:
        #print "K", k
        block_count[k] += row['block_count']
        blocked_url_count[k] += row['blocked_url_count']
     
for k in sorted(block_count):
    if block_count[k] == 0 and blocked_url_count[k] == 0:
        continue
    if not k in ids:
        print "ID unknown for: ", k
        continue
    sql = "update categories set total_block_count = %s, total_blocked_url_count = %s where id = %s"
    #sql += " AND ".join([ "name{0} = %s".format(x+1) for x in range(0,len(k))])
    sqlargs = [block_count[k], blocked_url_count[k], ids[k]] #+ list(k)
    #print sql, sqlargs
    c.execute(sql, sqlargs)
    if c.rowcount == 0:
        #print "Total not changed: ", k
        pass

conn.commit()
#print "Totals: {0} blocks={1}, blocked_urls={2}, keys={3}, ids={4}, time={5}".format(
#    cat, block_count[(cat,)], blocked_url_count[(cat,)], len(block_count), len(ids), time.time() - st
#    )
    
print "Total elapsed: ", time.time() - st0
    

