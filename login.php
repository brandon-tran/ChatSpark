<?php

function verify_user($email_hash, $password_hash){
	global $jwt;
	$jwt = array(
		'user' => 0,
		'created' => '',
		'issued_by' => '',
		'last_checked' => '', // this is *not* the last time of use, it is when the db was checked to see if the user password had changed since this was issued
		'sig'
	);

	return true;
}


?>
