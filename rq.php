<?php
// include('JWT.php');
include('globals.php');
include('utils.php');
use \jwt
include('JWT.php');
include('BeforeValidException.php');
include('ExpiredException.php');
include('SignatureInvalidException.php');


// TODO verify token here


$rq_list = json_decode(file_get_contents("php://input"), false);

$response = array();
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

function write_response(){
	global $response, $headers;
	foreach($headers as $k => $field)
		header("$k:$field");
	echo json_encode($response);
}

function create_response($rqid, $data = array()){
	global $response;
	if(array_key_exists($rqid, $response))
		die(dLog("Error: response already written for rqid=$rqid"));
	$response[$rqid] = $data;
}

function chk_fields_present($field_names, $arr){
	$keys = array_keys($arr);
	$res = array();
	$missing = array_diff($field_names, $keys);
	if(count($missing) > 0)
		$res['missing'] = $missing;
	$excess = array_diff($keys, $field_names);
	if(count($excess) > 0)
		$res['excess'] = $excess;
	return count($res) > 0 ? $res : TRUE;
}

function create_new_account($rq, &$resp){
	global $mysql_conn;
	$valid = TRUE;
	$resp['fields'] = array();
	static $req_fields = array('email', 'email-hash', 'gender', 'day', 'month', 'year');
	$res = chk_fields_present($req_fields, $rq->data);
	if($res !== TRUE){
		$valid = FALSE;
		$resp['fields'] = $res;
	}
	
	if(!array_key_exists('invalid', $res))
		$resp['fields']['invalid'] = array();
	
	if(!filter_var($rq->data['email'], FILTER_VALIDATE_EMAIL)){
		$valid = FALSE;
		array_push($resp['fields']['invalid'], 'email');		
	}
	if(!in_array($res['gender'], array('male', 'female', 'other'))){
		$valid = FALSE;
		array_push($resp['fields']['invalid'], 'gender');		
	}
	if($valid)
		unset($resp['fields']['invalid']);
	else {
		$resp['status'] = 'field_error';
		return FALSE;
	}
	
	$wr_fields = $rq->data;
	unset $wr_fields['email'];
	$wr_fields['email_hash_hash'] = hash('CRC32', $rq_fields['email_hash']);
	
	$sql = "INSERT INTO users (" . implode(',', array_keys($wr_fields)) . ") VALUES("  . implode(',', array_values($wr_fields)) . ");";
	
	if(!do_sql($sql))
		switch(mysqli_errno($mysql_conn)){
			case ER_DUP_ENTRY_WITH_KEY_NAME:
				dLog("User account already created");
				$resp['status'] = 'existing_user';
				return FALSE;
			break;
			default:
				dLog("mysql Error");
				$resp['status'] = 'error';
				return FALSE;
		}
	return send_confirmation_email($rq->data['email'], $mysql_conn->insert_id);
}


function send_outgoing_emails(){
	static $subject = "Account activation for your new chatSpark account";
	$message = file_get_contents(ACTIVATION_EMAIL_FILE);
	
	mail( $rq->data['email'],   , string $message	
	
	
}

function send_confirmation_email($email, $user_id){
	$ip = $_SERVER['REMOTE_ADDR'];
	$sql = "SELECT COUNT FROM email WHERE user_id="
	$sql = "INSERT INTO email (email, user_id, ip) VALUES($email, $user_id, " .  . " )";
	
	$tkn = array(
		'user_id' => $fields['email_hash'],
		
		
	);
	$message = str_replace('@activation_link@', )
	
	
	Thank you for signing up to chatSpark!\To activate your account please click on the link below.
	
	
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
				return NULL;
			}
	}
	if($rqt === NULL)
		return FALSE;
	$callback($rq, &$resp);
	return TRUE;
	
}

if(!isset($_SERVER[PHP_AUTH_DIGEST]) || !decode_token($_SERVER[PHP_AUTH_DIGEST])){
	$rqs = find_rqs_by_type($rq_list, 'login');
	$l = count($rqs);
	if($l==0){
		$rqs = find_rqs_by_type($rq_list, 'new_account');
		if(count($rqs)==1){
			create_response($rqs[0]->rqid, array(
				'result' => create_new_account($rqs[0], $fields_resp),
				'fields' => $fields_resp,				
			)); // Not verified until they answer their email confirmation
		}
		else
			invalid_status_response('login_needed');
	}
	elseif($l == 1){
		$k = key($rqs);
		if(!login_user($rqs[$k]->user, $rqs[$k]->password, $status))
			invalid_status_response($status);
		else
			$verified = TRUE;
		unset($rq_list[$k]);
	}
	else {
		dLog("Multiple login requests in same batch, headers:", getallheaders());
		dLog("Multiple login requests in same batch, params:", $rq_list);
	}
}
else
	$verified = TRUE;

if(!$verified){
	write_response();
	end_all();
	exit(1);
}

$headers['Authorization'] = "Bearer " . JWT::encode($token, $rsa_priv_key, 'RS256'); // todo this should be further down


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

write_response(); // duplicated functionality from above, but it's much safer to have clean code flow for authentication stuff
end_all();
exit(0);

function get_file_details($lang = 'en'){
	$rows = get_db_rows("SELECT DISTINCT(file) f FROM files WHERE lang='$lang' JOIN ");
	$files = array();
	foreach($rows as $row)
		array_push($files, $row['file']);
	return $files;
}

function login_user($email_hash, $password_hash, &$status){
	global $token, $headers, $rsa_priv_key;
	$sql = "SELECT * FROM users WHERE email_hash=\"$email_hash\"";
	$rows = get_db_rows($sql);
	if(count($rows)==0){
		$status = 'user_not_found';
		return false;		
	}
	if($rows[0]->password_hash != $password_hash){
		$status = 'incorrect_password';
		return false;
	}
	
	
	$t = time();
	$token = array(
		'user_id' => $rows[0]->user_id,
		'created' => $t,
		'issued_by' => $_SERVER[SERVER_ADDR],
		'check_after' => $t + STEP_TIME_BEFORE_PASSWORD_UPDATE_CHECK, // the time after which it should be checked to see if there has been a password update
	);
	return true;
}

function decode_token(){
	global $token;
	$token = JWT::decode($jwt, $publicKey, array('RS256'));
	return $verified = !!$token;
}



?>
