#!/bin/bash
/usr/local/lsws/bin/config add_domain $username $newdomain
/usr/local/lsws/bin/config ws_rewrite
/usr/local/lsws/bin/config lsws_restart

