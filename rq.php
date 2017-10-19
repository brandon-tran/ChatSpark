<?php
include('utils.php');



$rq_list = json_decode(file_get_contents("php://input"), false);

$responses = array();
$headers = array();
$verified = false;
$token = array();


if(json_last_error() != JSON_ERROR_NONE){
	die(dLog("Invalid call, dumping headers", getallheaders()));
}

init();

function find_rqs_by_type($rq_list, $type){
	$rqs = array();
	foreach($rq_list as $k => $rq)
		if(isset($rq->type) && ($rq->type==$type))
			$rqs[$k] = $rq;
	return $rqs;
}

function invalid_status_response($resp, $rq){
	global $response, $rsa_pub_key;
	if(!array_key_exists(0, $response))
		$response[0] = array();
	array_push($response[0], 
		array(
			'type' => 'status',
			'data' => $status,
		)	
	);
	return true;	
}

function write_responses(){
	global $responses, $headers;
	foreach($headers as $k => $field)
		header("$k:$field");
	echo json_encode(array_values($responses));
}

function create_response($resp){
	global $responses;
	if(array_key_exists($resp['rqid'], $responses))
		die(dLog("Error: response already written for rqid=$rqid"));
	$responses[$resp['rqid']] = $resp;
}

function chk_fields_present($field_names, $arr, &$res){
	if(gettype($arr)=='object')
		$arr = get_object_vars($arr);
	$keys = array_keys($arr);
	$res = array();
	$missing = array_diff($field_names, $keys);
	if(count($missing) > 0)
		$res['missing'] = $missing;
	$excess = array_diff($keys, $field_names);
	if(count($excess) > 0)
		$res['excess'] = $excess;
}

function chk_date($d, $m, $y, &$inv){
	$age = date('Y') - $y;
	
	if(($age < MIN_AGE) || ($age > MAX_AGE))
		array_push($inv, 'year');
	
	if(($m < 1) || ($m > 12))
		array_push($inv, 'month');
	
	if(!checkdate($m, $d, $y))
		array_push($inv, 'day');

}


function create_new_account($rq, &$resp){
	global $mysql_conn, $genders;
	$valid = TRUE;
	$resp['fields'] = array();
	static $req_fields = array('email', 'email_hash', 'password_hash', 'gender', 'day', 'month', 'year');
	chk_fields_present($req_fields, $rq->data, $resp['fields']);

	if(!array_key_exists('invalid', $resp['fields']))
		$resp['fields']['invalid'] = array();
	
	if(!filter_var($rq->data->email, FILTER_VALIDATE_EMAIL))
		array_push($resp['fields']['invalid'], 'email');	
	
	$g = array_search($rq->data->gender, $genders);
	if($g===FALSE)
		array_push($resp['fields']['invalid'], 'gender');
	else
		$rq->data->gender = $g;
	
	chk_date($rq->data->day, $rq->data->month + 1, $rq->data->year, $resp['fields']['invalid']);
	
	foreach($resp['fields'] as $k => $v)
		if(count($v) > 0)
			$valid = FALSE;
		else
			unset($resp['fields'][$k]);

	if(!$valid){
		$resp['status'] = 'field_error';
		return FALSE;
	}

	$wr_fields = get_object_vars($rq->data);
	
	foreach(array('email', 'year', 'month', 'day') as $k)
		unset($wr_fields[$k]);
		
	$wr_fields['email_hash_hash'] = CRC32($rq->data->email_hash);
	
	$wr_fields['birthday'] = mktime(0,0,0, $rq->data->month + 1, $rq->data->day, $rq->data->year);
	dLog("wr_fields:", $wr_fields);
	dLog("wr_fields av:", array_values($wr_fields));
	$sql = "INSERT INTO users(" . implode(',', array_keys($wr_fields)) . ") VALUES(" . implode_sql(array_values($wr_fields)) . ");";

	if(!do_sql($sql)){
		$err_num = mysqli_errno($mysql_conn);
		dLog("MySQL error in create_new_account(). Number: $err_num");
		switch($err_num){
			case ER_DUP_ENTRY:
				dLog("User account already created");
				$resp['status'] = 'existing_user';
				return FALSE;
			break;
			default:
				dLog("mysql Error");
				$resp['status'] = 'error';
				return FALSE;
		}
	}
	return send_confirmation_email($rq->data->email, $mysql_conn->insert_id, $resp);
}


function send_confirmation_email($email, $user_id, &$resp){	
	dLog("send_confirmation_email() cwd:" . getcwd());
	$ip = $_SERVER['REMOTE_ADDR'];
	$t = time() - 3600;
	$sql = "SELECT COUNT(*) AS count FROM email WHERE (user_id=$user_id OR ip=\"$ip\") AND ((sent IS NOT NULL AND sent > $t) OR requested > $t);"; // MySql already makes sure that only one pending email exists per user_id
	$rows = get_db_rows($sql);
	if(!$rows || !count($rows))
		die(dLog("Error in send_confirmation_email(), bailing"));
	
	if($rows[0]->count > MAX_EMAILS_PER_HOUR){
		$resp['status'] = 'email_limit_exceeded';
		return false;		
	}

	$sql = "INSERT INTO email (email, user_id, ip) VALUES($email, $user_id, " . $_SERVER['SERVER_ADDR'] . " )";
	
	$message = file_get_contents(ACTIVATION_EMAIL_FILE);
	if(!$message){
		dLog("Error retrieving file in send_confirmation_email()");
		return NULL;		
	}
	
	
	$tkn = array(
		'user_id' => $user_id,		
		'type' => 'activate_account',
	);
	
	$message = str_replace('@activation_link@', ACTIVATION_ENDPOINT . '?' . encode_token($tkn), $message);
	dLog("send_confirmation_email() message:\n $message");
	if(mail( $email, "Account activation for your new chatSpark account", $message )){
		$resp['status'] = 'confirmation_email_sent';
		return TRUE;
	}	
	$resp['status'] = 'email_send_error';
	return NULL;
}

function find_rq_by_type(&$rqs, $type, $callback, &$resp) {
	$rqt = NULL;
	foreach($rqs as $k => &$rq){
		if(isset($rq->type) && ($rq->type==$type))
			if($rqt===NULL)
				$rqt = &$rq; // keep looping in case there's more than one, throw an error if that occurs
			else {
				$resp['rqid'] = $rq->rqid;
				$resp['status'] = 'multiple_items';
				$resp['success'] = FALSE;
				dLog("Error: Multiple matching requests found");
				return NULL;
			}
	}
	if($rqt === NULL)
		return FALSE;
	
	$resp['rqid'] = $rqt->rqid;
	
	$callback($rq, $resp);
	return TRUE;
}

if(!isset($_SERVER['PHP_AUTH_DIGEST']) ||
	!($token = decode_token($_SERVER['PHP_AUTH_DIGEST']) )){
	$resp = array();
	if(find_rq_by_type($rq_list, 'login', 'login_user', $resp)){
		if($resp['status'] == 'logged_in')
			$verified = TRUE;
		create_response($resp);
	}
	elseif(find_rq_by_type($rq_list, 'new_account', 'create_new_account', $resp))
		create_response($resp);
}
else
	$verified = TRUE;

if(!$verified){
	write_responses();
	end_all();
	exit(1);
}


foreach($rq_list as $rq){
	switch($rq->type){
		case 'login':
			die(dLog("This should never trigger"));
			break;
		case 'files':
			add_to_response(get_file_details(), $rq->rqid);
			break;
		
	}
}

function add_to_response($arr, $rqid){
	global $response;
	array_push($response, 
		array(
			'rqid' => $rqid,
			'data' => $arr		
		)
	);
}


$headers['Authorization'] = "Bearer " . encode_token($token); // todo this should be further down
write_responses(); // duplicated functionality from above, but it's much safer to have clean code flow for authentication stuff

end_all();
exit(0);

function get_file_details($lang = 'en'){
	$rows = get_db_rows("SELECT DISTINCT(file) f FROM files WHERE lang='$lang' JOIN ");
	$files = array();
	foreach($rows as $row)
		array_push($files, $row['file']);
	return $files;
}

function login_user(&$rq, &$resp){ //$email_hash, $password_hash, &$status){
	global $token;
	$sql = "SELECT * FROM users WHERE email_hash=\"" . $rq->data->email_hash . "\"";
	$rows = get_db_rows($sql);
	if(count($rows)==0){
		$resp['status'] = 'user_not_found';
		return false;	
	}
	if($rows[0]->password_hash != $rq->data->password_hash){
		$resp['status'] = 'incorrect_password';
		return false;
	}
	
	$resp['status'] = 'logged_in';
	
	$t = time();
	$token = array(
		'user_id' => $rows[0]->user_id,
		'created' => $t,
		'issued_by' => $_SERVER[SERVER_ADDR],
		'check_after' => $t + STEP_TIME_BEFORE_PASSWORD_UPDATE_CHECK, // the time after which it should be checked to see if there has been a password update
	);
	return true;
}

?>
