<?php

function get_user_by_email_hash($email_hash){
	$email_hash_hash = crc32($email_hash);
	$sql = "SELECT * FROM users WHERE email_hash_hash=\"" . $email_hash_hash . "\"";
	$rows = get_db_rows($sql);
	$c = count($rows);
	if($c == 0)
		return false;	
	
	elseif($c > 1){
		$sql = "SELECT * FROM users WHERE email_hash=\"" . $email_hash . "\"";
		$rows = get_db_rows($sql);
		if(count($rows)>1)
			die(dLog("db Error: email_hash duplicated"));
	}
	return $rows[0];
}

function record_login_details($success, $user_id = NULL){
	$success = $success ? 1 : 0;
	$sql = $user_id ? 
		"INSERT INTO logins (user_id, success, ip) VALUES($user_id, $success,\"" . $_SERVER['REMOTE_ADDR'] . '")' :
		"INSERT INTO logins (success, ip) VALUES($success,\"" . $_SERVER['REMOTE_ADDR'] . '")';
	if(!($res = do_sql($sql)))
		die(dLog("Could not record to database logins"));
	return !!$res;
}

function chk_login_attempts_per_ip(){
	$sql = 'SELECT COUNT(*) AS count FROM logins WHERE SUCCESS=0 AND ip="' . $_SERVER['REMOTE_ADDR']. '" AND (time + 86400) > ' . time() . ';';
	dLog('chk_login_attempts_per_ip() res:', get_db_rows($sql));
	dLog('chk_login_attempts_per_ip() tot:' . get_db_rows($sql)[0]->count);
	return get_db_rows($sql)[0]->count <= MAX_LOGIN_ATTEMPTS_IP_PER_HOUR;
}

function chk_login_attempts_per_user($user_id){
	$sql = "SELECT COUNT(*) AS count FROM logins WHERE SUCCESS=0 AND user_id=$user_id  AND (time + 3600) > " . time() . ';';
	return get_db_rows($sql)[0]->count <= MAX_LOGIN_ATTEMPTS_USERID_PER_DAY;	
}

function login_user(&$rq, &$resp){
	global $token;
	if(!chk_login_attempts_per_ip()){
		record_login_details(FALSE);
		$resp['status'] = 'number_attempts_per_ip_exceeded';
		return $resp['success'] = FALSE;
	}
	
	$row = get_user_by_email_hash($rq->data->email_hash);
	if(!$row){
		$resp['status'] = 'user_not_found';
		$resp['fields'] = array('email');
		record_login_details(FALSE);
		return $resp['success'] = FALSE;
	}
	
	if(!chk_login_attempts_per_user($row->user_id)){
		$resp['number_attempts_per_userid_exceeded'];
		return $resp['success'] = FALSE;
	}
	
	if(!password_verify(base64_decode($rq->data->password_hash), $row->password_hash)){
		$resp['status'] = 'incorrect_password';
		$resp['fields'] = array('password');
		record_login_details(FALSE);
		return $resp['success'] = FALSE;
	}
	
	$token = mk_user_token($row->user_id);
	$resp['status'] = 'logged_in';
	dLog("login_user() resp 4:", $resp);
	record_login_details(TRUE, $row->user_id);
	return $resp['success'] = TRUE;
}

function mk_user_token($user_id){
	$t = time();
	return array(
		'user_id' => $user_id,
		'created' => $t,
		'issued_by' => $_SERVER['SERVER_ADDR'],
		'check_after' => $t + STEP_TIME_BEFORE_PASSWORD_UPDATE_CHECK, // the time after which it should be checked to see if there has been a password update
		'password_hash_hash' => mk_web_hash($user_id),
	);
}

function mk_web_hash($user_id){
	$wh = mk_web_hash_password($user_id);
	if(!$wh){
		dLog("mk_web_hash() Error making hash");
		return FALSE;
	}
	return password_hash( $wh, PASSWORD_BCRYPT );
}

function mk_web_hash_password($user_id){
	global $server_salt;
	$sql = "SELECT password_hash FROM users WHERE user_id=$user_id";
	$rows = get_db_rows($sql);
	if(count($rows)!=1){
		dLog("verify_web_password_hash() Error: Returning false");
		return FALSE;
	}
	return hash("SHA256", $rows[0]->password_hash . pack('C*', ...$server_salt), TRUE);
}

function verify_web_password_hash($user_id, $hash){
	global $server_salt;
	$p = mk_web_hash_password($user_id);
	return password_verify($p, $hash);	
}

function web_update_password(&$rq, &$resp){
	$tkn = decode_token($rq->data->jwt);
	
	if(!$tkn){
		$resp['message'] = 'An error has occurred. Please restart the reset process';
		$resp['status'] = 'token_error';
		return $resp['success'] = FALSE;
	}
	if($tkn->expiry < time()){
		$resp['message'] = 'Link validity has expired. Please restart the reset process';
		$resp['status'] = 'link_expired';
		return $resp['success'] = FALSE;
	}

	if(!verify_web_password_hash($tkn->user_id, $tkn->password_hash)){
		$resp['message'] = 'Password has already been changed';
		$resp['status'] = 'already_reset';		
		return $resp['success'] = FALSE;
	}
	
	$resp['message'] = 'Password successfully changed';
	$resp['status'] = 'password_changed';
	
	return $resp['success'] = update_password($tkn->user_id, base64_decode($rq->data->password_hash));	
}

function update_password($user_id, $password){
	$sql = 'UPDATE users SET password_hash="' . password_hash($password, PASSWORD_BCRYPT) . '" WHERE user_id=' . $user_id;
	return do_sql($sql);
}

function parse_subject_from_message_template($msg){
	$i = strpos($msg, 'subject:"', 0) + 9;
	$j = strpos($msg, '"', $i);
	return substr($msg, $i, $j - $i + 1);	
}

function send_email($email, $user_id, &$resp, $template_name, $message_callback){	
	dLog("send_confirmation_email() cwd:" . getcwd());
	$ip = $_SERVER['REMOTE_ADDR'];
	$t = time() - 3600;
	$sql = "SELECT COUNT(*) AS count FROM email WHERE (user_id=$user_id OR ip=\"$ip\") AND ((sent IS NOT NULL AND sent > $t) OR requested > $t);"; // MySql already makes sure that only one pending email exists per user_id
	$rows = get_db_rows($sql);
	if(!$rows || !count($rows))
		die(dLog("Error in send_confirmation_email(), bailing"));
	
	if($rows[0]->count > MAX_EMAILS_PER_HOUR){
		$resp['status'] = 'email_limit_exceeded';
		return $resp['success'] = FALSE;	
	}

	$sql = "INSERT INTO email (email_address, user_id, ip) VALUES(\"$email\", $user_id, \"" . $_SERVER['SERVER_ADDR'] . "\" )";
	if(!do_sql($sql)){
		dLog("Error writing to table email.");
		return $resp['success'] = FALSE;
	}
	$message = file_get_contents($template_name);
	if(!$message){
		dLog("Error retrieving file in send_confirmation_email()");
		return $resp['success'] = FALSE;	
	}

	$message = $message_callback($message, $user_id);
	
	
	dLog("send_confirmation_email() message:\n $message");
	
	$email_headers = [ 'MIME-Version: 1.0', 'Content-type: text/html; charset=iso-8859-1', 'From: ChatSpark <' . ACTIVATION_EMAIL_ADDRESS . '>' ];
	$subject = parse_subject_from_message_template($message);
	if(mail( $email, $subject, $message, implode("\r\n", $email_headers))){
		$resp['status'] = 'confirmation_email_sent';
		return $resp['success'] = TRUE;
	}	
	$resp['status'] = 'email_send_error';
	return $resp['success'] = FALSE;
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
	
	$wr_fields['password_hash'] = password_hash(base64_decode($wr_fields['password_hash']), PASSWORD_BCRYPT);
	
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

function app_update_password(&$rq, &$resp){
	global $token;
	dLog('app_update_password() 1 rq:', $rq);
	$new_password = base64_decode($rq->data->password_new_hash);
	$old_password = base64_decode($rq->data->password_hash);
	if($old_password==$new_password){
		$resp['status'] = 'app_update_same_password';
		$resp['fields'] = array( 'password_new', 'password_new2' );
		return $resp['success'] = FALSE;
	}
	
	dLog('app_update_password() 2 rq:', $rq);
	
	$sql = 'SELECT password_hash FROM users WHERE user_id=' . $token->user_id;
	$rows = get_db_rows($sql);
	if(count($rows)!=1)
		die(dLog('Error: Could not find user with user_id=' . $token->user_id . ' in database users'));
	dLog('app_update_password() 3 rq:', $rq);
	$db_password_hash = $rows[0]->password_hash;
	
	if(!password_verify($old_password, $db_password_hash)){
		$resp['status'] = 'app_update_password_old_password_incorrect';
		$resp['fields'] = array('password');
		return $resp['success'] = FALSE;
	}
	
	dLog('app_update_password() 4 rq:', $rq);

	$b = update_password($token->user_id, base64_decode($rq->data->password_new_hash));
	$resp['status'] = 'app_update_password_updated';
	return $resp['success'] = TRUE;
}

function reset_password(&$rq, &$resp){
	$row = get_user_by_email_hash($rq->data->email_hash);
	if(!$row){
		$resp['status'] = 'reset_password_invalid_email';
		$resp['fields'] = array('email');
		$resp['success'] = FALSE;
		return FALSE;		
	}
	
	return send_email($rq->data->email, $row->user_id, $resp, PASSWORD_RESET_TEMPLATE, function($message, $user_id){
		$sql = "SELECT password_hash FROM users WHERE user_id=$user_id";
		$tkn = array(
			'user_id' => $user_id,		
			'type' => 'reset_password',
			'expiry' => time() + 3600,
			'password_hash' => mk_web_hash($user_id),
		);
		$action_link = ACTION_ENDPOINT . '?' . encode_token($tkn);
		dLog("action_link: $action_link");
		return str_replace('@reset_link@', $action_link, $message);
	});
}

function send_confirmation_email($email, $user_id, &$resp){
	return send_email($email, $user_id, $resp, ACTIVATION_EMAIL_TEMPLATE, function($message, $user_id){
		$tkn = array(
			'user_id' => $user_id,		
			'type' => 'activate_account',
		);
		$action_link = ACTION_ENDPOINT . '?' . encode_token($tkn);
		return str_replace('@activation_link@', $action_link, $message);
	});	
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

?>
