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
		$sql = "UPDATE users SET verified=1 WHERE user_id=$user_id";
		if(do_sql($sql))
			echo "Your account is now active!";
		else
			echo "Processing error, please try again later.";
		break;
		
	case 'reset_password':
		$html = file_get_contents(PASSWORD_RESET_HTML);
		if(!$html)
			die(dLog("An error has occurred in action.php. Could not load page"));
		$html = str_replace('@rq_endpoint@', RQ_ENDPOINT, $html);
		echo $html;
		break;
	case 'web_update_password':
		if()
		break;
	default:
		die(dLog("Error! Please retry!");	
}

echo "\nsql=$sql \n";



end_all();
exit(0);
?>