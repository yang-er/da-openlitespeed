#!/bin/bash
/usr/local/lsws/bin/config drop_sub $username $domain $subdomain
/usr/local/lsws/bin/config ws_rewrite sub- $domain $subdomain
/usr/local/lsws/bin/config lsws_restart

