<?php
include('utils.php');
include('account.php');

$rq_list = sanitize_object(json_decode(file_get_contents("php://input"), false));


$responses = array();
$headers = array();
$verified = false;
$token = array();


if(json_last_error() != JSON_ERROR_NONE){
	die(dLog("Invalid call, dumping headers", getallheaders()));
}

init();


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


function find_rq_by_type(&$rqs, $type, $callback, &$resp) {
	$k0 = NULL;
	dLog("rqs:", $rqs);
	
	foreach($rqs as $k => &$rq){
		if(isset($rq->type) && ($rq->type==$type))
			if($k0===NULL)
				$k0 = $k; // keep looping in case there's more than one, throw an error if that occurs
			else {
				$resp['rqid'] = $rq->rqid;
				$resp['status'] = 'multiple_items';
				$resp['success'] = FALSE;
				dLog("Error: Multiple matching requests found");
				return NULL;
			}
	}
	if($k0 === NULL)
		return FALSE;
	
	$resp['rqid'] = $rqs->$k0->rqid;
	
	$callback($rqs->$k0, $resp);
	unset($rqs->$k0);
	return TRUE;
}


if(find_rq_by_type($rq_list, 'web_update_password', 'web_update_password', $resp)){
	add_to_response($resp);
	write_responses();
	end_all();
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
	elseif(find_rq_by_type($rq_list, 'reset_password', 'reset_password', $resp))
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

function add_to_response($resp){
	global $response;
	array_push($response, 
		array(
			'rqid' => $resp['rqid'],
			'data' => $resp	
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



?>
