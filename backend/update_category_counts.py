#!/usr/bin/env python

import argparse
import MySQLdb

from MySQLdb.cursors import DictCursor,SSDictCursor

parser = argparse.ArgumentParser()
parser.add_argument('--dbname', help="Database name")
parser.add_argument('--dbuser', help="Database user")
parser.add_argument('--dbpass', help="Database pass")
args = parser.parse_args()

conn2 = MySQLdb.connect(host='localhost',user=args.dbuser,db=args.dbname, passwd=args.dbpass)
c2 = conn2.cursor(cursorclass=DictCursor)

c2.execute("select distinct name1 from categories")
catlist = [x['name1'] for x in c2]

for cat in catlist:
    conn = MySQLdb.connect(host='localhost',user=args.dbuser,db=args.dbname)
    c  = conn.cursor (cursorclass=SSDictCursor)
    c.execute("select id,name1,name2,name3,name4,name5,name6,name7,name8,name9,name10 from categories where name1=%s order by name1, name2, name3, name4, name5, name6, name7, name8, name9, name10",[cat])
    for row in c:
        for i in range(10, 0, -1):
            if row["name"+str(i)] is not None:
                print row['id'],("  " * i), row["name"+str(i)],
                break

        c2.execute("""select count(distinct uls.urlid) blocked_url_count, count(distinct uls.urlid,network_name) block_count
            from
            url_categories inner join url_latest_status uls on uls.urlid = url_categories.urlid
            where uls.status = 'blocked' and category_id = %s
            """, [row['id']])
        row2 = c2.fetchone()
        print row2['blocked_url_count'], row2['block_count']
        c2.execute("update categories set block_count = %s, blocked_url_count = %s where id = %s",
            [row2['block_count'], row2['blocked_url_count'], row['id']])

        conn2.commit()
    conn.commit()

for i in range(1,9):
    sql = """create table cat_tmp as select {f},sum(block_count) total_block_count, sum(blocked_url_count) total_blocked_url_count
        from categories x where x.name{i} is not null group by  {g}""".format(
            i=i,
            f=",".join(["name{0}".format(x) for x in range(1, i+1)]),
            g=",".join([ "x.name{0}".format(x) for x in range(1, i+1) ])
            )
    print sql
    c.execute( sql )
    sql = "create index cat_tmp_idx on cat_tmp({f})".format(
        f = ",".join(["name{0}".format(x) for x in range(1, min(i+1,4))]),
        )
    print sql
    c.execute(sql)

    sql2 = """update categories,cat_tmp x set 
        categories.total_block_count = x.total_block_count, 
        categories.total_blocked_url_count = x.total_blocked_url_count
        where {w} and name{j} is null""".format(
            j = i+1,
            w=" and ".join([ "categories.name{0} = x.name{0}".format(x) for x in range(1, i+1) ])
            )
    print sql2
    c.execute( sql2 )
    c.execute("""drop table cat_tmp""")

    conn.commit()

c.execute("update categories set total_block_count = block_count, total_blocked_url_count = blocked_url_count where name10 is not null")
conn.commit()
    

