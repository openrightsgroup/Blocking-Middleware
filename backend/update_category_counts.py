
import MySQLdb

from MySQLdb.cursors import DictCursor,SSDictCursor


conn2 = MySQLdb.connect(host='localhost',user='root',db='blocked')
c2 = conn2.cursor(cursorclass=DictCursor)

c2.execute("select distinct name1 from categories")
catlist = [x['name1'] for x in c2]

for cat in catlist:
    conn = MySQLdb.connect(host='localhost',user='root',db='blocked')
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



    

