<?php
$debug = TRUE;

if($debug){
	define('FILE_DIR', '.'); // TODO change this
	define('MYSQL_SERVER', 'localhost');
	define('DLOG_FILE', "F:\\Hosting\\Logs\\sparktalk.log");
	define('ACTIVATION_EMAIL_ADDRESS', 'rapid1@amnet.net.au');
	file_put_contents(DLOG_FILE, "");
}
else {
	define('FILE_DIR', '.'); // TODO change this
	define('DLOG_FILE', "sparktalk.log"); //todo put this somewhere safer
	define('MYSQL_SERVER', 'localhost');
	define('ACTIVATION_EMAIL_ADDRESS', 'activation@chatspark.xyz');
}

define('MYSQL_USERNAME', 'chatspark_user');
define('MYSQL_PASSWORD', 'NMdWMg43ysLfRvYk');
define('MYSQL_DB', 'chatspark');


$mysql_conn = NULL;

$dropbox_key = "ptx5vdfpqpu6ep8";
$dropbox_secret = "evrzwbig18c0r3t";
$dropbox_token = "3Y9RfWQXvtAAAAAAAAAACNjjHLjVjP4jpaBZ67mV2RwXRuArIBtWT_xDE07fzSr8";
$genders = array('male', 'female', 'other');

define('SERVER_ADDRESS', 'http://127.0.0.1/sparktalk');
define('ACTION_ENDPOINT', SERVER_ADDRESS . '/action.php');
define('RQ_ENDPOINT', SERVER_ADDRESS . '/rq.php');

define('STEP_TIME_BEFORE_PASSWORD_UPDATE_CHECK', 3600);
define('ER_DUP_ENTRY', 1062); // from mysqld_error.h

define('ACTIVATION_EMAIL_TEMPLATE', FILE_DIR . DIRECTORY_SEPARATOR . 'activation_email.html');
define('PASSWORD_RESET_TEMPLATE', FILE_DIR . DIRECTORY_SEPARATOR . 'password_reset_email.html');
define('PASSWORD_RESET_HTML', FILE_DIR . DIRECTORY_SEPARATOR . 'password_reset.html');

define('MAX_EMAILS_PER_HOUR', 5);
define('MAX_LOGIN_ATTEMPTS_USERID_PER_DAY', 10);
define('MAX_LOGIN_ATTEMPTS_IP_PER_HOUR', 5);
define('MIN_AGE', 10);
define('MAX_AGE', 100);

define('PASSWORD_RESET_EXPIRY', 3600);


$commonSalt = [52, 114, 250, 27, 157, 4, 78, 96, 51, 65, 236, 143, 107, 225, 131, 247, 20, 136, 102, 37, 253, 125];
$server_salt = [228,188,75,5,67,238,55,104,163,43,252,174,176,219,196,183,58,226,123,224,151,200,45,23,147,245,127,126,68,12,122,242,181,230,8,191,93,253,99,113,170,42,91,66,233,243,91,255,99,20];

$rsa_priv_key = "-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQCCjDjE7rxDQzxjIGVzxtG8GJgBXjb4SgvWfTxG/0abhdTkabTl
HGtubOz7jd2zePoG43zvCX+OC9hjtspczfOU8tR3P7ciH9fsZTlXBx4A9jP+HfDQ
c9UDCx+teV+Qsi3HsAg+TXo/7hsHy43Iqva/dYbxfW0KF8R4cXxfMC0LtwIDAQAB
AoGAEP5VyXTWNt9CthiafDauSJDfAJaWCz4ASnxk400JkOcb7lvAO262oVo2gwxV
hq5BxbHJKoiO/RuXoGtD7k603UdPAJ7CmVkTVODMB5GENcnFCUA5hPxJTD5cyofM
55q8HHMKbTkPR4Esz3Czq2mGWlBOGM7kLOzabxt0fZ2euMECQQDcNxNEGhgbBl5k
sy0wvznaW1C+yJDx2jSA2+T7D8W9aleWetGz4opLxM31NPsOiBAB3ZqfFmVWxxOB
WGgSF4kXAkEAl8L+M8CQy6Hu3VtzZq+zZNyT4wzYIqKYxozbOxSLYSlex9BVqLtP
8+PBlDxKvUxkDSvPxZy33kRdaMkZLGr2YQI/eiYSibPvqw3dTf4VEvT/Ih+Eqk6W
F5DxjohqethE1swlyVJW/3CpRV3k4B6DI4xVVLOXEKdbjsbeCuD+2Qo1AkEAh5dm
e2Kfe/CwdAHTN3nf9EvHreK58SgJC8ypyz1t0l+eGTSgc+L3alahjAnaVQs9kS8F
se91sBawxoB2B2OBwQJBAI4P2kNU7G4U/vxUdo28bZn4AGvTk9AtIKes357gzGm/
ZqPwf6DGglDJNZRhxg6jJcLajOqqK+KfZV8O2K+UmnI=
-----END RSA PRIVATE KEY-----";
$rsa_pub_key = "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCCjDjE7rxDQzxjIGVzxtG8GJgB
Xjb4SgvWfTxG/0abhdTkabTlHGtubOz7jd2zePoG43zvCX+OC9hjtspczfOU8tR3
P7ciH9fsZTlXBx4A9jP+HfDQc9UDCx+teV+Qsi3HsAg+TXo/7hsHy43Iqva/dYbx
fW0KF8R4cXxfMC0LtwIDAQAB
-----END PUBLIC KEY-----";
?>