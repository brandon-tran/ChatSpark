<?php
include('utils.php');
init_mysql();

$tkn = decode_token($_SERVER['QUERY_STRING']);
if(!$tkn){
	echo "Error: Incorrect activation url. Please restart your account creation.";
	return;
}

var_dump($tkn);
$user_id = intval($tkn->user_id);
switch($tkn->type){
	case 'activate_account':
		if(do_sql($sql))
			echo "Your account is now active!";
		else
			echo "Processing error, please try again later.";
		break;
		
	case 'reset_password':
		
		break;
	default:
		die(dLog("Error! Please retry!");	
}
$sql = "UPDATE users SET verified=1 WHERE user_id=$user_id";
echo "\nsql=$sql \n";



end_all();
exit(0);
?>