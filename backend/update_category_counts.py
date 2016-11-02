#!/usr/bin/env python

# vim: sw=4 ts=8 sts=4 et : 

import time
import argparse
import collections
import MySQLdb

from MySQLdb.cursors import DictCursor,SSDictCursor

parser = argparse.ArgumentParser()
parser.add_argument('--dbname', help="Database name")
parser.add_argument('--dbuser', help="Database user")
parser.add_argument('--dbpass', help="Database pass")
args = parser.parse_args()

conn = MySQLdb.connect(host='localhost',user=args.dbuser,db=args.dbname, passwd=args.dbpass)
conn2 = MySQLdb.connect(host='localhost',user=args.dbuser,db=args.dbname, passwd=args.dbpass)
c = conn.cursor(cursorclass=DictCursor)

st0 = time.time()
if True:
    c.execute("""create temporary table cat_tmp (primary key(id)) as 
    select url_categories.category_id id, count(distinct uls.urlid) blocked_url_count, count(distinct uls.urlid, uls.network_name) block_count 
    from url_categories 
    inner join url_latest_status uls on uls.urlid = url_categories.urlid 
    inner join isps on isps.name = uls.network_name and isps.queue_name is not null
    where status = 'blocked' 
    group by url_categories.category_id;""")

    c.execute("update categories set block_count=0, blocked_url_count=0, total_block_count=0, total_blocked_url_count=0")
    c.execute("""update categories 
    inner join cat_tmp using(id) 
    set 
    categories.block_count = cat_tmp.block_count, 
    categories.blocked_url_count = cat_tmp.blocked_url_count""")
    conn.commit()
    
print "Rebuild time: ", time.time() - st0

c.execute("select distinct name1 from categories")
catlist = [x['name1'] for x in c]


for cat in catlist:
    print cat
    block_count = collections.defaultdict(lambda: 0)
    blocked_url_count = collections.defaultdict(lambda: 0)
    st = time.time()
    ids = {}
    c  = conn.cursor (cursorclass=SSDictCursor)
    c2 = conn2.cursor()
    c.execute("select id,name1,name2,name3,name4,name5,name6,name7,name8,name9,name10, block_count, blocked_url_count from categories where name1=%s order by name1, name2, name3, name4, name5, name6, name7, name8, name9, name10",[cat])
    for row in c:
        key = [ row['name'+str(x)] for x in range(1,11) if row['name'+str(x)] is not None ]
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
        c2.execute(sql, sqlargs)
        if c2.rowcount == 0:
            #print "Total not changed: ", k
            pass
            

    conn2.commit()
    conn.commit()
    print "Totals: {0} blocks={1}, blocked_urls={2}, keys={3}, ids={4}, time={5}".format(
        cat, block_count[(cat,)], blocked_url_count[(cat,)], len(block_count), len(ids), time.time() - st
        )
    
print "Total elapsed: ", time.time() - st0
    

