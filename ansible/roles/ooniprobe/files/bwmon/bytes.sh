#!/bin/bash

DT=$(date '+%Y-%m-%d %H:%M:%S')

(echo -ne "$DT\t"; grep eth0 /proc/net/dev) >> /var/log/bandwidth

