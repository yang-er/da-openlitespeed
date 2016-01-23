#!/usr/local/bin/php -n
<?php
$all = @file_get_contents("/usr/local/directadmin/data/plugin_data/admin.json");
$data = @json_decode($all);
if(!$data) die('He He');
foreach($data as $domain => $ext) {
	$write = <<<EOF
docRoot                   \$VH_ROOT/public_html
adminEmails               {$ext[ServerAdmin]}
enableGzip                1
enableIpGeo               0

errorlog /var/log/httpd/domains/|DOMAIN|.error.log {
  useServer               0
  logLevel                ERROR
  rollingSize             256K
}

accesslog /var/log/httpd/domains/|DOMAIN|.access.log {
  useServer               0
  logHeaders              7
  rollingSize             1M
  keepDays                30
  compressArchive         1
}

index  {
  useServer               1
  autoIndex               0
}
EOF;
	if($ext['error_400'])
		$write .= <<<EOF
errorpage 400 {
  url                     {$ext[error_400]}
}
EOF;
	if($ext['error_401'])
		$write .= <<<EOF
errorpage 401 {
  url                     {$ext[error_401]}
}
EOF;
	if($ext['error_403'])
		$write .= <<<EOF
errorpage 403 {
  url                     {$ext[error_403]}
}
EOF;
	if($ext['error_404'])
		$write .= <<<EOF
errorpage 404 {
  url                     {$ext[error_404]}
}
EOF;
	if($ext['error_500'])
		$write .= <<<EOF
errorpage 500 {
  url                     {$ext[error_500]}
}
EOF;
	$write .= <<<EOF
accessControl  {
  allow                   *
}
EOF;
/*context /|URL| {
  type                    NULL
  location                /|LOCATION|
  allowBrowse             1
  note                    |NOTES|
  enableExpires           1
  expiresDefault          A233
  expiresByType           mime/type=A266
  extraHeaders            |HEADER|: |HEADER|
  addMIMEType             CUSTOM_MIME/a2 ext, DDD/a2 etc
  forceType               force/mime
  defaultType             application/octet-stream
  indexFiles              |INDEX_FILES|
  autoIndex               1
  authName                |AUTH_NAME|
  required                |REQ|

  accessControl  {
    allow                 |SUBNET|
    deny                  |SUBNET|
  }

  rewrite  {
    enable                1
    inherit               0
    base                  /|REWRITE_BASE|
    rules                 <<<END_rules
|REWRITE_RULES|
|REWRITE_RULES|
|REWRITE_RULES|
    END_rules

  }
  addDefaultCharset       on
  defaultCharsetCustomized |DEFAULT_CHARSET|
  enableIpGeo             1
}
*/
	if($ext['rewrite_engine_on'])
		$write .= <<<EOF
rewrite  {
  enable                  1
  rules                   <<<END_rules
{$ext[rewrite_config]}
  END_rules
}

EOF;
	@file_put_contents('/usr/local/lsws/conf/vhosts/'.$ext['user'].'_'.$ext['domain'].'.conf', $write);
	@exec('/etc/init.d/lsws restart');
	@print($write);
}
