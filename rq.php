<?php
include('utils.php');
include('account.php');

$responses = array();
$response_headers = array();
$rq_headers = getallheaders();
$verified = false;
$token = array();

$rq_list = json_decode(file_get_contents("php://input"), false);
sanitize_object($rq_list);

dLog('headers:', getallheaders());
dLog('rq_list:', $rq_list);


if(json_last_error() != JSON_ERROR_NONE){
	die(dLog("Invalid call, dumping headers", getallheaders()));
}

init();


if(find_rq_by_type($rq_list, 'web_update_password', 'web_update_password', $resp, $rq)){
	add_to_response($resp);
	write_responses();
	end_all();
	return;
}

dLog('server_vars', $_SERVER);
dLog('strlen auth:' . strlen($rq_headers['Authorization']));

$rq_auth = substr($rq_headers['Authorization'], 7);
dLog("rq_auth: $rq_auth");

if(find_rq_by_type($rq_list, 'login', 'login_user', $resp, $rq)){
	dLog('main() 1');
	add_to_response($resp);
	if($resp['status'] == 'logged_in'){
		dLog("Successfully verified user. resp:", $resp);

		$verified = TRUE;
	}
	else {
		$verified = FALSE;
		$token = NULL;
		exit_rq(0);
	}
}
elseif(find_rq_by_type($rq_list, 'new_account', 'create_new_account', $resp, $rq) ||
	find_rq_by_type($rq_list, 'reset_password', 'reset_password', $resp, $rq))
{
	add_to_response($resp);
	exit_rq(0);		
}

if($verified)   // keep this flow very clean and simple 
	;
elseif(!isset($rq_auth))
	exit_rq(5);
elseif(!($token = decode_token($rq_auth)))
	exit_rq(4);
elseif($token->check_after < time()){
	if(!verify_web_password_hash($token->user_id, $token->password_hash_hash)){
		add_to_response(
			array(
				'status' => 'token_expired',
				'message' => 'Token has expired, please log in again',
				'success' => FALSE,
			)
		);
		exit_rq(7);
	}
	$token = mk_user_token($token->user_id);
}

$verified = TRUE;

dLog('main() 3');


if(find_rq_by_type($rq_list, 'app_update_password', 'app_update_password', $resp, $rq)){
	add_to_response($resp);
	if(!$resp['success']){		
		$token = NULL;
		exit_rq(3);
	}
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

function add_to_response($resp){
	global $responses;
	array_push($responses, $resp);
}


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
	global $responses, $response_headers, $token;
	if($token)
		$response_headers['Authorization'] = "Bearer " . encode_token($token); // todo this should be further down
	foreach($response_headers as $k => $field)
		header("$k:$field");
	dLog('responses:', $responses);
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

function find_rq_by_type(&$rqs, $type, $callback, &$resp, &$req) {
	$k0 = NULL;
	//$resp = array();
	foreach($rqs as $k => &$rq){
		dLog("rq->type: " . $rq->type);
		if(isset($rq->type) && ($rq->type==$type)){
			dLog("type match");
			if($k0===NULL)
				$k0 = $k; // keep looping in case there's more than one, throw an error if that occurs
			else {
				dLog("Error: Multiple matching requests found");
				$req = $rq;
				$resp['rqid'] = $rq->rqid;
				$resp['status'] = 'multiple_items';
				return $resp['success'] = FALSE;
			}
		}
	}

	if($k0 === NULL)
		return $resp['success'] = FALSE;
	dLog('find_rq_by_type() rqs:', $rqs);
	$req = $rq;
	$resp['rqid'] = $rqs[$k0]->rqid;
	$resp['type'] = $rqs[$k0]->type;
	$callback($rqs[$k0], $resp);
	unset($rqs[$k0]);
	return TRUE;
}

function exit_rq($exit_code){
	write_responses();
	end_all();
	exit($exit_code);
}

?>
