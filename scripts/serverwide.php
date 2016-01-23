#!/usr/local/bin/php -n
<?php
$port_80 = 8088;
$port_443 = 8089;
$listener_binding = '4';

$lsws_config_file = '#
# PLAIN TEXT CONFIGURATION FILE WRITTEN BY DA-OLS
#

serverName                lshttpd
user                      apache
group                     apache
priority                  0
inMemBufSize              60M
swappingDir               /tmp/lshttpd/swap
autoFix503                1
gracefulRestartTimeout    300
mime                      $SERVER_ROOT/conf/mime.properties
showVersionNumber         1
adminEmails               root@localhost
adminRoot                 $SERVER_ROOT/admin/

errorlog $SERVER_ROOT/logs/error.log {
  logLevel                DEBUG
  debugLevel              0
  rollingSize             10M
  enableStderrLog         1
}

accesslog $SERVER_ROOT/logs/access.log {
  rollingSize             10M
  keepDays                30
  compressArchive         0
}

indexFiles                index.html,index.php

expires  {
  enableExpires           1
  expiresByType           image/*=A604800, text/css=A604800, application/x-javascript=A604800
}

tuning  {
  eventDispatcher         best
  maxConnections          2000
  maxSSLConnections       1000
  connTimeout             300
  maxKeepAliveReq         1000
  smartKeepAlive          0
  keepAliveTimeout        5
  sndBufSize              0
  rcvBufSize              0
  maxReqURLLen            8192
  maxReqHeaderSize        16380
  maxReqBodySize          2047M
  maxDynRespHeaderSize    8192
  maxDynRespSize          2047M
  maxCachedFileSize       4096
  totalInMemCacheSize     20M
  maxMMapFileSize         256K
  totalMMapCacheSize      40M
  useSendfile             1
  fileETag                28
  enableGzipCompress      1
  enableDynGzipCompress   1
  gzipCompressLevel       6
  compressibleTypes       text/*,application/x-javascript,application/javascript,application/xml, image/svg+xml
  gzipAutoUpdateStatic    1
  gzipStaticCompressLevel 6
  gzipMaxFileSize         1M
  gzipMinFileSize         300
  SSLCryptoDevice         null
}

fileAccessControl  {
  followSymbolLink        1
  checkSymbolLink         0
  requiredPermissionMask  000
  restrictedPermissionMask 000
}

perClientConnLimit  {
  staticReqPerSec         0
  dynReqPerSec            0
  outBandwidth            0
  inBandwidth             0
  softLimit               10000
  hardLimit               10000
  gracePeriod             15
  banPeriod               300
}

CGIRLimit  {
  maxCGIInstances         20
  minUID                  11
  minGID                  10
  priority                0
  CPUSoftLimit            10
  CPUHardLimit            50
  memSoftLimit            460M
  memHardLimit            470M
  procSoftLimit           400
  procHardLimit           450
}

accessDenyDir  {
  dir                     /etc/*
  dir                     /dev/*
  dir                     $SERVER_ROOT/conf/*
  dir                     $SERVER_ROOT/admin/conf/*
}

accessControl  {
  allow                   ALL
}

extprocessor lsphp5 {
  type                    lsapi
  address                 uds://tmp/lshttpd/lsphp.sock
  maxConns                35
  env                     PHP_LSAPI_MAX_REQUESTS=500
  env                     PHP_LSAPI_CHILDREN=35
  initTimeout             60
  retryTimeout            0
  persistConn             1
  respBuffer              0
  autoStart               1
  path                    $SERVER_ROOT/fcgi-bin/lsphp5
  backlog                 100
  instances               1
  priority                0
  memSoftLimit            2047M
  memHardLimit            2047M
  procSoftLimit           400
  procHardLimit           500
}

scripthandler  {
  add                     lsapi:lsphp5 php
}

railsDefaults  {
  railsEnv                1
  maxConns                5
  env                     LSAPI_MAX_REQS=1000
  env                     LSAPI_MAX_IDLE=60
  initTimeout             60
  retryTimeout            0
  pcKeepAliveTimeout      60
  respBuffer              0
  backlog                 50
  runOnStartUp            1
  extMaxIdleTime          300
  priority                3
  memSoftLimit            2047M
  memHardLimit            2047M
  procSoftLimit           500
  procHardLimit           600
}
';

$listener_list = array();
$vhosts_list = array();
$user_lists = array();

if($handle = opendir('/usr/local/directadmin/data/users')) {
	while(false !== ($file = readdir($handle))) {
		if($file != '.' && $file != '..') {
			$user_lists[] = $file;
		}
	}
	closedir($handle);
}

sort($user_lists);

foreach($user_lists as $user) {
	$user = trim($user);
	$domains = file('/usr/local/directadmin/data/users/'.$user.'/domains.list');
	foreach($domains as $domain) {
		$domain = trim($domain);
		$default_file = file('/usr/local/directadmin/data/users/'.$user.'/domains/'.$domain.'.conf');
		$ips = array_values(preg_grep('/^ip=/', $default_file));
		list(, $ip) = explode('=', $ips[0]);
		$ip = trim($ip);
		$ssls = array_values(preg_grep('/^ssl=/', $default_file));
		list(, $ssl) = explode('=', $ssls[0]);
		$ssl = trim($ssl);
		$vhosts_list[] = array('u' => $user, 'd' => $domain, 'r' => $domain);
		$listener_list["{$ip}:{$port_80}"][] = $domain;
		if($ssl == 'ON') $listener_list["{$ip}:{$port_443}"][] = $domain;

		$pointer_file_addr = '/usr/local/directadmin/data/users/'.$user.'/domains/'.$domain.'.pointers';
		if((file_exists($pointer_file_addr) && filesize($pointer_file_addr) != 0)) {
			$pointer_file = file($pointer_file_addr);
			foreach($pointer_file as $line) {
				list($pointed_domain, ) = explode('=alias', $line);
				$pointed_domain = trim($pointed_domain);
				$vhosts_list[] = array('u' => $user, 'd' => $domain, 'r' => $pointed_domain);
				$listener_list["{$ip}:{$port_80}"][] = $pointed_domain;
				if($ssl == 'ON') $listener_list["{$ip}:{$port_443}"][] = $pointed_domain;
			}
		}

		$sub_file_addr = '/usr/local/directadmin/data/users/'.$user.'/domains/'.$domain.'.subdomains';
		if((file_exists($sub_file_addr) && $sub_file = file($sub_file_addr))) {
			foreach($sub_file as $subdomain) {
				$subdomain = trim($subdomain).'.'.$domain;
				$vhosts_list[] = array('u' => $user, 'd' => $domain, 'r' => $subdomain);
				$listener_list["{$ip}:{$port_80}"][] = $subdomain;
				if($ssl == 'ON') $listener_list["{$ip}:{$port_443}"][] = $subdomain;
			}
		}
	}
}

foreach($vhosts_list as $vhost) {
	$lsws_config_file .= '
virtualhost '.$vhost['r'].' {
  vhRoot                  /home/'.$vhost['u'].'/domains/'.$vhost['d'].'/
  configFile              $SERVER_ROOT/conf/vhosts/'.$vhost['u'].'_'.$vhost['r'].'.conf
  allowSymbolLink         1
  enableScript            1
  restrained              1
  smartKeepAlive          1
  setUIDMode              2
}
';
}

$lsws_config_file .= '
virtualhost Default {
  vhRoot                  /var/www/
  configFile              $SERVER_ROOT/conf/vhosts/Default.conf
  allowSymbolLink         1
  enableScript            1
  restrained              1
  smartKeepAlive          1
  setUIDMode              2
}

listener Default {
  address                 *:'.$port_80.'
  binding                 '.$listener_binding.'
  secure                  0
  map                     Default *
}

listener Default {
  address                 *:'.$port_443.'
  binding                 '.$listener_binding.'
  secure                  1
  map                     Default *
}
';

foreach($listener_list as $listener => $all_domains) {
	$lsws_config_file .= '
listener '.$listener.' {
  address                 '.$listener.'
  binding                 '.$listener_binding.'
  secure                  '.(stripos($listener.'p', ":{$port_443}p") === FALSE ? '0' : '1');
	foreach($all_domains as $domain) {
		$lsws_config_file .= '
  map                     '.$domain.' '.$domain;
	}
	$lsws_config_file .= '
}
';
}

$lsws_config_file .= '
vhTemplate centralConfigLog {
  templateFile            $SERVER_ROOT/conf/templates/ccl.conf
  listeners               Default
}

vhTemplate PHP_SuEXEC {
  templateFile            $SERVER_ROOT/conf/templates/phpsuexec.conf
  listeners               Default
}

vhTemplate EasyRailsWithSuEXEC {
  templateFile            $SERVER_ROOT/conf/templates/rails.conf
  listeners               Default
}
';

@file_put_contents('/usr/local/lsws/conf/httpd_config.conf', $lsws_config_file);
@system('/bin/chown lsadm:lsadm /usr/local/lsws/conf/httpd_config.conf');
@system('/etc/init.d/lsws restart');
