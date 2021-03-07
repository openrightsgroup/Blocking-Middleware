#!/usr/bin/python

import sys
import email
import email.utils


def main():
    msg = email.message_from_file(sys.stdin)

    _, toaddr = email.utils.parseaddr(msg['To'])
    fromname, _ = email.utils.parseaddr(msg['From'])

    if 'reply-isp-' in toaddr:
        del msg['From']
        msg['From'] = email.utils.formataddr((fromname, toaddr.replace('reply-isp-', 'reply-', 1)))
    elif 'reply-' in toaddr:
        del msg['From']
        msg['From'] = email.utils.formataddr((fromname, toaddr.replace('reply-', 'reply-isp-', 1)))
    else:
        # do nothing
        pass

    sys.stdout.write(msg.as_string())


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print >>sys.stderr, "Error: {}".format(exc)
        sys.exit(1)
