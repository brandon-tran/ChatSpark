<?php
// include('JWT.php');
include('globals.php');
include('utils.php');

// TODO verify token here


$rq_list = json_decode(file_get_contents("php://input"), false);

$response = array();
$verified = false;

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

function status_response($status){
	global $response;
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
	global $response;
	echo json_encode($response);	
}

function create_response($rqid, $data = array()){
	global $response;
	if(array_key_exists($rqid, $response))
		die(dLog("Error: response already written for rqid=$rqid"));
	$response[$rqid] = $data;
}

if(!isset($_SERVER['Authorization']) || !verify_token($_SERVER['Authorization'])){
	$rqs = find_rqs_by_type($rq_list, 'login');
	$l = count($rqs);
	if($l==0){
		$rqs = find_rqs_by_type($rq_list, 'new_account');
		if(count($rqs)==1){
			create_response($rqs[0]->rqid, array(
				'result' => create_new_account($rqs[0], $fields_msgs),
				'fields' => $fields_msgs,				
			));
		}
		else
			status_response('login_needed');
	}
	elseif($l == 1){
		$k = key($rqs);
		if(!verify_user($rqs[$k]->user, $rqs[$k]->password))
			status_response('invalid_login');
		$verified = TRUE;
		unset($rq_list[$k]);
	}
	else {
		dLog("Multiple login requests in same batch, headers:", getallheaders());
		dLog("Multiple login requests in same batch, params:", $rq_list);
	}
}

if(!$verified){
	write_response();
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



?>
