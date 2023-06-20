<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require realpath(dirname(__FILE__)) . '/../connect.php';

$configs = include('config.php');

global $mysqli;
echo "
<html>
	<head>
		<script src='https://telegram.org/js/telegram-web-app.js'></script>
		<script src='jquery.min.js'></script> 
	</head>
	<body style='padding:10px;margin:20px;border:1px solid #ccc;' id='bodyMain'>
		<style>
			.variant {
				border: 1px solid #ccc;
				padding: 5px;
				border-radius: 5px;
				margin-bottom: 10px;
			}
			.active {
				background: #a0fd9a;
			}
			.not_active {
				background: #e2f6ff;
			}
			.command {
				margin-bottom: 20px;
				display: flex;
				flex-direction: column;
			}
			.commandInput {
				padding: 5px;
				border-radius: 5px;
			}
			.commandInput:focus {
				border: 2px solid #23B578 !important;
				outline: none !important;
			}
			.uptext {
				font-size: 20px;
			}
			.sendResult {
				display: block;
				font-size: 14px;
				margin: 15px 0;
				padding: 12px 20px;
				border: none;
				border-radius: 4px;
				background-color: var(--tg-theme-button-color, #50a8eb);
				color: var(--tg-theme-button-text-color, #ffffff);
				cursor: pointer;
				width: 200px;
			}
			.replyWindow {
				position: fixed;
				top: 30px;
				display: flex;
				background: #ccc;
				border-radius: 10px;
				padding: 10px;
				width: 60vw;
				height: 150px;
				align-content: center;
				justify-content: center;
				align-items: center;
				color: #ff1f1f;
				font-size: 18px;
				font-weight: 700;
				margin: 0px 13%;
				opacity: 0;
				transition: 0.5s;
				display: none;
			}
			.content {
				color: #f98a00 !important;
			}
		</style>
		<div class='command'>
			<div style='margin-bottom: 15px;color: #f98a00;'>Введите номер стола (обязательно):</div>
			<input true='text'/ class='commandInput' id='commandInput'>
		</div>
		<div class='content'>Выберите варианты. У вас осталось баллов: <span class='uptext' id='scoreCount'>300</span></div>
";

$gameUrl = basename(__FILE__);
$gameTest = '/^[A-Za-z0-9.]+$/i';
if (preg_match($gameTest, $gameUrl)) {
	$query = "SELECT * FROM game_questions WHERE gameId IN (SELECT id FROM game_list WHERE gameFile='".$gameUrl."')";
	$result = $mysqli->query($query);
	$i = 1;
	foreach ($result as $key=>$value) {
		echo "<div onclick='changeState(this);' id='".$value['id']."' name='".$value['questionScore']."' class='variant not_active'>".$i.". ".$value['questionText']." (".$value['questionScore'].")</div>";
		$i++;
	}
	$gameId = $value['gameId'];
	$query = "SELECT id FROM game_list WHERE gameFile='".$gameUrl."'";
	$result = $mysqli->query($query)->fetch_row();
	$idGame =  $result[0];
	echo "<div style='display:none;' onclick='sendAnswers();'>Send</div>";
} else {
	echo "Игра не корректна.";
}
echo "<div id='rest' class='replyWindow'>Отклик</div>
<div class='content'>У вас осталось баллов: <span  class='uptext' id='scoreCount2'>300</span></div>

<input type='button' class='sendResult' id='sendResult' value='Отправить ответы' onclick='prepareSend();'/>
";

echo "		
	<script>
		function setThemeClass() {
			document.documentElement.className = Telegram.WebApp.colorScheme;
		}
		Telegram.WebApp.onEvent('themeChanged', setThemeClass);
		setThemeClass();
	</script>
	
	<script type='application/javascript'>
    Telegram.WebApp.ready();
	const initData = Telegram.WebApp.initData || '';
	const initDataUnsafe = Telegram.WebApp.initDataUnsafe || {};
	var predlIsDown = false;
    if (!Telegram.WebApp.initDataUnsafe || !Telegram.WebApp.initDataUnsafe.query_id) {
		predlIsDown = true;
	}
	if (predlIsDown != false) {
		document.querySelector('#bodyMain').innerHTML = '';
		} else {
			const userId = initDataUnsafe.user.id;
			const mainButton = Telegram.WebApp.MainButton;
			Telegram.WebApp.MainButton
				.setText('Закрыть')
				.hide()
				.onClick(function(){ webviewClose(); });
		}
		
    function webviewClose() {
        Telegram.WebApp.close();
    };
	
	function changeState(elm) {
		let score = 300;
		if (elm.classList.contains('active')) {
			elm.classList.remove('active');
			elm.classList.add('not_active');
		} else {
			elm.classList.add('active');
			elm.classList.remove('not_active');
		}
		let elms = document.getElementsByClassName('variant');
		for (let i = 0; i < elms.length; i++) {
		
			if (elms[i].classList.contains('active')) {
				score = score - elms[i].attributes.name.value;
			}
		}
		if (score < 0) {
			delay('Вы выбрали ресурсов больше 300 баллов. Пожалуйста, уберите один ресурс, чтобы продолжить.');
			document.getElementById('scoreCount').innerHTML = '<br/>Вы выбрали ресурсов больше 300 баллов. Пожалуйста, уберите один ресурс, чтобы продолжить.';
			document.getElementById('scoreCount2').innerHTML = '<br/>Вы выбрали ресурсов больше 300 баллов. Пожалуйста, уберите один ресурс, чтобы продолжить.';
		} else {
			document.getElementById('scoreCount').innerHTML = score;
			document.getElementById('scoreCount2').innerHTML = score;
		}
	}
	function sendAnswers() {
		let elms = document.getElementsByClassName('variant');
		let ansvers = [];
		for (let i = 0; i < elms.length; i++) {
			if (elms[i].classList.contains('active')) {
				ansvers.push(elms[i].id);
			}
		}
		let commandInput = document.getElementById('commandInput').value;
		let score = document.getElementById('scoreCount').innerHTML;
		if ((score/1) > -1) {
			const request = new XMLHttpRequest();
			request.responseType = 'text';
			const url = ".$configs['hosting']."'/ajax.php';
			request.onload = () => {
				Telegram.WebApp.MainButton
				.show();
				document.getElementById('sendResult').style.display = 'none';
				delay('Ответы отправлены, можете нажать кнопку \"Закрыть\"');
			};
			request.open('POST', url, true);
			request.setRequestHeader('queryid', initDataUnsafe.query_id);
			request.setRequestHeader('user', encodeURI(JSON.stringify(initDataUnsafe.user)));
			request.setRequestHeader('authdate', initDataUnsafe.auth_date);
			request.setRequestHeader('hash', initDataUnsafe.hash);
			request.setRequestHeader('ansvers', ansvers);
			request.setRequestHeader('userid', initDataUnsafe.user.id);
			request.setRequestHeader('gameid', ".$idGame.");
			request.setRequestHeader('command', encodeURI(commandInput));
			request.send();
		}
	}
	
	function prepareSend() {
		let commandInput = document.getElementById('commandInput').value;
		if (commandInput.length > 0) {
			sendAnswers();
		} else {
			delay('Введите название команды');
		}
	}
	
	function delay(data) {
	var timeDelay = 100;
	let a = document.getElementById('rest');
	a.innerHTML = data;
	a.style.display = 'flex';
	a.style.opacity = 1;
	const run = setInterval(frame, 50);
	function frame() {
		if (timeDelay == 0) {
			clearInterval(run);
			a.style.opacity = 0;
			a.style.display = 'none';
		} else {
			timeDelay--;
		}
	}
};
	</script>
	</body>	
</html>	
";
?>