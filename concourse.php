<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require realpath(dirname(__FILE__)) . '/vendor/autoload.php';
require realpath(dirname(__FILE__)) . '/connect.php';
require realpath(dirname(__FILE__)) . '/function.php';

$configs = include('configs.php');

$GLOBALS['passList'] = $configs['passlist'];

$GLOBALS['token'] = $configs['token'];

try {
    $bot = new \TelegramBot\Api\Client($GLOBALS['token']);

    $bot->command('start', function ($message) use ($bot) {
		global $mysqli;
		$userId = $message->getChat()->getId();
		$userName = $message->getChat()->getUsername();
		$userFirstName = mysqli_real_escape_string($mysqli, $message->getChat()->getFirstName());
		if (!checkUser($userId)) {
			$bot->sendMessage($userId, "Введите, пожалуйста, пароль.");
			addUser($userId, $userName, $userFirstName, $bot);
		} else {
			clearUdata($userId);
			if (checkAuthorize($userId)) {
				$bot->sendMessage($userId, "Ожидайте начала игры.");
			} else {
				$bot->sendMessage($userId, "Вы еще не ввели пароль.");
			}
		}
    });

    $bot->command('newgame', function ($message) use ($bot) {
    	$userId = $message->getChat()->getId();
    	if (isModer($userId)) {
    		//set status 0
    		addLog($userId, 'create the game');
    		if (startInvite($userId, time(), $bot)) {
    			$bot->sendMessage($userId, "Приглашения вступить в игру разосланы. Следующая команда /sendquestions");
    		} else {
    			$bot->sendMessage($userId, "Ошибка создания игры.");
    		}
    	};
    });

	$bot->command('sendquestions', function ($message) use ($bot) {
		//set status 1
		addLog($userId, 'import questions');
		$userId = $message->getChat()->getId();
		if (isModer($userId)) {
			addLog($userId, 'is moderator');
			sendExcel($userId, $bot);
		}
	});

	$bot->command('testgame', function ($message) use ($bot) {
		//set status 2 
		$userId = $message->getChat()->getId();
		if (isModer($userId)) {
			global $mysqli;
			$query = "SELECT gameFile FROM game_list WHERE author='".$userId."' AND gameStatus='2'";
			$result = $mysqli->query($query)->fetch_row();
			addLog('game url:', $result[0]);
			$GLOBALS['webapp'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup(
				[[["text" => "Играть", "web_app" => ['url' => $configs['hosting']."/games/".$result[0]]]]],
				false,
				true
			);
			$bot->sendMessage($userId, "Тест игры. Если все успешно нажать /startgame", false, null, null, $GLOBALS['webapp']);
		}
	});

    $bot->command('startgame', function ($message) use ($bot) {
    	$userId = $message->getChat()->getId();
		//set status 3
    	if (isModer($userId)) {
    		addLog($userId, 'start the game');
    		if (startGame($userId, $bot)) {
    			$bot->sendMessage($userId, "Игра запущена, пользователям разосланы бланки. Для получения сводного отчета нажмите /gamereport");
    		} else {
    			$bot->sendMessage($userId, "Ошибка запуска игры.");
    		}
    	};
    });

	$bot->command('gamereport', function ($message) use ($bot) {
		//set status 4
		$userId = $message->getChat()->getId();
		addLog($userId, 'create report');
		if (isModer($userId)) {
			addLog($userId, 'is moderator');
			gameReport($userId, $bot);
		}
	});

	//set status 5 - ....

	$bot->command('endgame', function ($message) use ($bot) {
		$userId = $message->getChat()->getId();
		if (isModer($userId)) {
			//set staus 6
			addLog($userId, 'end the game');
			if (endGame($userId, $bot)) {
				$bot->sendMessage($userId, "Игра завершена. Для новой игры выберите /newgame");
			} else {
				$bot->sendMessage($userId, "Ошибка остановки игры.");
			}
		}
	});

	$bot->command('menu', function ($message) use ($bot) {
		$userId = $message->getChat()->getId();
		if (isModer($userId)) {
			$bot->sendMessage($userId, "Приветствую, администратор. Ваши команды: 
/newgame - создание игры
/sendquestions - импорт вопросов
/testgame - пробный запуск игры
/startgame - запуск игры
/gamereport - таблица эффективности
/endgame - остановка игры
");
		}
	});

	$bot->callbackQuery(function ($callbackQuery) use ($bot) {
		$message = $callbackQuery->getMessage();
		$chatId = $message->getChat()->getId();
		if (checkAuthorize($chatId)) {
			$mId = $message->getMessageId();
			$params = $callbackQuery->getData();
			$text = "success!";
			$data = getUdata($chatId);
			addLog('params:', $params);
			$command = substr($params, 0, 11);
			$senderId = substr($params, 12, strlen($params));
			addLog($command, $senderId);
			switch ($command) {
				case 'command_001':
					$bot->deleteMessage($chatId, $mId);
					replyToModer($chatId, true, $bot);
				break;
				case 'command_002':
					$bot->deleteMessage($chatId, $mId);
					replyToModer($chatId, false, $bot);
				break;
			}
		}
	});

	$bot->on(function ($update) use ($bot) {
		global $mysqli;
		$message = $update->getMessage();
		$userId = $message->getChat()->getId();
		$postText=$message->getText();
		addLog($userId, 'update');
		if ((checkUser($userId)) && (!checkAuthorize($userId))) {
			addLog($userId, 'user checked, not authorized');
			$keyId = array_search ($postText, $GLOBALS['passList']);
			if ($keyId) {
				addlog($userId, "keyId: ".$keyId);
				if (authorizeUser($userId, $keyId)) {
					$bot->sendMessage($userId, "Авторизация успешна. Ожидайте начала игры.");
				}
			} else {
				addLog($userId, 'wrong pass');
				addLog('key:', $keyId);
				if ($keyId === 0) {
					addLog($userId, 'admin pass');
					if (authorizeUser($userId, $keyId, true)) {
						$bot->sendMessage($userId, "Приветствую, администратор. Ваши команды: 
/newgame - создание игры
/sendquestions - импорт вопросов
/testgame - пробный запуск игры
/startgame - запуск игры
/gamereport - таблица эффективности
/endgame - остановка игры
");
					}
				}
			}
		} elseif (checkAuthorize($userId)) {
			addLog($userId, 'user authorized');
			$data = getUdata($userId);
			$keyId = array_search ($postText, $GLOBALS['passList']);
			if ($keyId === 0) {
				addLog($userId, 'admin pass');
				if (authorizeUser($userId, $keyId, true)) {
					$bot->sendMessage($userId, "Приветствую, администратор. Ваши команды: 
/newgame - создание игры
/sendquestions - импорт вопросов
/testgame - пробный запуск игры
/startgame - запуск игры
/gamereport - таблица эффективности
/endgame - остановка игры
");
				}
			} elseif ($keyId) {
				addlog($userId, "keyId: ".$keyId);
				if (authorizeUser($userId, $keyId)) {
					$bot->sendMessage($userId, "Авторизация успешна. Ожидайте начала игры.");
				}
			} else {
				if ($message->getDocument()) {
					$fileName = $message->getDocument()->getFilename();
					addLog('try upload file', $fileName);
					$mime = $message->getDocument()->getMimeType();
					$accept = array(
						'application/vnd.ms-excel', 
						'application/msexcel', 
						'application/x-msexcel', 
						'application/x-ms-excel', 
						'application/x-excel', 
						'application/x-dos_ms_excel', 
						'application/xls', 
						'application/x-xls', 
						'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
					if (in_array(strtolower($message->getDocument()->getMimeType()), $accept)) {
						addLog($mime, 'excel');
						$file = $message->getDocument();
						$fileId = $file->getFileId();
						$url='https://api.telegram.org/bot'.$GLOBALS['token'].'/getFile?file_id='.$fileId;
						$str = my_curl($url); 
						$strj = json_decode($str, true);
						$file_path = $strj['result']['file_path'];
						$link = 'https://api.telegram.org/file/bot'.$GLOBALS['token'].'/'.$file_path; 
						$uploaddir = './files/';
						$uploadFile = $uploaddir.basename($link);
						if (copy($link, $uploadFile)) {
							addLog('copied file:', basename($link));
							fileImport($userId, basename($link), $bot);
						}
					}
				}
			}
		} else {
			$bot->sendMessage($userId, "Команда не ясна.");
		}
	}, function() {
		return true;
		}
	);

	$bot->run();
}
catch (\TelegramBot\Api\Exception $e) {
    file_put_contents('errors.txt', sprintf("[TelegramAPI]\t[%s]\t%s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
    return;
}
?>