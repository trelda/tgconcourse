<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require realpath(dirname(__FILE__)) . '/function.php';

$configs = include('/configs.php');

if( !function_exists('apache_request_headers') ) {
	function apache_request_headers() {
	  $arh = array();
	  $rx_http = '/\AHTTP_/';
	  foreach($_SERVER as $key => $val) {
		if( preg_match($rx_http, $key) ) {
		  $arh_key = preg_replace($rx_http, '', $key);
		  $rx_matches = array();
		  $rx_matches = explode('_', $arh_key);
		  if( count($rx_matches) > 0 and strlen($arh_key) > 2 ) {
			foreach($rx_matches as $ak_key => $ak_val) $rx_matches[$ak_key] = ucfirst($ak_val);
			$arh_key = implode('-', $rx_matches);
		  }
		  $arh[$arh_key] = $val;
		}
	  }
	  return( $arh );
	};
}

$headers = apache_request_headers();
$token = $configs['token'];
$arr = array();
$arr = ['query_id' => $headers['Queryid'], 'user' => urldecode($headers['User']), 'hash' => $headers['Hash'], 'auth_date' => $headers['Authdate']];
$check_hash = $arr['hash'];
unset($arr['hash']);
foreach($arr as $k => $v) $check_arr[]=$k.'='.$v;
@sort($check_arr);
$string = @implode("\n", $check_arr);
$secret_key = hex2bin(hash_hmac('sha256', $token, "WebAppData"));
$hash = hash_hmac('sha256', $string, $secret_key);
if (strcmp($hash, $check_hash) == 0) {
	try {
		require realpath(dirname(__FILE__)) . '/vendor/autoload.php';
		require realpath(dirname(__FILE__)) . '/connect.php';
		global $mysqli;
		$bot = new \TelegramBot\Api\Client($token);
		$gameId = (is_numeric($headers['Gameid'])) ? $headers['Gameid'] : null;
		if ($gameId > 2) {
			$query = "SELECT author FROM game_list where id='".$gameId."'";
			$result = $mysqli->query($query)->fetch_row();
			$moderId = $result[0];
		} else {
			$moderId = $configs['moderator'];
		}
		$query = "SELECT userName, mId FROM game_users WHERE chatId='".$headers['Userid']."'";
		$result = $mysqli->query($query)->fetch_row();
		$userName = $result[0];
		$mId = $result[1];
		$query = "SELECT * FROM game_questions WHERE id in (".$headers['Ansvers'].")";
		$result = $mysqli->query($query);
		$efficiency = 0;
		$answers = 'Ответы пользователя: '.urldecode($headers['Command']).'

';
		foreach ($result as $key=>$value) {
			$efficiency = $efficiency + $value['questionEfficiency'];
			$answers = $answers.$value['questionText'].',

';
		}
		
		$arrAnswers = explode(',', $headers['Ansvers']);
	
		$bot->sendMessage($moderId, $answers.'Эффективность: '.$efficiency);
		$bot->sendMessage($headers['Userid'], $answers);
		$bot->sendMessage($headers['Userid'], 'Ваши варианты приняты. Для начала новой игры введите пароль.');

		clearUdata($headers['Userid']);
		$data = getUdata($headers['Userid']);
		$uData = array('name' => $headers['Command'], 'efficiency' => $efficiency);
		setUdata($headers['Userid'], $uData);

	} catch (\TelegramBot\Api\Exception $e) {
		file_put_contents('errors.txt', sprintf("[TelegramAPI]\t[%s]\t%s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
		return;
	}
}

?>