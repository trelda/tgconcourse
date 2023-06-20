<?php

require realpath(dirname(__FILE__)) . '/connect.php';
$configs = include('configs.php');

function my_curl($url) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	return curl_exec($curl);
	curl_close($curl);
};

function addLog($text, $chatId) {
	$fp = fopen('errors.txt','a');
	fwrite($fp, date("Y-m-d H:i:s").' '.$text.' '.$chatId.PHP_EOL);
	fclose($fp);
};

function checkUser($userId) {
    global $mysqli;
    $userId = (is_numeric($userId)) ? $userId : null;
    $query = "SELECT * FROM game_users WHERE chatId='".$userId."' LIMIT 1";
    $result = $mysqli->query($query);
    $row = $result->num_rows;
    if ($row==0) {
		return false;
	} else {
		return true;
    }
};

function addUser($userId, $userName, $userFirstName, $bot) {
    global $mysqli;
    $userId = (is_numeric($userId)) ? $userId : null;
    $nameTest = '/^[A-Za-z0-9_-]+$/i';
    $userName = (preg_match($nameTest, $userName)) ? $userName : null ;
    $query ="INSERT INTO game_users (`chatId`,`userName`,`type`,`date`, `userFirstName`) VALUES ('".$userId."', '".$userName."', '0', '".date("Y-m-d H:i:s")."', '".$userFirstName."')";
	if ($result = $mysqli->query($query)) {
		return true;
    } else {
		return false;
    }
};

function checkAuthorize($userId) {
    $userId = (is_numeric($userId)) ? $userId : null;
    global $mysqli;
    $query = "SELECT type FROM game_users WHERE chatId='".$userId."'";
    $result = $mysqli->query($query);
    $data = $result->fetch_assoc();
    if ($data['type'] > 0) {
		return true;
    } else {
		return false;
    }
};

function isModer($userId) {
    $userId = (is_numeric($userId)) ? $userId : null;
    global $mysqli;
    $query = "SELECT type FROM game_users WHERE chatId='".$userId."'";
    $result = $mysqli->query($query);
    $data = $result->fetch_assoc();
    if ($data['type'] != 2) {
		return false;
    } else {
		return true;
    }
};

function authorizeUser($userId, $keyId='', $admin=null) {
	if (checkUser($userId)) {
		global $mysqli;
		$userId = (is_numeric($userId)) ? $userId : '' ;
		if ($admin) {
			$query = "UPDATE game_users SET `type`='2' WHERE chatId='".$userId."'";
		} else {
			$query = "UPDATE game_users SET `type`='1', `region`='".$keyId."' WHERE chatId='".$userId."'";
		}
		$result = $mysqli->query($query);
		if ($result) {
			return true;
		} else {
			return false;
		}
	}
};

function startInvite($userId, $gameId, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (checkAuthorize($userId)) {
		$query = "SELECT * FROM game_list WHERE author='".$userId."' AND gameStatus!='6'";
		$result = $mysqli->query($query);
		if ($result->num_rows>0) {
			$bot->sendMessage($userId, "Игра уже создана.");
			return false;
		} else {
			addLog($userId, "Create game, id: ".$gameId);
			$query = "SELECT region FROM game_users WHERE chatId='".$userId."'";
			$result = $mysqli->query($query)->fetch_row();
			$region = $result[0];
			$gameFile = md5($configs['secret'].$gameId).'.php';
			$query = "INSERT INTO game_list (`author`, `gameId`, `gameDate`, `gameFile`) VALUES ('".$userId."', '".$gameId."', '".date("Y-m-d H:i:s")."', '".$gameFile."')";
			addLog('add game',$query);
			addLog(realpath(dirname(__FILE__)).'/'.$gameFile, 'pwd2');
			if (!copy(realpath(dirname(__FILE__)).'/webapp.php', realpath(dirname(__FILE__)).'/games/'.$gameFile)) {
				addLog('not copied', $gameFie);
			} else {
				addLog('game file copied', $gameFie);
			}
			$result = $mysqli->query($query);
			addLog('last game ID: ', $mysqli->insert_id);
			$query = "UPDATE game_users SET inGame='".$mysqli->insert_id."' WHERE chatId='".$userId."'";
			$result = $mysqli->query($query);
			$query = "SELECT chatId, userName FROM game_users WHERE region='".$region."' AND type=1";
			$result = $mysqli->query($query);
			foreach ($result as $key => $value) {
				addLog('invited user:', $value['chatId']);
				try {
					$inviteKeyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
						[[
							['callback_data' => 'command_001_'.$value['userName'], "text" => "Да"],
							['callback_data' => 'command_002_'.$value['userName'], "text" => "Нет"]
						]],
						false,
						true
					);
					$bot->sendMessage($value['chatId'], "Создана новая игра. Желаете вступить?", false, null, null, $inviteKeyboard);
				} catch (Exception $e) {
					addLog('Exception', $e);
				}
			}
			return true;
		}
	} else {
		return false;
	}
};

function replyToModer($userId, $accept, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (checkAuthorize($userId)) {
		$query = "SELECT * FROM game_users WHERE region IN (SELECT region FROM game_users WHERE chatId='".$userId."') AND type='2'";
		$result = $mysqli->query($query)->fetch_row();
		$moderId = $result[1];
		addLog('moder Id:', $moderId);
		$query = "SELECT userName from game_users WHERE chatId='".$userId."'";
		$result = $mysqli->query($query)->fetch_row();
		if ($accept) {
			$query = "SELECT * FROM game_list WHERE author='".$moderId."' AND gameStatus < 5 AND gameStatus >= 0";
			$resultUser = $mysqli->query($query);
			$row = $resultUser->num_rows;
			if ($row!=0) {
				$gameId = $resultUser->fetch_assoc()['id'];
				$query = "UPDATE game_users SET inGame='".$gameId."' WHERE chatId='".$userId."'";
				$resultGame = $mysqli->query($query);
				if ($resultGame) {
					$bot->sendMessage($moderId, $result[0]." присоединяется к игре.");
				}
			}
		} else {
			$bot->sendMessage($moderId, $result[0]." отказался от игры.");
		}
		return true;
	} else {
		return false;
	}
};

function startGame($userId, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (checkAuthorize($userId)) {
		//сколько статусов у игры будет пока не ясно. 0 - создана, 1 - загружены вопросы, 2 - испытаны вопросы, 3 - начата, 4 - получен отчет, 5 - завершена
		$query = "SELECT * FROM game_list WHERE author='".$userId."' AND gameStatus < 5";
		$result = $mysqli->query($query);
	    $row = $result->num_rows;
		$gameId = ($result->fetch_row()[0]);
		addLog('normal game Id: ', $gameId);
		if ($row == 0) {
			addLog($gameId, 'not started');
			return false;
		} else {
			addLog($gameId, 'started');
			$query = "UPDATE game_list SET gameStatus='2' WHERE id='".$gameId."'";
			$result = $mysqli->query($query);
			sendWebFormsToUsers($gameId, $bot);
			if ($result) {
				addLog($gameId, 'game status updated');
				return true;
			} else {
				addLog($gameId, 'error updating game status');
				return false;
			}
		}
	} else {
		return false;
	}
};

function endGame($userId, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (checkAuthorize($userId)) {
		$query = "SELECT * FROM game_list WHERE author='".$userId."' AND gameStatus < 5";
		$result = $mysqli->query($query);
	    $row = $result->num_rows;
		$game = $result->fetch_row();
		$gameId = $game[0];
		addLog($game[6], 'game file');
		addLog('game to stop Id: ', $gameId);
		if ($row == 0) {
			addLog($gameId, 'not started');
			return false;
		} else {
			addLog($gameId, 'stopping!');
			$query = "UPDATE game_list SET gameStatus='6' WHERE id='".$gameId."'";
			$result = $mysqli->query($query);
			$gameFile = md5("myMegaGame".$gameId).'.php';
			addLog($gameFile, 'game file(php)');
			addLog(realpath(dirname(__FILE__)).'/games/'.$game[6], 'full path');
			if (unlink(realpath(dirname(__FILE__)).'/games/'.$game[6])) {
				addLog('game file deleted','');
			} else {
				addLog('game file not deleted','');
			}
			addLog(realpath(dirname(__FILE__)).'/games/'.$game[6], 'delete link');
			if (sendStopToUsers($gameId, $bot)) {
				$query = "UPDATE game_users SET inGame='0', mode='' WHERE inGame='".$gameId."'";
				$result = $mysqli->query($query);
			}
			return true;
		}
	}
};

function sendWebFormsToUsers($gameId, $bot) {
	global $mysqli;
	$gameId = (is_numeric($gameId)) ? $gameId : null;
	$query = "SELECT * FROM game_users WHERE inGame='".$gameId."' AND type='1'";
	$result = $mysqli->query($query);
	addLog('game id:', $gameId);
	$queryGameUrl = "SELECT gameFile FROM game_list WHERE id='".$gameId."'";
	$resultGameUrl = $mysqli->query($queryGameUrl)->fetch_row();
	addLog('game url:', $resultGameUrl[0]);
	foreach ($result as $key=>$value) {
		addLog($value['chatId'], 'user in game');
		$GLOBALS['webapp'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
			[[["text" => "Играть", "web_app" => ['url' => $configs['hosting']."/games/".$resultGameUrl[0]]]]],
			false,
			true
		);
		$bot->sendMessage($value['chatId'], "Для игры нажмите кнопку 'Играть'.", false, null, null, $GLOBALS['webapp']);
	}
	return true;
};

function sendStopToUsers($gameId, $bot) {
	global $mysqli;
	$gameId = (is_numeric($gameId)) ? $gameId : null;
	$query = "SELECT * FROM game_users WHERE inGame='".$gameId."' AND type='1'";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	if ($row != 0) {
		foreach ($result as $key=>$value) {
			$bot->sendMessage($value['chatId'], "Игра завершена.");
		}
		return true;
	} else {
		return false;
	}
};

function gameReport($userId, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	$query = "SELECT * FROM game_users WHERE region IN (SELECT region FROM game_users WHERE chatId='".$userId."') AND type='1' AND inGame IN (SELECT inGame FROM game_users WHERE chatId='".$userId."')";
	addLog($query, 'query');
	$result = $mysqli->query($query);
	$report = "";
	foreach ($result as $key=>$value) {
		$data = getUdata($value['chatId']);
		addLog(serialize($data), 'from mode');
		$report = $report.'Команда: '.urldecode($data['name']).' эффективность: '.$data['efficiency'].'
';
	}
	if ($report == "") {
		$report = "Ответов нет. Если это испытание тестовой игры нажмите /endgame";
	}
	$bot->sendMessage($userId, $report);
};

function sendExcel($userId, $bot) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if (isModer($userId)) {
		addLog($userId, 'sendExcel');
		$query = "SELECT id, gameStatus FROM game_list WHERE author='".$userId."' AND gameStatus='0'";
		$result = $mysqli->query($query);
		$row = $result->num_rows;
		if ($row != 0 ) {
			$bot->sendMessage($userId, "Отправьте заполненный файл образец.");
			$document = new \CURLFile('import.xls');
			$bot->sendDocument($userId, $document);
			$gameId = $result->fetch_row()[0];
			addLog('gameId:', $gameId);
			$query = "UPDATE game_list SET gameStatus='1' WHERE id='".$gameId."'";
			addLog('update game status:', $query);
			$result = $mysqli->query($query);
		} else {
			$bot->sendMessage($userId, "Игра не обнаружена, нажмите /newgame");
		}
	}
};

function fileImport($userId, $file, $bot) {
	global $mysqli;
	addLog($userId, 'import file');
	$userId = (is_numeric($userId)) ? $userId : null;
	if (isModer($userId)) {
		$query = "SELECT id, gameStatus FROM game_list WHERE gameStatus='1' AND author='".$userId."'";
		addLog('get game:', $query);
		$result = $mysqli->query($query);
		addLog('result', serialize($result));
		$row = $result->num_rows;
		$gameId = $result->fetch_row()[0];
		addLog('gameId:', $gameId);
		if ($row != 0 ) {
			addLog($userId, 'game found, change status');
			addLog('file: ', './files/'.$file);
			$inputFileName = './files/'.$file;
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($inputFileName);
			$reader->setReadDataOnly(TRUE);
			$spreadsheet = $reader->load($inputFileName);
			$sheet = $spreadsheet->getActiveSheet();
			$cells = $sheet->getCellCollection();
			$query = "DELETE * FROM game_questions WHERE gameId='".$gameId."'";
			addLog('purge questions', $query);
			$result = $mysqli->query($query);
			$bot->sendMessage($userId, "Файл обрабатывается..");
			for ($i = 2; $i <= $cells->getHighestRow(); $i++) {
				if (($cells->get('A'.$i)->getValue() !== null) && ($cells->get('B'.$i)->getValue() !== null) && ($cells->get('C'.$i)->getValue() !== null)) {
					$query = "INSERT INTO game_questions (`gameId`, `questionText`, `questionScore`, `questionEfficiency`) VALUES 
					('".$gameId."', '".$cells->get('A'.$i)->getValue()."', '".$cells->get('B'.$i)->getValue()."', '".$cells->get('C'.$i)->getValue()."')";
					addLog('insert question', $query);
					$result = $mysqli->query($query);
				}
			}
			$query = "UPDATE game_list SET gameStatus='2' WHERE id='".$gameId."'";
			$result = $mysqli->query($query);
			$bot->sendMessage($userId, "Вопросы загружены, следующая команда /testgame");
		} else {
			addLog($userId, 'game not found');
			$bot->sendMessage($userId, "Игра не найдена, нажмите /sendquestions");
		}
	}
};

function setUdata($chatId, $data = array()) {
	global $mysqli;
	$data = json_encode($data, JSON_UNESCAPED_UNICODE);
	$query = "UPDATE game_users SET mode='".$data."' WHERE chatId = '".$chatId."'";
	$result = $mysqli->query($query);
};

function getUdata($chatId) {
	global $mysqli;
	$res = array();
	$query = "SELECT * FROM game_users WHERE chatId = '".$chatId."'";
	$result = $mysqli->query($query);
	$arr = mysqli_fetch_assoc($result);
	if(isset($arr['mode'])) {
		$res = json_decode($arr['mode'], true);
	}
	return $res;
};

function clearUdata($chatId) {
	global $mysqli;
	$query = "UPDATE game_users SET mode='' WHERE chatId = '".$chatId."'";
	$result = $mysqli->query($query);
};
