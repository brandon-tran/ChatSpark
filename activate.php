<?php
include('globals.php');
include('utils.php');

$tkn = decode_token($_SERVER['QUERY_STRING']);
if(!$tkn){
	echo "Error: Incorrect activation url. Please restart your account creation.";
	return;
}

$user_id = intval($tkn['user_id']);
$sql = "UPDATE users SET verified=1 WHERE user_id=$user_id";
if(do_sql($sql))
	echo "Your account is now active!";
else
	echo "Processing error, please try again later.";

?>