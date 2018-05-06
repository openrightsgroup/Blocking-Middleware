#!/usr/bin/python

import os
import sys
import StringIO
import psycopg2
import shutil
import email.parser
import email.utils

def import_mail(conn, fp):
    msgtxt = StringIO.StringIO()
    shutil.copyfileobj(fp, msgtxt)
    msgtxt.seek(0)
    parser = email.parser.Parser()
    msg = parser.parse(msgtxt)

    to = msg['To']
    name, addr = email.utils.parseaddr(to)
    print name, addr

    mailname = addr.split('@')[0]
    
    print mailname

    c = conn.cursor()
    c.execute("select id from isp_reports where mailname = %s", [mailname])
    row = c.fetchone()
    if row is None:
        return
    report_id = row[0]

    c.execute("insert into isp_report_emails(report_id, message, created) values (%s,%s,now())",
              [report_id, msgtxt.getvalue()])
    conn.commit()


if __name__ == '__main__':
    conn = psycopg2.connect('dbname=blocked')
    import_mail(conn, sys.stdin)

