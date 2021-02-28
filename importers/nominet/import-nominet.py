
import os
import sys
import glob
import socket
import logging
import argparse
import datetime
import subprocess
import configparser

import psycopg2
import paramiko


def get_parser():
    parser = argparse.ArgumentParser(description="Import and process nominet zone additions")
    parser.add_argument('--config', '-c', default='import-nominet.cfg', help="Path to config file")
    parser.add_argument('--verbose', '-v', action='store_true', default=False, help="Verbose mode")
    parser.add_argument('--debug', action='store_true', default=False, help="Verbose mode")
    return parser


def connect(cfg):
    transport = paramiko.Transport((cfg.get('sftp', 'host'), 22))
    transport.connect(None,
                      cfg.get('sftp', 'user'),
                      cfg.get('sftp', 'password'),
                      None)

    sftp = paramiko.SFTPClient.from_transport(transport)
    sftp.chdir(cfg.get('sftp', 'path'))
    return sftp


def connect_db(cfg):
    return psycopg2.connect(host=cfg.get('db', 'host'),
                            user=cfg.get('db', 'user'),
                            password=cfg.get('db', 'password'),
                            dbname=cfg.get('db', 'dbname'),
                            )


def unpack(cfg, filename):
    workdir = get_workdir(cfg, filename)
    try:
        os.mkdir(workdir)
    except:
        pass

    if not glob.glob(os.path.join(workdir, 'db-dump-*')):
        logging.info("Already unpacked: %s", workdir)
        subprocess.check_call(['unzip', '../'+filename], cwd=workdir)


def sortfile(filename):
    if not os.path.isfile(filename + '.sorted'):
        logging.debug("Sorting: %s", filename)
        with open(filename, 'r') as fpin, open(filename + '.sorted', 'w') as fpout:
            subprocess.check_call(['sort'], stdin=fpin, stdout=fpout)
    else:
        logging.info("Already sorted: %s", filename)
    return filename + '.sorted'


def get_workdir(cfg, filename):
    tmpname, _ = os.path.splitext(filename)
    return os.path.join(cfg.get('paths', 'download'), tmpname)


def compare(cfg, filename):

    dbdumpname = 'db-dump-' + getdate().strftime('%Y%m%d') + '.csv'
    dbdumppath = os.path.join(get_workdir(cfg, filename), dbdumpname)

    prevdump = glob.glob(os.path.join(cfg.get('paths', 'download'), 'prev', 'db-dump*.csv'))[0]

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
            return prefix + name
        except socket.gaierror as exc:
            logging.debug("Resolution failed: %s, %s", prefix + name, str(exc))
        except Exception as err:
            logging.warn("Error resolving: %s: %s", prefix+name, str(err))


def resolve_iter(it):
    for name in it:
        res = resolve(name)
        logging.debug("Resolve? %s -> %s", name, res)
        if res:
            yield res


def getdate():
    return datetime.date.today() - datetime.timedelta(1, 0)


def dbstore(conn, name):
    c = conn.cursor()
    c.execute("insert into domains(domain, created) values (%s, now())",
              [name])
    c.close()
    conn.commit()


def main():
    parser = get_parser()
    args = parser.parse_args()

    logging.basicConfig(level=logging.DEBUG if args.debug else
                              logging.INFO if args.verbose else logging.WARN,
                        format="%(asctime)s\t%(levelname)s\t%(message)s",
                        datefmt="[%Y-%m-%d %H:%M:%S")

    cfg = configparser.RawConfigParser()
    cfg.read([args.config])

    try:
        os.makedirs(cfg.get('paths', 'download'))
    except:
        pass

    dt = getdate()
    filename = dt.strftime('ukdata-%Y%m%d.zip')
    logging.info("Bundle: %s", filename)

    destpath = os.path.join(cfg.get('paths', 'download'), filename)
    if not os.path.isfile(destpath):
        sftp = connect(cfg)
        logging.info("Retrieving: %s", filename)
        sftp.get(filename, destpath)
    else:
        logging.info("Already downloaded: %s", filename)


    unpack(cfg, filename)

    conn = connect_db(cfg)

    for name in resolve_iter(compare(cfg, filename)):
        logging.info("Got: %s", name)
        dbstore(conn, name)
        if args.debug:
            break


if __name__ == '__main__':
    main()
