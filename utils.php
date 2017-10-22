<?php
include('globals.php');

include('jwt' . DIRECTORY_SEPARATOR . 'JWT.php');

function maintenance(){
	$t = time() - 3600;
	$sql = "DELETE FROM email WHERE requested < $t OR sent < $t";
	do_sql($sql);	
}

function sanitize_object(&$obj){
	dLog("sanitize_object() obj:", $obj);
	foreach($obj as $k => &$val)
		if(is_scalar($val))
			$val = addslashes($val);
		else 
			sanitize_object($val);
			
	return $obj;
}


function init(){
	init_mysql();
}

function install(){ // run this once on a new machine
	create_map_id_autoincrement();	
}

function init_mysql(){
	global $mysql_conn;
	
	$mysql_conn = new mysqli(MYSQL_SERVER, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DB);
	if ($mysql_conn->connect_error) 
		die(dLog('Connection failed: ' . $mysql_conn->connect_error));
	dLog('Connected successfully to MySql server: ' . MYSQL_SERVER);
}

function get_db_rows($sql){
	global $mysql_conn;
	$result = do_sql($sql);
	if(!$result){
		dLog("Error in get_db_rows() for query: $sql");
		return NULL;
	}
	$rows = array();
    while($obj = $result->fetch_object()) 
		array_push($rows, $obj);
	return $rows;
}

function do_sql($sql){
	global $mysql_conn;
	dLog("do_sql() sql=$sql");
	$result = $mysql_conn->query($sql);
	if(!$result)
		dLog( 'do_sql() Query: $sql \n Error message: ' . $mysql_conn->error);
	return $result;	
}

function do_sql_multi($sql){
	global $mysql_conn;
	dLog("do_sql() sql=$sql");
	$result = $mysql_conn->multi_query($sql);
	if(!$result)
		dLog( 'do_sql_multi() Error message: ' . $mysql_conn->error);
	return $result;	
}


function get_user_by_email_hash_var($var_name, $default){
	$row = get_db_rows("SELECT value FROM vars WHERE name='$var_name'");
	if(count($row)!=1)
		return $default;
	return $row['value'];
}

function dLog($msg, $arr = NULL){
	$msg = $arr != NULL ? $msg . var_export($arr, TRUE) : $msg;	
	file_put_contents(DLOG_FILE, $msg . "\n", FILE_APPEND);
	return $msg;
}

function end_all(){
	global $mysql_conn;
	$mysql_conn->close(); 
}

function rnd_str($min_len = 12, $max_len = 24){
	$l = rand($min_len, $max_len);
	$s = "";
	for($i = 0; $i < $l; $i++)
		$s .= chr(rand(97, 122));
	return $s;	
}

function gen_files_table_test_data(){
	$file_urls = array();
	$n_files = 4;
	$n_lang = 3;
	for($j = 0; $j < $n_lang; $j++){
		$lang = array();
		for($i = 0; $i < $n_files; $i++)
			array_push($lang, rnd_str());
		array_push($file_urls, $lang);
	}

	for($id = 1; $id < 100; $id++){
		for($lang = 0; $lang < $n_lang; $lang++)
			do_sql("INSERT INTO files (`category_id`, `file_url`, `language`) VALUES($id, \"" . $file_urls[$lang][$id % $n_files] . "\", $lang)");
	}
}

$id_types = array('stringuid-fileid', 'category-string', 'category-name');
$languages = array('en');

function map_id($id1, $id2, $type){
	
}

function map_id_autoincrement($id2, $type){
	$sql = "CALL map_id_autoincrement($id2, $type);";
	return get_db_rows($sql)[0]->id;
}

function add_new_category($cat, $lang = 0){
	global $id_types;
	$r = add_new_string($cat, $lang);
	$cat_id = map_id_autoincrement($r->id, $id_types['category-name']);
	return $cat_id;
}

function get_cat_ids($lang = 0){
	global $id_types;
	$sql = "SELECT id_map.id1 AS id, strings.string AS label JOIN strings ON id_map.id2=strings.string_id WHERE strings.language=$lang AND id_map.type=" . $id_types['category-name'];
	$rows = get_db_rows($sql);
	$map = array();
	foreach($rows as $row)
		$map[$row->id] = $row->label;
	return $map;
}

function add_new_question($str, $cat, $lang = 'en'){
	$r = add_new_string($str);
	assign_question_to_category($r->id, $cat);
}

function add_new_string($str, $lang){
	$hash = crc32($str);
	$sql = "SET @id=IFNULL((SELECT MAX(string_id) FROM strings), 0) + 1; INSERT INTO strings (string_id, language, string, string_hash) VALUES(@id, $lang, '$str', $hash); SELECT @id AS id, LAST_INSERT_ID() AS uid;";
	$res = do_sql($sql);
	var_dump($res);
	return $res->fetch_object();	
}

function assign_question_to_category($string_id, $cat_id){
	$sql = "INSERT INTO id_map (id1, id2, type) VALUES($string_id, $cat_id, " . $id_types['category-string'] . ")";
	$res = do_sql($sql);
}


function create_map_id_autoincrement(){
	create_stored_procedure("CREATE PROCEDURE map_id_autoincrement(IN id2 SMALLINT(5), IN type TINYINT(3)) BEGIN
	SET @id=IFNULL((SELECT MAX(id1) FROM id_map WHERE type=type), 0)+1;
	INSERT INTO id_map (`id1`, `id2`, `type`) VALUES(@id, id2, type);
	SELECT @id AS id;
	END;");
}

function create_stored_procedure($proc){
	global $mysql_conn;
	if(!preg_match('/PROCEDURE\s+([^(\s]+)/', $proc, $matches)){
		dLog("Could not find procedure name in create_stored_procedure(). Text: $proc");
		return false;
	}
	$proc_name = $matches[1];
	
	if (!$mysql_conn->query("DROP PROCEDURE IF EXISTS $proc_name") ||
    !$mysql_conn->query($proc)) {
		dLog("Stored procedure creation failed: (" . $mysql_conn->errno . ") " . $mysql_conn->error);
		return false;
	}
	
	dLog("Procedure $proc_name successfully created");
	return true;
}

function decode_token($jwt){
	global $rsa_pub_key;
	try {
		$tkn = JWT::decode($jwt, $rsa_pub_key, array('RS256'));
	}
	catch(Exception $e){
		dLog('decode_token() Invalid token: ' . "$jwt\n Error message: " . $e->getMessage());
		return FALSE;
	}
	dLog("decode_token() tkn:", $tkn);
	return sanitize_object($tkn);
}

function encode_token($tkn){
	global $rsa_priv_key;
	return JWT::encode($tkn, $rsa_priv_key, 'RS256');	
}

function implode_sql($arr){
	$s = "";
	foreach($arr as &$v)
		if(!is_int($v))
			$v = "\"$v\"";
	return implode(',', $arr);
}

?>