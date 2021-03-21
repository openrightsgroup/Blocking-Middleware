
import os
import sys
import glob
import socket
import logging
import argparse
import datetime
import subprocess
import configparser
import multiprocessing

import requests
import psycopg2
import paramiko

cfg = None
args = None


def get_parser():
    parser = argparse.ArgumentParser(description="Import and process nominet zone additions")
    parser.add_argument('--config', '-c', default='import-nominet.cfg', help="Path to config file")
    parser.add_argument('--verbose', '-v', action='store_true', default=False, help="Verbose mode")
    parser.add_argument('--no-submit', '-N', action='store_true', default=False, help="Do not submit to API")
    parser.add_argument('--debug', action='store_true', default=False, help="Verbose mode")

    parsers = parser.add_subparsers(dest='mode', help="Select mode")

    subp = parsers.add_parser('fetch')
    subp.add_argument('--no-submit', '-N', action='store_true', default=False, help="Do not submit to API")
    subp.add_argument('--keep', '-K', action='store_true', default=False, help="Keep old downloaded files")

    subp2 = parsers.add_parser('resubmit')
    subp2.add_argument('--age', '-d', type=int, help="Resubmit at age")

    subp3 = parsers.add_parser('download')
    subp3.add_argument('filename', type=str, help="filename to download")

    return parser


def connect_ssh():
    transport = paramiko.Transport((cfg.get('sftp', 'host'), 22))
    transport.connect(None,
                      cfg.get('sftp', 'user'),
                      cfg.get('sftp', 'password'),
                      None)

    sftp = paramiko.SFTPClient.from_transport(transport)
    sftp.chdir(cfg.get('sftp', 'path'))
    return sftp


def connect_db():
    return psycopg2.connect(host=cfg.get('db', 'host'),
                            user=cfg.get('db', 'user'),
                            password=cfg.get('db', 'password'),
                            dbname=cfg.get('db', 'dbname'),
                            )


def unpack(filename):
    workdir = get_workdir(filename)
    try:
        os.mkdir(workdir)
    except:
        pass

    dbfile = filename.replace('.zip', '.csv').replace('ukdata-', 'db-dump-')

    if os.path.isfile(os.path.join(workdir, dbfile)) or os.path.isfile(os.path.join(workdir, dbfile+'.sorted')):
        logging.info("Already unpacked: %s", workdir)
    else:
        logging.info("Unpacking %s", dbfile)
        subprocess.check_call(['unzip', '../'+filename, dbfile], cwd=workdir)


def sortfile(filename):
    if not os.path.isfile(filename + '.sorted') and not filename.endswith('.sorted'):
        logging.debug("Sorting: %s", filename)
        with open(filename, 'r') as fpin, open(filename + '.sorted', 'w') as fpout:
            subprocess.check_call(['sort'], stdin=fpin, stdout=fpout)
        os.unlink(filename)
    else:
        logging.info("Already sorted: %s", filename)
    if filename.endswith('.sorted'):
        return filename
    return filename + '.sorted'


def get_workdir(filename):
    tmpname, _ = os.path.splitext(filename)
    return os.path.join(cfg.get('paths', 'download'), tmpname)


def compare(filename):

    dbdumpname = 'db-dump-' + getdate().strftime('%Y%m%d') + '.csv'
    dbdumppath = os.path.join(get_workdir(filename), dbdumpname)

    prevdump = glob.glob(os.path.join(cfg.get('paths', 'download'), 'prev', 'db-dump*.csv*'))[0]
    logging.debug("Comparing %s <=> %s", prevdump, dbdumppath)

    prevdump = sortfile(prevdump)
    dbdumppath = sortfile(dbdumppath)


    proc = subprocess.Popen(['diff', '-c', prevdump, dbdumppath],
                            stdout=subprocess.PIPE)

    for line in proc.stdout:
        line = line.decode('utf8')
        if line.startswith('+ '):
            yield line.split()[-1].replace(',', '.')

    ret = proc.wait()
    logging.debug("compare result: %s", ret)


def resolve(name):
    for prefix in ('www.', ''):
        try:
            _ = socket.gethostbyname(prefix + name)
            return name, prefix + name
        except socket.gaierror as exc:
            logging.debug("Resolution failed: %s, %s", prefix + name, str(exc))
        except Exception as err:
            logging.warn("Error resolving: %s: %s", prefix+name, str(err))
        return name, None


def resolve_iter(it):
    for name in it:
        res = resolve(name)
        logging.debug("Resolve? %s -> %s", name, res)
        if res:
            yield res


def getdate():
    return datetime.date.today() - datetime.timedelta(1, 0)


def dbstore(conn, name, resolved):
    c = conn.cursor()
    try:
        c.execute("insert into domains(domain, created, resolved) values (%s, now(), %s) "
                  "returning id as id",
                  [name, resolved])
    except psycopg2.IntegrityError:
        logging.debug("Caught duplicate: %s", name)
        conn.rollback()
        c.execute("update domains set resolved = true where domain = %s returning id as id", [name])

    row = c.fetchone()
    c.close()
    conn.commit()
    return row[0]


def update_submitted(conn, recid, urlid=None):
    c2 = conn.cursor()
    c2.execute("update domains set submitted = now(), urlid=%s where id = %s", [urlid, recid])
    conn.commit()


def submit_api(domain):
    opts = dict(cfg.items('api'))
    suffixmap = dict(cfg.items('suffixmap'))

    data = {'url': 'http://'+domain,
            'queue': cfg.get('submission', 'queue'),
            'source': cfg.get('submission', 'source'),
            }

    if cfg.has_option('submission', 'tags'):
        tags = cfg.get('submission', 'tags').split(':')
    else:
        tags = []

    if suffixmap:
        # compare with suffixes by sort descending
        for suffix in sorted(suffixmap, key=len, reverse=True):
            if domain.endswith('.' + suffix):
                tags.append(suffixmap[suffix])
                break

    data['tags'] = ":".join(tags)

    if args.no_submit:
        logging.info("Dummy mode: data=%s", data)
    else:
        req = requests.post(opts['baseurl'] + "submit/url",
                            auth=(opts['user'], opts['secret']),
                            data=data
                            )
        logging.debug("API post result: %s; %s; %s", domain, req.status_code, req.json())
        return req.json().get('urlid')

def relink_prev(filename):
    tmpname, _ = os.path.splitext(filename)

    try:
        os.unlink(os.path.join(cfg.get('paths', 'download'), 'prev'))
    except OSError:
        pass

    os.symlink(tmpname, os.path.join(cfg.get('paths', 'download'), 'prev'))


def download():
    filename = args.filename
    destpath = os.path.join(cfg.get('paths', 'download'), filename)
    if not os.path.isfile(destpath):
        sftp = connect_ssh()
        logging.info("Retrieving: %s", filename)
        sftp.get(filename, destpath)
        sftp.close()
    else:
        logging.info("Already downloaded: %s", filename)
    unpack(filename)


def fetch():
    try:
        os.makedirs(cfg.get('paths', 'download'))
    except:
        pass

    dt = getdate()
    filename = dt.strftime('ukdata-%Y%m%d.zip')
    logging.info("Bundle: %s", filename)

    destpath = os.path.join(cfg.get('paths', 'download'), filename)
    if not os.path.isfile(destpath):
        sftp = connect_ssh()
        logging.info("Retrieving: %s", filename)
        sftp.get(filename, destpath)
        sftp.close()
    else:
        logging.info("Already downloaded: %s", filename)


    unpack(filename)

    pool = multiprocessing.Pool(cfg.getint('worker', 'threads'))

    conn = connect_db()

    for (name, resolvedname) in pool.imap_unordered(resolve, compare(filename), chunksize=16):
        recid = dbstore(conn, resolvedname or name, resolvedname is not None)
        logging.info("Got: %s %s", name, resolvedname)
        if resolvedname:
            urlid = submit_api(resolvedname)
            update_submitted(conn, recid, urlid)
            if args.debug:
                break

    if not args.debug:
        relink_prev(filename)


def resubmit():
    conn = connect_db()
    c = conn.cursor()
    c2 = conn.cursor()
    query_args = [
        datetime.date.today() - datetime.timedelta(args.age+1),
        datetime.date.today() - datetime.timedelta(args.age),
    ]
    logging.info("Date range: %s", query_args)
    c.execute("select domain, id from domains "
              "where created > %s and created < %s and resolved = true "
              "order by domain",
              query_args)
    for row in c:
        logging.info("Submitting %s(%s)", row[0], row[1])
        if args.no_submit:
            logging.debug("No submit")
        else:
            urlid = submit_api(row[0])
            update_submitted(conn, recid, urlid)


def main():
    global args
    global cfg
    parser = get_parser()
    args = parser.parse_args()

    logging.basicConfig(level=logging.DEBUG if args.debug else
                              logging.INFO if args.verbose else logging.WARN,
                        format="%(asctime)s\t%(levelname)s\t%(message)s",
                        datefmt="[%Y-%m-%d %H:%M:%S]")
    logging.info("Args: %s", args)
    logging.debug("foo")

    cfg = configparser.RawConfigParser()
    cfg.read([args.config])

    if args.mode == 'fetch':
        fetch()
    elif args.mode == 'resubmit':
        resubmit()
    elif args.mode == 'download':
        download()
    else:
        logging.warn("Unknown mode: %s", args.mode)


if __name__ == '__main__':
    main()
