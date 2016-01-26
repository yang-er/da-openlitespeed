#!/bin/bash
/usr/local/lsws/bin/config drop_domain $username $domain
/usr/local/lsws/bin/config ws_rewrite
/usr/local/lsws/bin/config lsws_restart

