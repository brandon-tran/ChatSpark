<?php 
echo "234<br/>";

include('global.php');
//var_dump(share_dropbox_file('/live/soccer.txt'));
var_dump(get_dropbox_link('/live/soccer.txt'));
return;


$a = dropbox_upload("abcdefghijkl",
	array(
		"path" => "/live/soccer.txt",
		"mode" => "overwrite",
		"autorename" => false,
		"mute" => false,
	)
);

var_dump($a);
echo "\n\nDir listing:\n";

$a = post_json("https://api.dropboxapi.com/2/files/list_folder", 
	array(
		"path" => "",
		"recursive" => false,
		"include_media_info" => false,
		"include_deleted" => false,
		"include_has_explicit_shared_members" => false,
		"include_mounted_folders" => true,
	),
	array(
		'Authorization: Bearer ' . $dropbox_token,
	)
);

var_dump($a);

function dir_dropbox(){
	global $dropbox_token;
	
	return post_json("https://api.dropboxapi.com/2/files/list_folder",
		array(
			'path' => '/live',
			'recursive' => false,
			'include_media_info'=> false,
			'include_deleted'=> false,
			'include_has_explicit_shared_members'=> false,
			'include_mounted_folders'=> true
		),
		array('Authorization: Bearer ' . $dropbox_token)
	);
}

function get_dropbox_link($path = '/live'){
	global $dropbox_token;
	
	$l = post_json("https://api.dropboxapi.com/2/sharing/list_shared_links",
		array('path' => $path),
		array('Authorization: Bearer ' . $dropbox_token)
	);
	return str_replace('www.dropbox.com', 'dl.dropbox.com', $l->links[0]->url);
}

function share_dropbox_file($path){
	global $dropbox_token;
	$a = post_json(
		"https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings",
		array(
			"path" => "/live/soccer.txt",
			"settings" => array(
				"requested_visibility" => "public",
			)
		),
		array(
			'Authorization: Bearer ' . $dropbox_token,
		)
	);
	var_dump($a);
	return $a['url'];
}

function dropbox_upload($text, $params){
	global $dropbox_token;
	return post(
		"https://content.dropboxapi.com/2/files/upload",
		$text,
		array(
			'Dropbox-API-Arg:' . json_encode($params),
			'Content-Type: application/octet-stream', // text/plain; charset=dropbox-cors-hack',
			'Authorization: Bearer ' . $dropbox_token
		)
	);
}


function post_json($url, $params = array(), $headers){
	array_push($headers, 'Content-Type: application/json');
	$r = post($url, json_encode($params), $headers);
	return json_decode($r);
}


function post($url, $postfields, $headers){
    $curlOptions = array(
		CURLOPT_URL => $url,
		CURLOPT_VERBOSE => 0,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_SSL_VERIFYHOST => 2,
		CURLOPT_CAINFO => dirname(__FILE__) . '/cacert.pem',
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_POST => 1,
		CURLOPT_POSTFIELDS => $postfields,
		CURLOPT_HTTPHEADER => $headers,
		CURLINFO_HEADER_OUT => true,
	);
    $ch = curl_init();
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
	//var_dump(curl_getinfo($ch));
	
    if (curl_errno($ch)) { //Check for curl errors
        //dLog("Curl error in post: Could not make call to $url with params:", $params);
		echo 'Curl error: ' . curl_error($ch);
        curl_close($ch);
        return false;
	} 
	curl_close($ch);
	return $response;
}
?>