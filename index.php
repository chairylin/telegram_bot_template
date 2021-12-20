<?php

define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $payload = json_encode($parameters);
  header('Content-Type: application/json');
  header('Content-Length: '.strlen($payload));
  echo $payload;

  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successful: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POST, true);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
  // process incoming message
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if (isset($message['text'])) {
    // incoming text message
    $text = $message['text'];

    if (strpos($text, "/start") === 0) {
		
		$buttons = json_encode([
            'inline_keyboard' => [
                [
                    [
                        // текст кнопки
                        "text" => "Привет",
                        // передаем значения для обработки кнопки разделенные знаком _
                        // первым идет метод который будет обрабатывать эту кнопку
                        // в данном примере это actionInlineButton
                        // вторым параметром идет значение 1234 для примера
                        // параметров может быть много все через заранее определенный
                        // знак в этом случае это нижнее подчеркивание
                        // общая длинна всей строки не должна превышать 64 байта (символа)
                        "callback_data" => "actionInlineButton_1234"
                    ],
                    [
                        // текст кнопки
                        "text" => "Здравствуйте",
						"callback_data" => "actionInlineButton_4321"
                        // ссылка на ресурс
                        //"url" => "http://imakebots.ru"
                    ]
                ]
            ],
        ], true);
		
      apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Привет', 
		/*'reply_markup' => array(
			'remove_keyboard' => true,
			),*/
		/*'reply_markup' => array(
			'keyboard' => array(array('Hello', 'Hi')),
			'one_time_keyboard' => true,
			'resize_keyboard' => true),*/
		'reply_markup' => array(
			'inline_keyboard' =>  array( 
				array(
					array('text'=>'Привет','callback_data'=>"1"),
				),
				array(
					array('text'=>'Здравствуйте','callback_data'=>"2"),
				),
		)),
		
		));
    } else if ($text === "Привет" || $text === "Здравствуйте") {
      apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Приятно познакомиться', 'reply_markup' => array('remove_keyboard' => true)));
    } else if (strpos($text, "/stop") === 0) {
      // stop now
    } else {
      apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Класс'));
    }
  } else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
  }
}

function processCallback($callback, $message) {
  // process incoming message
  $message_id = $message['message_id'];
  $chat_id = $message['chat']['id'];
  if ( isset($callback["data"]) ) {
	  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Приятно познакомиться', 'reply_markup' => array('remove_keyboard' => true)));
  } else {
	  apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
  }
}


/*define('WEBHOOK_URL', 'https://my-site.example.com/secret-path-for-webhooks/');

if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}*/


$content = file_get_contents("php://input");
$update = json_decode($content, true);


file_put_contents( __DIR__.'/logfile'.date("m.Y").'.txt', '--- NEW --- ' . date('d.m.Y') ."\n". print_r($update,1) ."\n" ,FILE_APPEND );

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update["message"])) {
  processMessage($update["message"]);
}
if (isset($update["callback_query"])) {
  processCallback($update["callback_query"], $update["callback_query"]["message"]);
}