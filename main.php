<?php

ini_set('log_errors', 1);
ini_set('error_log', '/home/g/ghulqul/facehookapp.ru/public_html/source/FaceApp/PHP_errors_test3.log');
error_reporting(E_ERROR); // Устанавливаем уровень ошибок

$token = '6660548794:AAHhy82DMJtws1NlMj7VIx0_zDw8c_MswWk';

$host = 'localhost';
$user = 'ghulqul_face_app';
$password = 'A951753d!81902018B';
$database = 'ghulqul_face_app';

$data = json_decode(file_get_contents('php://input'), TRUE);
// file_put_contents('file.txt', '$data: '.print_r($data, 1)."\n", FILE_APPEND);
$mysqli = new mysqli($host, $user, $password, $database);
$mysqli->set_charset('utf8mb4');

// Подготовьте данные для вставки в таблицу
$update_id = $data['update_id'];
$message_id = $data['message']['message_id'] ?? $data['callback_query']['message']['message_id'];
$chat_id = $data['message']['chat']['id'] ?? $data['callback_query']['message']['chat']['id'];
$username = $data['message']['from']['username'] ?? $data['callback_query']['from']['username'];
$text = $data['message']['text'] ?? $data['callback_query']['data'];
//Проверка на запрещённые символы
if (hasBackSlash($text) == true) {
    sendTelegramMessage($token, $chat_id, 'Нельзя использовать "\"', 0, $mysqli);
    return;
}
$location = $data['message']['location'];
$i=0;
$check_file_id = $data['message']['photo'][$i]['file_id'];
// Выбор наилучшего разрешения фото
while (isset($check_file_id)) {
    $i++;
    $check_file_id = $data['message']['photo'][$i]['file_id'];
}
$i -= 1;
$file_id = $data['message']['photo'][$i]['file_id'];
$video_id = $data['message']['video']['file_id'];

// SQL-запрос для вставки в лог
$sql = "INSERT INTO msg_webhook (update_id, message_id, chat_id, username, text, reg_step)
        VALUES ('$update_id', '$message_id', '$chat_id', '$username', '$text', 0)";
$mysqli->query($sql);

//Функция показа пар
function showMatches ($token, $chat_id, $mysqli) {
	$sqlLikeQueue = "SELECT first_id, second_id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate = true)
                                                             OR (first_id = '$chat_id' and second_rate = true and first_rate = true) ";
	$resultLikeQueue = $mysqli->query($sqlLikeQueue);
    if ($resultLikeQueue->num_rows == 0) {
		sendTelegramMessage ($token, $chat_id, 'У вас пока нет пар. Вернитесь позже)', 0, $mysqli);
        $sqlFilter = ("UPDATE users SET match_menu_flag = true WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlFilter);
        deleteMenu($token, $chat_id, $mysqli);
        sendTelegramMessage ($token, $chat_id, 'Ваши лайки:', 7, $mysqli);
		return;
	}
	$i = 0;
	while ($i < $resultLikeQueue->num_rows) {
		$rowLiker = $resultLikeQueue->fetch_assoc();
		if ($rowLiker['first_id'] == $chat_id) {
			$match_id = $rowLiker['second_id'];
		}
		elseif ($rowLiker['second_id'] == $chat_id) {
			$match_id = $rowLiker['first_id'];
		}
        $sqlMatchUsername = "SELECT username FROM users WHERE chat_id = '$match_id'";
	    $resultMatchUsername = $mysqli->query($sqlMatchUsername);
        $row = $resultMatchUsername->fetch_assoc();
		showProfile ($token, $chat_id, $match_id, $mysqli);
        sendTelegramMessage ($token, $chat_id, 'Начинай общение ➤ @'.$row['username'], 0, $mysqli);
		$i += 1;
	}
    $sqlFilter = ("UPDATE users SET match_menu_flag = true WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlFilter);
	sendTelegramMessage ($token, $chat_id, 'Ваши лайки:', 7, $mysqli);
	return;
}

//Функция показа лайкнувших анкету
function comingLikes ($token, $chat_id, $mysqli) {
    $sqlShowFlag = "UPDATE users SET coming_flag = TRUE WHERE chat_id = '$chat_id'";
    $mysqli->query($sqlShowFlag);
    $sqlLikeQueue = "SELECT first_id, second_id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate IS NULL)
                                                             OR (first_id = '$chat_id' and second_rate = true and first_rate IS NULL) ";
    $resultLikeQueue = $mysqli->query($sqlLikeQueue);
    if ($resultLikeQueue->num_rows == 0) {
        $sqlShowFlag = "UPDATE users SET coming_flag = FALSE, match_menu_flag = true WHERE chat_id = '$chat_id'";
        $mysqli->query($sqlShowFlag);
        sendTelegramMessage($token, $chat_id, "Вы никому не нужны!", 8, $mysqli);
        deleteMenu ($token, $chat_id, $mysqli);
        sendTelegramMessage($token, $chat_id, "Ваши лайки:", 7, $mysqli);
        return;
    }
    $rowLiker = $resultLikeQueue->fetch_assoc();
    if ($rowLiker['first_id'] == $chat_id) {
        $match_id = $rowLiker['second_id'];
    }
    elseif ($rowLiker['second_id'] == $chat_id) {
        $match_id = $rowLiker['first_id'];
    }
    $sqlUpdShownId = "UPDATE users SET last_shown_id = '$match_id' WHERE chat_id = '$chat_id'";
    $mysqli->query($sqlUpdShownId);
    showProfile ($token, $chat_id, $match_id, $mysqli);
    sendTelegramMessage ($token, $chat_id, 'Оцените анкету', 10, $mysqli);
    return;
}

//Функция фильтрации показа анкет
function showAlgorithm ($token, $chat_id, $mysqli) {
    $sqlShowFlag = "UPDATE users SET show_flag = TRUE WHERE chat_id = '$chat_id'";
    $mysqli->query($sqlShowFlag);
    //Запрос для получения параметров профиля пользователя
    $sqlShowFilter = "SELECT gender, favorite_gender, city, filter_location, favorite_age_min, favorite_age_max FROM users WHERE chat_id = '$chat_id'";
    $resultShowFilter = $mysqli->query($sqlShowFilter);
    $showFilter = $resultShowFilter->fetch_assoc();
    $gender = $showFilter ['gender'];
    $favorite_gender = $showFilter ['favorite_gender'];
    $city = $showFilter ['city'];
    $filter_location = $showFilter ['filter_location'];
    $favorite_age_min = $showFilter ['favorite_age_min'];
    $favorite_age_max = $showFilter ['favorite_age_max'];
    // Запрос для получения всех уже показанных анкет
    $sqlAllShowed = "SELECT first_id, second_id FROM rate WHERE (first_id = '$chat_id' AND first_rate IS NOT NULL) OR (second_id = '$chat_id' AND second_rate IS NOT NULL)";
    $resultSqlAllShowed = $mysqli->query($sqlAllShowed);
    // Проверка наличия уже показанных анкет
    if ($resultSqlAllShowed->num_rows != 0) {
        // Если есть уже показанные анкеты, создаем массив с ними
        $rowsAllShowed = array();
        while ($rowAllShowed = $resultSqlAllShowed->fetch_assoc()) {
            if ($rowAllShowed['first_id'] == $chat_id) {
                $rowsAllShowed[] = "'" . $rowAllShowed['second_id'] . "'";
            }
            else {
                $rowsAllShowed[] = "'" . $rowAllShowed['first_id'] . "'";
            }
        }
        $rowsAllShowed_separated = join (', ', $rowsAllShowed); //Необходимо для корректного запроса
        //Создание запроса для выборки новой анкеты
        if ($favorite_gender == 'Мужской' || $favorite_gender == 'Женский') {
            if ($filter_location == 'local') {
                $sql = "SELECT chat_id FROM users
                        WHERE chat_id != '$chat_id' AND gender = '$favorite_gender' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                    AND city = '$city' AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                    AND reg_step = '10'
                                                    AND chat_id NOT IN ($rowsAllShowed_separated)";
            }
            else {
                $sql = "SELECT chat_id FROM users
                        WHERE chat_id != '$chat_id' AND gender = '$favorite_gender' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                    AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                    AND reg_step = '10'
                                                    AND chat_id NOT IN ($rowsAllShowed_separated) AND filter_location = '$filter_location'";
            }

        }
        else {
            if ($filter_location == 'local') {
                $sql = "SELECT chat_id FROM users
                        WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все') AND city = '$city'
                                                    AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                    AND reg_step = '10'
                                                    AND chat_id NOT IN ($rowsAllShowed_separated)";
            }
            else {
                $sql = "SELECT chat_id FROM users
                        WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                    AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                    AND reg_step = '10'
                                                    AND chat_id NOT IN ($rowsAllShowed_separated) AND filter_location = '$filter_location'";
            }
        }
    }
    elseif ($favorite_gender == 'Мужской' || $favorite_gender == 'Женский') {
        if ($filter_location == 'local') {
            $sql = "SELECT chat_id FROM users
                    WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                AND gender = '$favorite_gender' AND city = '$city'
                                                AND reg_step = '10'";
        }
        else {
            $sql = "SELECT chat_id FROM users
                    WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                AND gender = '$favorite_gender' AND filter_location = '$filter_location'
                                                AND reg_step = '10'";
        }
    }
    else {
        if ($filter_location == 'local') {
            $sql = "SELECT chat_id FROM users
                    WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                AND age >= '$favorite_age_min' AND age <= '$favorite_age_max'
                                                AND city = '$city'
                                                AND reg_step = '10'";
        }
        else {
            $sql = "SELECT chat_id FROM users
                    WHERE chat_id != '$chat_id' AND (favorite_gender = '$gender' OR favorite_gender = 'Все')
                                                AND age >= '$favorite_age_min' AND age <= '$favorite_age_max' AND filter_location = '$filter_location'
                                                AND reg_step = '10'";
        }
    }
    $result = $mysqli->query($sql);
    //Проверка наличия анкет по фильтру
    if ($result->num_rows == 0) {
        sendTelegramMessage ($token, $chat_id, 'Анкеты закончились', 8, $mysqli);
        $sqlShowFlag = "UPDATE users SET show_flag = FALSE, main_menu_flag = true WHERE chat_id = '$chat_id'";
        $mysqli->query($sqlShowFlag);
        sendTelegramMessage($token, $chat_id, 'Главное меню:', 1, $mysqli);
        return;
    }
    $rows = $result->fetch_assoc();
    $match_id = $rows['chat_id'];
    //Вызов функции показа анкеты
    $sqlUpdShownId = "UPDATE users SET last_shown_id = '$match_id' WHERE chat_id = '$chat_id'";
    $mysqli->query($sqlUpdShownId);
    showProfile($token, $chat_id, $match_id, $mysqli);
    sendTelegramMessage ($token, $chat_id, 'Оцените анкету', 10, $mysqli);
    //Создание новой строчки с показаной анкетой
    $sqlRate = "SELECT id FROM rate WHERE (first_id = '$match_id' AND second_id = '$chat_id') OR (first_id = '$chat_id' AND second_id = '$match_id')";
    $resultRate = $mysqli->query($sqlRate);
    if ($resultRate->num_rows != 0) {
        return;
    }
    else {
        $sqlShow = "INSERT INTO rate (first_id, second_id) VALUES ('$chat_id', '$match_id')";
        $mysqli->query($sqlShow);
        return;
    }
}

//Функция удаления меню
function deleteMenu($chat_id, $token, $mysqli) {
    $sql = "SELECT message_id FROM msg_webhook
            WHERE chat_id = '$chat_id' AND (text = '/showprofile' OR text = '/startmatch' OR text = '/register'
                                            OR text = '/start' OR text = '/checklike' OR text = '/matches' OR text = '/age'
                                            OR text = '/combacktostartmatches' OR text = '/combacktostartmenu' OR text = '/favorite_gender'
											                      OR text = '/soulmatetest')
                                            ORDER BY id DESC LIMIT 1";
    $result = $mysqli->query($sql);
    $row = $result->fetch_assoc();
        // Данные для запроса
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $row['message_id'],
    ];

    $ch = curl_init("https://api.telegram.org/bot". $token ."/deleteMessage?" . http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

function editTelegramMessage($token, $chat_id, $step, $mysqli) {
    $sql = "SELECT message_id FROM msg_webhook WHERE chat_id = '$chat_id' AND (text = '/distance' OR text = '/filter'
                                                                            OR text = '/myprofilemenu' OR text = '/matchmenu'
                                                                            OR text = '/combacktostartmatches' OR text = '/combacktostartmenu')
                                                                            ORDER BY id DESC LIMIT 1";
    $result = $mysqli->query($sql);
    $message_id = $result->fetch_assoc();
    switch ($step) {
        case 0:
            $sqlFilter = "SELECT filter_location, favorite_gender, favorite_age_min, favorite_age_max, show_flag FROM users WHERE chat_id = '$chat_id'";
            $resultFilter = $mysqli->query($sqlFilter);
            $filter = $resultFilter->fetch_assoc();
            if ($filter['filter_location'] == 'global') {
              $filter_location = 'без ограничений';
            }
            elseif ($filter['filter_location'] == 'local') {
              $filter_location = 'по городу';
            }
            if ($filter['show_flag'] == true) {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "message_id" => $message_id['message_id'],
                    "text" => 'Настройка фильтра показа анкет:',
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Расстояние поиска: '.$filter_location,
                                    'callback_data' => '/distance',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Возраст: '.$filter['favorite_age_min'].'-'.$filter['favorite_age_max'],
                                    'callback_data' => '/age',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Кого вы ищете: '.$filter['favorite_gender'],
                                    'callback_data' => '/favorite_gender',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Продолжить просмотр анкет',
                                    'callback_data' => '/combacktostartmatches',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
            else {
                $getQuery = array(
                    "chat_id" 	=> $chat_id,
                    "message_id" => $message_id['message_id'],
                    "text" => 'Настройка фильтра показа анкет:',
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Расстояние поиска: '.$filter_location,
                                    'callback_data' => '/distance',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Возраст: '.$filter['favorite_age_min'].'-'.$filter['favorite_age_max'],
                                    'callback_data' => '/age',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Кого вы ищете: '.$filter['favorite_gender'],
                                    'callback_data' => '/favorite_gender',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'В главное меню',
                                    'callback_data' => '/combacktostartmenu',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
        case 1:
            $sqlLikeQueue = "SELECT id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate IS NULL)
                                                    OR (first_id = '$chat_id' and second_rate = true and first_rate IS NULL)";
            $resultLikeQueue = $mysqli->query($sqlLikeQueue);
            $countLikes = $resultLikeQueue->num_rows;
            $sqlMatches = "SELECT id FROM rate WHERE (first_id = '$chat_id' OR second_id = '$chat_id') AND (first_rate = true AND second_rate = true)";
            $resultMatches = $mysqli->query($sqlMatches);
            $countMatches = $resultMatches->num_rows;
            $getQuery = array(
                "chat_id" => $chat_id,
				"message_id" => $message_id['message_id'],
                "text" => 'Ваши лайки:',
                'reply_markup' => json_encode(array(
                    'inline_keyboard' => array(
                        array(
                            array(
                                'text' => "Ваши пары ($countMatches)",
                                'callback_data' => '/matches',
                            ),
                        ),
                        array(
                            array(
                                'text' => "Анкеты которым вы понравились ($countLikes)",
                                'callback_data' => '/checklike',
                            ),
                        ),
                        array(
                            array(
                                'text' => 'В главное меню',
                                'callback_data' => '/combacktostartmenu',
                            ),
                        ),
                    ),
                )),
            );
            break;
        case 2:
            $sqlLikeQueue = "SELECT id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate IS NULL)
                                        OR (first_id = '$chat_id' and second_rate = true and first_rate IS NULL)";
            $resultLikeQueue = $mysqli->query($sqlLikeQueue);
            $countLikes = $resultLikeQueue->num_rows;
            $sqlMatches = "SELECT id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate = true)
                                    OR (first_id = '$chat_id' and second_rate = true and first_rate = true)";
            $resultMatches = $mysqli->query($sqlMatches);
            $countMatches = $resultMatches->num_rows;
            $sqlStatusTest = "SELECT test_step FROM users WHERE chat_id = '$chat_id'";
            $resultStatusTest = $mysqli->query($sqlStatusTest);
            $statusTest = $resultStatusTest->fetch_assoc();
            if ($statusTest ['test_step'] == 10) {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "message_id" => $message_id['message_id'],
                    "text" => 'Меню анкеты:',
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Soul Mate тест: ✅',
                                    'callback_data' => '/soulmatetest',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Редактировать мою анкету',
                                    'callback_data' => '/register',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Показать мою анкету',
                                    'callback_data' => '/showprofile',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'В главное меню',
                                    'callback_data' => '/combacktostartmatches',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
            else {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "message_id" => $message_id['message_id'],
                    "text" => 'Меню анкеты:',
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Soul Mate тест: ✖️',
                                    'callback_data' => '/soulmatetest',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Редактировать мою анкету',
                                    'callback_data' => '/register',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Показать мою анкету',
                                    'callback_data' => '/showprofile',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'В главное меню',
                                    'callback_data' => '/combacktostartmatches',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
		case 3:
			$getQuery = array(
				"chat_id" => $chat_id,
				"message_id" => $message_id['message_id'],
                "text" => 'Главное меню:',
				'disable_notification' => true,
				'reply_markup' => json_encode(array(
					'inline_keyboard' => array(
						array(
							array(
								'text' => 'Поиск',
								'callback_data' => '/startmatch',
							),
							array(
								'text' => 'Фильтр',
								'callback_data' => '/filter',
							),
						),
						array(
							array(
								'text' => 'Пары',
								'callback_data' => '/matchmenu',
							),
						),
						array(
							array(
								'text' => 'Моя анкета',
								'callback_data' => '/myprofilemenu',
							),
						),
					),
				)),
			);
			break;
    }
    $ch = curl_init("https://api.telegram.org/bot". $token ."/editMessageText?" . http_build_query($getQuery));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
    return;
}
//Функция отправки сообщения
function sendTelegramMessage($token, $chat_id, $text, $reg_step, $mysqli) {
    $getQuery = [];
    switch ($reg_step) {
        case 0:
            $getQuery = array(
                "chat_id" 	=> $chat_id,
                "text" => $text,
            );
            break;
        case 1:
            $sqlСheckReg = "SELECT * FROM users WHERE chat_id = '$chat_id'";
            $result = $mysqli->query($sqlСheckReg);
            if ($result->num_rows == 0) {
                $getQuery = array(
                    "chat_id" 	=> $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                'text' => 'Зарегестрироваться',
                                'callback_data'=>'/register',
                                ),
                            )),
                        )),

                );
                break;
            }
            else {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'remove_keyboard' => true,
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Поиск',
                                    'callback_data' => '/startmatch',
                                ),
                                array(
                                    'text' => 'Фильтр',
                                    'callback_data' => '/filter',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Пары',
                                    'callback_data' => '/matchmenu',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Моя анкета',
                                    'callback_data' => '/myprofilemenu',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
        case 2:
            $sqlFilter = "SELECT filter_location, favorite_gender, favorite_age_min, favorite_age_max, show_flag FROM users WHERE chat_id = '$chat_id'";
            $resultFilter = $mysqli->query($sqlFilter);
            $filter = $resultFilter->fetch_assoc();
            if ($filter['filter_location'] == 'global') {
              $filter_location = 'без ограничений';
            }
            elseif ($filter['filter_location'] == 'local') {
              $filter_location = 'по городу';
            }
            if ($filter['show_flag'] == true) {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'remove_keyboard' => true,
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Расстояние поиска: '.$filter_location,
                                    'callback_data' => '/distance',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Возраст: '.$filter['favorite_age_min'].'-'.$filter['favorite_age_max'],
                                    'callback_data' => '/age',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Кого вы ищете: '.$filter['favorite_gender'],
                                    'callback_data' => '/favorite_gender',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Продолжить просмотр анкет...',
                                    'callback_data' => '/combacktostartmatches',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
            else {
                $getQuery = array(
                    "chat_id" => $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'remove_keyboard' => true,
                    'reply_markup' => json_encode(array(
                        'inline_keyboard' => array(
                            array(
                                array(
                                    'text' => 'Расстояние поиска: '.$filter_location,
                                    'callback_data' => '/distance',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Возраст: '.$filter['favorite_age_min'].'-'.$filter['favorite_age_max'],
                                    'callback_data' => '/age',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'Кого вы ищете: '.$filter['favorite_gender'],
                                    'callback_data' => '/favorite_gender',
                                ),
                            ),
                            array(
                                array(
                                    'text' => 'В главное меню',
                                    'callback_data' => '/combacktostartmenu',
                                ),
                            ),
                        ),
                    )),
                );
                break;
            }
        case 3:
            $getQuery = array(
                "chat_id" 	=> $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'remove_keyboard' => true,
                'one_time_keyboard' => true,
                'reply_markup' => json_encode(array(
                    'keyboard' => array(
                        array(
                            array(
                            'text' => 'Мужской',
                            ),
                            array(
                            'text' => 'Женский',
                            ),
                        )),
                        'resize_keyboard' => TRUE,
                    )),
            );
            break;
        case 4:
            $getQuery = array(
                "chat_id" 	=> $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'remove_keyboard' => true,
                'reply_markup' => json_encode(array(
                    'keyboard' => array(
                        array(
                            array(
                            'text' => 'Парни',
                            ),
                            array(
                            'text' => 'Девушки',
                            ),
                            array(
                                'text' => 'Все',
                            ),
                        )),
                        'resize_keyboard' => TRUE,
                    )),
            );
            break;
        case 4.1:
            $getQuery = array(
                "chat_id" 	=> $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'remove_keyboard' => true,
                'reply_markup' => json_encode(array(
                    'keyboard' => array(
                        array(
                            array(
                            'text' => 'Парни',
                            ),
                            array(
                            'text' => 'Девушки',
                            ),
                            array(
                                'text' => 'Все',
                            ),
                        )),
                        'one_time_keyboard' => TRUE,
                        'resize_keyboard' => TRUE,
                    )),
            );
            break;
        case 5:
            $getQuery = array(
                "chat_id" => $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'reply_markup' => json_encode(array(
                    'keyboard' => array(
                        array(
                            array(
                                'text' => 'Отправить геопозицию',
                                'request_location' => true,
                            ),
                        ),
                    ),
                    'one_time_keyboard' => true,
                    'resize_keyboard' => true,
                )),
            );
            break;
      case 5.1:
        $getQuery = array(
            "chat_id" => $chat_id,
            "text" => $text,
            'disable_notification' => true,
            'reply_markup' => json_encode(array(
                'keyboard' => array(
                    array(
                        array(
                            'text' => 'Пропустить',
                        ),
                    ),
                ),
                'one_time_keyboard' => true,
                'resize_keyboard' => true,
            )),
        );
        break;
        case 6:
          $sqlStatusTest = "SELECT test_step FROM users WHERE chat_id = '$chat_id'";
          $resultStatusTest = $mysqli->query($sqlStatusTest);
          $statusTest = $resultStatusTest->fetch_assoc();
          if ($statusTest ['test_step'] == 10) {
              $getQuery = array(
                  "chat_id" => $chat_id,
                  "text" => $text,
                  'disable_notification' => true,
                  'reply_markup' => json_encode(array(
                      'inline_keyboard' => array(
                          array(
                              array(
                                  'text' => 'Soul Mate тест: ✅',
                                  'callback_data' => '/soulmatetest',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'Редактировать мою анкету',
                                  'callback_data' => '/register',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'Показать мою анкету',
                                  'callback_data' => '/showprofile',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'В главное меню',
                                  'callback_data' => '/combacktostartmatches',
                              ),
                          ),
                      ),
                  )),
              );
              break;
          }
          else {
              $getQuery = array(
                  "chat_id" => $chat_id,
                  "text" => $text,
                  'disable_notification' => true,
                  'reply_markup' => json_encode(array(
                      'inline_keyboard' => array(
                          array(
                              array(
                                  'text' => 'Soul Mate тест: ✖️',
                                  'callback_data' => '/soulmatetest',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'Редактировать мою анкету',
                                  'callback_data' => '/register',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'Показать мою анкету',
                                  'callback_data' => '/showprofile',
                              ),
                          ),
                          array(
                              array(
                                  'text' => 'В главное меню',
                                  'callback_data' => '/combacktostartmatches',
                              ),
                          ),
                      ),
                  )),
              );
              break;
          }
        case 7:
            $sqlLikeQueue = "SELECT id FROM rate WHERE (second_id = '$chat_id' and first_rate = true and second_rate IS NULL)
                                                    OR (first_id = '$chat_id' and second_rate = true and first_rate IS NULL)";
            $resultLikeQueue = $mysqli->query($sqlLikeQueue);
            $countLikes = $resultLikeQueue->num_rows;
            $sqlMatches = "SELECT id FROM rate WHERE (first_id = '$chat_id' OR second_id = '$chat_id') AND (first_rate = true AND second_rate = true)";
            $resultMatches = $mysqli->query($sqlMatches);
            $countMatches = $resultMatches->num_rows;
            $getQuery = array(
                "chat_id" => $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'reply_markup' => json_encode(array(
                    'inline_keyboard' => array(
                        array(
                            array(
                                'text' => "Ваши пары ($countMatches)",
                                'callback_data' => '/matches',
                            ),
                        ),
                        array(
                            array(
                                'text' => "Анкеты которым вы понравились ($countLikes)",
                                'callback_data' => '/checklike',
                            ),
                        ),
                        array(
                            array(
                                'text' => 'В главное меню',
                                'callback_data' => '/combacktostartmenu',
                            ),
                        ),
                    ),
                )),
            );
            break;
        //Кейс отправки пустой клавиатуры
        case 8:
            $getQuery = array(
                "chat_id" => $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'reply_markup' => json_encode(array(
                    'remove_keyboard' => true,
                    'selective' => false,
                )),
            );
            break;
        case 9:
            $getQuery = array(
                "chat_id" 	=> $chat_id,
                "text" => $text,
                'disable_notification' => true,
                'reply_markup' => json_encode(array(
                    'keyboard' => array(
                        array(
                            array(
                            'text' => 'Завершить регистрацию:',
                            ),
                        )),
                        'one_time_keyboard' => true,
                        'resize_keyboard' => TRUE,
                    )),
            );
            break;
        case 10:
            $sqlFilter = "SELECT show_flag, coming_flag FROM users WHERE chat_id = '$chat_id'";
            $resultFilter = $mysqli->query($sqlFilter);
            $filter = $resultFilter->fetch_assoc();
            if ($filter['show_flag'] == true){
                $getQuery = array(
                    "chat_id" 	=> $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'reply_markup' => json_encode(array(
                        'keyboard' => array(
                            array(
                                array(
                                'text' => '❤️',
                                ),
                                array(
                                'text' => '👎',
                                ),
                                array(
                                'text' => '🛑',
                                ),
                                array(
                                'text' => 'Фильтр',
                                ),
                            )),
                            'resize_keyboard' => TRUE,
                        )),
                );
            }
            elseif ($filter['coming_flag'] == true) {
                $getQuery = array(
                    "chat_id" 	=> $chat_id,
                    "text" => $text,
                    'disable_notification' => true,
                    'reply_markup' => json_encode(array(
                        'keyboard' => array(
                            array(
                                array(
                                'text' => '❤️',
                                ),
                                array(
                                'text' => '👎',
                                ),
                                array(
                                'text' => '↩️',
                                ),
                            )),
                            'resize_keyboard' => TRUE,
                        )),
                );
            }
            break;
        case 11:
          $getQuery = array(
            "chat_id" => $chat_id,
            "text" => $text,
            'remove_keyboard' => true,
            'disable_notification' => true,
            'reply_markup' => json_encode(array(
                'keyboard' => array(
                    // Создаем массив кнопок, каждая в отдельном вложенном массиве
                    array(
                        array('text' => 'Полностью не согласен'),
                    ),
                    array(
                        array('text' => 'Частично не согласен'),
                    ),
                    array(
                        array('text' => 'Не уверен'),
                    ),
                    array(
                        array('text' => 'Частично согласен'),
                    ),
                    array(
                        array('text' => 'Полностью согласен'),
                    ),
                ),
                'resize_keyboard' => true,
            )),
          );
          break;
    }

    $ch = curl_init("https://api.telegram.org/bot". $token ."/sendMessage?" . http_build_query($getQuery));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);

}

//Функция проверки строки на запрещённые символы
function hasBackSlash($str) {
    return strpos($str, "\\") !== false;
}

// Функция измерения дистанции между двумя точками геолокации
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    // Радиус Земли в километрах
    $R = 6371;
    // Переводим координаты из градусов в радианы
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    // Разница между широтами и долготами
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    // Формула гаверсинусов
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    // Расстояние
    $distance = $R * $c;
    return $distance;
}

// Функция получения id файла
function getFileId ($file_id, $chat_id, $image_number, $mysqli) {
    if ($image_number== 'image') {
        $sqlReg = ("UPDATE users SET image_2 = NULL, image_3 = NULL WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlReg);
    }
    if ($image_number== 'image_2') {
        $sqlReg = ("UPDATE users SET image_3 = NULL WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlReg);
    }
    $sqlReg = ("UPDATE users SET $image_number = '$file_id' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlReg);
    return;
}

//Функция расчёта процента совместимости
function kendallTauCompatibility($answers1, $answers2) {
    if (count($answers1) != 5 || count($answers2) != 5) {
        return false; // Проверка на 5 ответов для каждого человека
    }

    $totalDifference = 0;

    for ($i = 0; $i < 5; $i++) {
        $difference = abs($answers1[$i] - $answers2[$i]);
        $totalDifference += $difference;
    }

    $maxDifference = 5 * 4; // Максимальная сумма разностей при полном несогласии

    $compatibility = (1 - ($totalDifference / $maxDifference)) * 100;

    return $compatibility;
}

// Функция показа профиля
function showProfile ($token, $chat_id, $match_id, $mysqli) {
    $sqlProfile = "SELECT * FROM users WHERE chat_id = '$match_id'";
    $result = $mysqli->query($sqlProfile);
    $rowsProfile = $result->fetch_assoc();
    $sqlLocationChatId = "SELECT latitude, longitude, test_step, test_1, test_2, test_3, test_4, test_5 FROM users
                          WHERE chat_id = '$chat_id'";
    $resultLocationChatId = $mysqli->query($sqlLocationChatId);
    $rowLocationChatId = $resultLocationChatId->fetch_assoc();
    if ($match_id == $chat_id) {
      $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city']."\n" . $rowsProfile['description'];
    }
    else {
      if (isset($rowsProfile['latitude']) == true && isset($rowsProfile['longitude']) == true &&
          isset($rowLocationChatId['latitude']) == true && isset($rowLocationChatId['longitude']) == true) {
              $distance = haversineDistance($rowsProfile['latitude'], $rowsProfile['longitude'],$rowLocationChatId['latitude'], $rowLocationChatId['longitude']);
              if ($distance < 1) {
                  $distance = number_format($distance, 3);
                  $distanceString = (string)$distance;
                  $parts = explode(".", $distanceString); // Разбиваем строку по точке
                  $distance = ltrim($parts[1], '0');
          if ($rowsProfile['test_step'] == 10 && $rowLocationChatId['test_step'] == 10) {
                      $answers1 = [$rowsProfile['test_1'], $rowsProfile['test_2'], $rowsProfile['test_3'], $rowsProfile['test_4'], $rowsProfile['test_5'],];
                      $answers2 = [$rowLocationChatId['test_1'], $rowLocationChatId['test_2'], $rowLocationChatId['test_3'], $rowLocationChatId['test_4'], $rowLocationChatId['test_5'],];
            $compatibility = kendallTauCompatibility ($answers1, $answers2);
                      $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] . ' 📍'.$distance. " метров от вас"."\n"."SoulMate: ".$compatibility.'%'."\n" . $rowsProfile['description'];
          }
                  else {
                      $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] . ' 📍'.$distance. " метров от вас.\n" . $rowsProfile['description'];
                  }
              }
              else {
                  if ($rowsProfile['test_step'] == 10 && $rowLocationChatId['test_step'] == 10) {
                      $distance = number_format($distance, 1);
                      $answers1 = [$rowsProfile['test_1'], $rowsProfile['test_2'], $rowsProfile['test_3'], $rowsProfile['test_4'], $rowsProfile['test_5'],];
                      $answers2 = [$rowLocationChatId['test_1'], $rowLocationChatId['test_2'], $rowLocationChatId['test_3'], $rowLocationChatId['test_4'], $rowLocationChatId['test_5'],];
            $compatibility = kendallTauCompatibility ($answers1, $answers2);
                      $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] . ' 📍'.$distance. ' км от вас'."\n".'SoulMate: '.$compatibility.'%'."\n" . $rowsProfile['description'];
          }
                  else {
                  $distance = number_format($distance, 1);
                  $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] . ' 📍'.$distance. ' км от вас'."\n". $rowsProfile['description'];
                  }
              }

      }
      else {
          if ($rowsProfile['test_step'] == 10 && $rowLocationChatId['test_step'] == 10) {
              $answers1 = [$rowsProfile['test_1'], $rowsProfile['test_2'], $rowsProfile['test_3'], $rowsProfile['test_4'], $rowsProfile['test_5'],];
              $answers2 = [$rowLocationChatId['test_1'], $rowLocationChatId['test_2'], $rowLocationChatId['test_3'], $rowLocationChatId['test_4'], $rowLocationChatId['test_5'],];
              $compatibility = kendallTauCompatibility ($answers1, $answers2);
              $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] . "\n".'SoulMate: '.$compatibility.'%'."\n" . $rowsProfile['description'];
          }
          else {
              $caption = $rowsProfile['name'] . ', ' . $rowsProfile['age'] . ', ' . $rowsProfile['city'] ."\n". $rowsProfile['description'];
          }
      }
    }
    $j = 0;
    for ($i = 0; $i < 3; $i++) {
        $imageColumn = 'image';
        if ($i == 1) {
            $imageColumn = 'image_2';
        } elseif ($i == 2) {
            $imageColumn = 'image_3';
        }
        if (isset($rowsProfile[$imageColumn])) {
            $j = $i;
        }
    }
    //Одна фотография
    if ($j==0) {
      if ($rowsProfile['video_1'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ]
          ])
        ];
      }
      else {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ]
          ])
        ];
      }
    }
    // Две фотографии
    if ($j==1) {
      if ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == false) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ]
          ])
        ];
      }
      elseif ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ]
          ])
        ];
      }
      elseif ($rowsProfile['video_1'] == false && $rowsProfile['video_2'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ]
          ])
        ];
      }
      else {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ]
          ])
        ];
      }
    }
    //Три фотографии
    if ($j==2) {
      if ($rowsProfile['video_1'] == false && $rowsProfile['video_2'] == false && $rowsProfile['video_3'] == false) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'photo', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == false && $rowsProfile['video_2'] == false && $rowsProfile['video_3'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'video', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == false && $rowsProfile['video_2'] == true && $rowsProfile['video_3'] == false) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'photo', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == false && $rowsProfile['video_2'] == true && $rowsProfile['video_3'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'photo', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'video', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == false && $rowsProfile['video_3'] == false) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'photo', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == false && $rowsProfile['video_3'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'photo', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'video', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == true && $rowsProfile['video_3'] == false) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'photo', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      } elseif ($rowsProfile['video_1'] == true && $rowsProfile['video_2'] == true && $rowsProfile['video_3'] == true) {
        $arrayQuery = [
          'chat_id' => $chat_id,
          'disable_notification' => true,
          'reply_markup' => null,
          'media' => json_encode([
          ['type' => 'video', 'media' => $rowsProfile['image'], 'caption' => $caption ],
          ['type' => 'video', 'media' => $rowsProfile['image_2'] ],
          ['type' => 'video', 'media' => $rowsProfile['image_3'] ]
          ])
        ];
      }
    }
    $ch = curl_init('https://api.telegram.org/bot'. $token .'/sendMediaGroup');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $arrayQuery);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_exec($ch);
    curl_close($ch);
}

//Далее идут функции запоминания этапа регистрации
function registerStep_1 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Введите свой возраст', 0, $mysqli);
    $reg_step = 1;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_2 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Введите своё имя', 0, $mysqli);
    $reg_step = 2;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_3 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Введите свой пол', 3, $mysqli);
    $reg_step = 3;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_4 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Кто тебе интересен?', 4, $mysqli);
    $reg_step = 4;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_5 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Откуда ты?', 5, $mysqli);
    $reg_step = 5;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_6 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Расскажи о себе', 5.1, $mysqli);
    $reg_step = 6;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_7 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Отправь своё фото или видео', 0, $mysqli);
    $reg_step = 7;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_8 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы добавли 1/3 фото', 9, $mysqli);
    $reg_step = 8;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function registerStep_9 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы добавли 2/3 фото', 9, $mysqli);
    $reg_step = 9;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  registerFinish ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Регистрация завершена успешно!', 0, $mysqli);
    $reg_step = 10;
    $sql = ("UPDATE users SET reg_step = '$reg_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  testStep_1 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы предпочитаете проводить время с друзьями и общаться в больших группах.', 11, $mysqli);
    $test_step = 1;
    $sql = ("UPDATE users SET test_step = '$test_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  testStep_2 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы легко примиряетесь с другими, даже если у вас разногласия.', 11, $mysqli);
    $test_step = 2;
    $sql = ("UPDATE users SET test_step = '$test_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  testStep_3 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы часто составляете списки задач и придерживаетесь им.', 11, $mysqli);
    $test_step = 3;
    $sql = ("UPDATE users SET test_step = '$test_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  testStep_4 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы часто бываете подвержены стрессу или тревожности.', 11, $mysqli);
    $test_step = 4;
    $sql = ("UPDATE users SET test_step = '$test_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function  testStep_5 ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы часто исследуете новые идеи, искусство и культуры.', 11, $mysqli);
    $test_step = 5;
    $sql = ("UPDATE users SET test_step = '$test_step' WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
    return;
}

function testFinish ($token, $chat_id, $mysqli) {
    sendTelegramMessage($token, $chat_id, 'Вы успешно прошли тест!', 8, $mysqli);
    $test_step = 10;
    $sql = ("UPDATE users SET test_step = '$test_step', test_flag = false, my_profile_menu_flag = true WHERE chat_id = '$chat_id'");
    $mysqli->query($sql);
	sendTelegramMessage($token, $chat_id, 'Меню анкеты:', 6, $mysqli);
    return;
}

//Функции обработки ответов на тест
function responseProcessingTest_1 ($token, $chat_id, $text, $mysqli) {
    switch ($text) {
        case 'Полностью не согласен':
            $text = 1;
            break;
        case 'Частично не согласен':
            $text = 2;
            break;
        case 'Не уверен':
            $text = 3;
            break;
        case 'Частично согласен':
            $text = 4;
            break;
        case 'Полностью согласен':
            $text = 5;
            break;
        default:
            sendTelegramMessage($token, $chat_id, "Неверный вариант ответа", 0 , $mysqli);
            return;
    }
    $sqlTest = ("UPDATE users SET test_1 = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlTest);
    testStep_2 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingTest_2 ($token, $chat_id, $text, $mysqli) {
    switch ($text) {
        case 'Полностью не согласен':
            $text = 1;
            break;
        case 'Частично не согласен':
            $text = 2;
            break;
        case 'Не уверен':
            $text = 3;
            break;
        case 'Частично согласен':
            $text = 4;
            break;
        case 'Полностью согласен':
            $text = 5;
            break;
        default:
            sendTelegramMessage($token, $chat_id, "Неверный вариант ответа", 0 , $mysqli);
            return;
    }
    $sqlTest = ("UPDATE users SET test_2 = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlTest);
    testStep_3 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingTest_3 ($token, $chat_id, $text, $mysqli) {
    switch ($text) {
        case 'Полностью не согласен':
            $text = 1;
            break;
        case 'Частично не согласен':
            $text = 2;
            break;
        case 'Не уверен':
            $text = 3;
            break;
        case 'Частично согласен':
            $text = 4;
            break;
        case 'Полностью согласен':
            $text = 5;
            break;
        default:
            sendTelegramMessage($token, $chat_id, "Неверный вариант ответа", 0 , $mysqli);
            return;
    }
    $sqlTest = ("UPDATE users SET test_3 = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlTest);
    testStep_4 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingTest_4 ($token, $chat_id, $text, $mysqli) {
    switch ($text) {
        case 'Полностью не согласен':
            $text = 1;
            break;
        case 'Частично не согласен':
            $text = 2;
            break;
        case 'Не уверен':
            $text = 3;
            break;
        case 'Частично согласен':
            $text = 4;
            break;
        case 'Полностью согласен':
            $text = 5;
            break;
        default:
            sendTelegramMessage($token, $chat_id, "Неверный вариант ответа", 0 , $mysqli);
            return;
    }
    $sqlTest = ("UPDATE users SET test_4 = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlTest);
    testStep_5 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingTest_5 ($token, $chat_id, $text, $mysqli) {
    switch ($text) {
        case 'Полностью не согласен':
            $text = 1;
            break;
        case 'Частично не согласен':
            $text = 2;
            break;
        case 'Не уверен':
            $text = 3;
            break;
        case 'Частично согласен':
            $text = 4;
            break;
        case 'Полностью согласен':
            $text = 5;
            break;
        default:
            sendTelegramMessage($token, $chat_id, "Неверный вариант ответа", 0 , $mysqli);
            return;
    }
    $sqlTest = ("UPDATE users SET test_5 = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlTest);
    testFinish ($token, $chat_id, $mysqli);
    return;
}

//Далее идут функции обработки ответов на вопросы регистрации
function responseProcessingAge ($token, $chat_id, $text, $mysqli) {
	if (ctype_digit($text) && $text < 100) {
		if ($text >= 18) {
			$sqlReg = ("UPDATE users SET age = '$text' WHERE chat_id = '$chat_id'");
			$mysqli->query($sqlReg);
            registerStep_2 ($token, $chat_id, $mysqli);
            return;
		}
		else {
			sendTelegramMessage($token, $chat_id, 'Извините, но бот не доступен для лиц младше 18 лет', 0, $mysqli);
			return;
		}
	}
	else {
		sendTelegramMessage($token, $chat_id, 'Введите корректное значение', 0, $mysqli);
		return;
	}
}

function responseProcessingName ($token, $chat_id, $text, $mysqli) {
    if (strlen($text)>50) {
        sendTelegramMessage($token, $chat_id, 'Слишком длинное имя', 0, $mysqli);
        return;
    }
    $sqlReg = ("UPDATE users SET name = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlReg);
    registerStep_3 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingGender ($token, $chat_id, $text, $mysqli) {
    if ($text == 'Мужской' || $text == 'Женский') {
        $sqlReg = ("UPDATE users SET gender = '$text' WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlReg);
        registerStep_4 ($token, $chat_id, $mysqli);
        return;
    }
    else {
        sendTelegramMessage($token, $chat_id, 'Некорректный ответ', 3, $mysqli);
        return;
    }
}

function responseProcessingFavoriteGender ($token, $chat_id, $text, $mysqli) {
    if ($text == 'Парни' || $text == 'Девушки' || $text == 'Все') {
        if ($text == 'Парни') {
            $sqlReg = ("UPDATE users SET favorite_gender = 'Мужской' WHERE chat_id = '$chat_id'");
        }
        elseif ($text == 'Девушки') {
            $sqlReg = ("UPDATE users SET favorite_gender = 'Женский' WHERE chat_id = '$chat_id'");
        }
        elseif ($text == 'Все') {
            $sqlReg = ("UPDATE users SET favorite_gender = 'Все' WHERE chat_id = '$chat_id'");
        }
        $mysqli->query($sqlReg);
        registerStep_5 ($token, $chat_id, $mysqli);
        return;
    }
    else {
        sendTelegramMessage($token, $chat_id, 'Некорректный ответ', 4, $mysqli);
        return;
    }
}

function responseProcessingCity ($token, $chat_id, $text, $location, $mysqli) {
    $sqlUserCity = "SELECT city FROM users WHERE chat_id = '$chat_id'";
    $resultCity = $mysqli->query($sqlUserCity);
    $row = $resultCity->fetch_assoc();
    $cityResult = $row['city'];
    if (isset($location)) { // Если пользователь отправил геолокацию
        $sqlReg = "UPDATE users SET latitude = '{$location['latitude']}', longitude = '{$location['longitude']}' WHERE chat_id = '$chat_id'";
        $mysqli->query($sqlReg);
        $sqlLocCities = "SELECT city, latitude, longitude FROM cities"; // Берём список существующих городов
        $result = $mysqli->query($sqlLocCities);
        $maxDistance = 100000; // Максимальное расстояние для нахождение ближайшего города
        if ($result) {
            while ($rowsLocCities = $result->fetch_assoc()) {
                // Расчитываем расстояние между точкой и городом
                $distance = haversineDistance($location['latitude'], $location['longitude'], $rowsLocCities['latitude'], $rowsLocCities['longitude']);
                if ($distance < $maxDistance) { //Нахождение ближайшего города к точке
                    $maxDistance = $distance;
                    $city = $rowsLocCities['city'];
                }
            }
            if ($resultCity) {
                $sqlDeleteCity = ("UPDATE cities SET number_users = number_users - 1 WHERE city = '$cityResult'");
                $mysqli->query($sqlDeleteCity);
            }
            $sqlReg = ("UPDATE users SET city = '$city' WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlReg);
            $sqlCity = ("UPDATE cities SET number_users = number_users + 1 WHERE city = '$city'");
            $mysqli->query($sqlCity);
            sendTelegramMessage ($token, $chat_id, "Ваша локация: $city", 8, $mysqli);
            registerStep_6 ($token, $chat_id, $mysqli);
            return;
        }
    }
    else { //Определение города по введенному названию
      if (strlen($text) > 50) {
        sendTelegramMessage($token, $chat_id, 'Слишком длинное название города', 0, $mysqli);
        return;
      }
    $sqlCities = "SELECT city FROM cities WHERE city LIKE '$text' ORDER BY number_users DESC"; // Используем LIKE для игнорирования регистра
    $resultCities = $mysqli->query($sqlCities);
    $сityRow = $resultCities->fetch_assoc();
    $сity = $сityRow['city'];
    if ($resultCities->num_rows != 0) {
        $sqlCityUserCheck = "SELECT city FROM users WHERE chat_id = '$chat_id'";
        $resultCityUserCheck = $mysqli->query($sqlCityUserCheck);
        $rowUserCity = $resultCityUserCheck->fetch_assoc();

        if (isset($rowUserCity)) {
            $cityResult = $rowUserCity['city'];
            $sqlCity = "UPDATE cities SET number_users = number_users - 1 WHERE city = '$cityResult'";
            $mysqli->query($sqlCity);
            $sqlCity = "UPDATE cities SET number_users = number_users + 1 WHERE city = '$сity'";
            $mysqli->query($sqlCity);
        } else {
            $sqlCity = "UPDATE cities SET number_users = number_users + 1 WHERE city = '$сity'";
            $mysqli->query($sqlCity);
        }

        $sqlReg = "UPDATE users SET city = '$сity' WHERE chat_id = '$chat_id'";
        $mysqli->query($sqlReg);
        registerStep_6($token, $chat_id, $mysqli);
        return;
    } else {
        sendTelegramMessage($token, $chat_id, 'Я не могу найти такой город', 0, $mysqli);
        return;
    }
    }
}

function responseProcessingCaption ($token, $chat_id, $text, $mysqli) {
    if ($text == 'Пропустить') {
      registerStep_7 ($token, $chat_id, $mysqli);
      return;
    }
    if (strlen($text)>1800) {
        sendTelegramMessage($token, $chat_id, 'Слишком длинное описание', 0, $mysqli);
        return;
    }
    $sqlReg = ("UPDATE users SET description = '$text' WHERE chat_id = '$chat_id'");
    $mysqli->query($sqlReg);
    registerStep_7 ($token, $chat_id, $mysqli);
    return;
}

function responseProcessingPhoto_1 ($token, $chat_id, $file_id, $video_id, $mysqli) {
  if (isset($file_id) || isset($video_id)) {
      if (isset($video_id)) {
        $sqlReg = ("UPDATE users SET video_1 = true WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlReg);
        $file_id = $video_id;
      }
      $image_number = 'image';
      getFileId ($file_id, $chat_id, $image_number, $mysqli);
      registerStep_8 ($token, $chat_id, $mysqli);
      return;
  }
  else {
      sendTelegramMessage($token, $chat_id, 'Отправь своё фото или видео', 0, $mysqli);
      return;
  }
}

function responseProcessingPhoto_2 ($token, $chat_id, $text, $file_id, $video_id, $mysqli) {
    if (isset($file_id) || isset($video_id)) {
        if (isset($video_id)) {
          $sqlReg = ("UPDATE users SET video_2 = true WHERE chat_id = '$chat_id'");
          $mysqli->query($sqlReg);
          $file_id = $video_id;
        }
        else {
          $sqlReg = ("UPDATE users SET video_2 = false WHERE chat_id = '$chat_id'");
          $mysqli->query($sqlReg);
        }
        $image_number = 'image_2';
        getFileId ($file_id, $chat_id, $image_number, $mysqli);
        registerStep_9 ($token, $chat_id, $mysqli);
        return;
    }
    elseif ($text == '/ready'|| $text == 'Завершить регистрацию:') {
        $sqlCountVideo = ("SELECT video_1 FROM users WHERE chat_id = '$chat_id'");
        $resultCountVideo = $mysqli->query($sqlCountVideo);
        $rowCountVideo = $resultCountVideo->fetch_assoc();
        if ($rowCountVideo ['video_1'] == true) {
            sendTelegramMessage($token, $chat_id, 'Необходимо добавить хотя бы одно фото', 0, $mysqli);
            return;
        }
        registerFinish ($token, $chat_id, $mysqli);
        showProfile ($token, $chat_id, $chat_id, $mysqli);
        $sqlFilter = ("UPDATE users SET my_profile_menu_flag = true WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlFilter);
        sendTelegramMessage($token, $chat_id, 'Главное меню:', 6, $mysqli);
        return;
    }
    else {
        sendTelegramMessage($token, $chat_id, 'Отправь своё фото или видео', 0, $mysqli);
        return;
    }
}

function responseProcessingPhoto_3 ($token, $chat_id, $text, $file_id, $video_id, $mysqli) {
  if (isset($file_id) || isset($video_id)) {
        if (isset($video_id)) {
          $sqlCountVideo = ("SELECT video_1, video_2 FROM users WHERE chat_id = '$chat_id'");
          $resultCountVideo = $mysqli->query($sqlCountVideo);
          $rowCountVideo = $resultCountVideo->fetch_assoc();
          if ($rowCountVideo ['video_1'] == true && $rowCountVideo ['video_2'] == true) {
              sendTelegramMessage($token, $chat_id, 'Необходимо добавить хотя бы одно фото', 0, $mysqli);
              return;
          }
          $sqlReg = ("UPDATE users SET video_3 = true WHERE chat_id = '$chat_id'");
          $mysqli->query($sqlReg);
          $file_id = $video_id;
        }
        else {
          $sqlReg = ("UPDATE users SET video_3 = false WHERE chat_id = '$chat_id'");
          $mysqli->query($sqlReg);
        }
        $image_number = 'image_3';
        getFileId ($file_id, $chat_id, $image_number, $mysqli);
        registerFinish ($token, $chat_id, $mysqli);
        showProfile ($token, $chat_id, $chat_id, $mysqli);
        $sqlFilter = ("UPDATE users SET my_profile_menu_flag = true WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlFilter);
        sendTelegramMessage($token, $chat_id, 'Главное меню:', 6, $mysqli);
        return;
    }
    elseif ($text == '/ready'||$text == 'Завершить регистрацию:') {
        $sqlCountVideo = ("SELECT video_1, video_2 FROM users WHERE chat_id = '$chat_id'");
        $resultCountVideo = $mysqli->query($sqlCountVideo);
        $rowCountVideo = $resultCountVideo->fetch_assoc();
        if ($rowCountVideo ['video_1'] == true && $rowCountVideo ['video_2'] == true) {
            sendTelegramMessage($token, $chat_id, 'Необходимо добавить хотя бы одно фото', 0, $mysqli);
            return;
        }
        registerFinish ($token, $chat_id, $mysqli);
        showProfile ($token, $chat_id, $chat_id, $mysqli);
        $sqlFilter = ("UPDATE users SET my_profile_menu_flag = true WHERE chat_id = '$chat_id'");
        $mysqli->query($sqlFilter);
        sendTelegramMessage($token, $chat_id, 'Главное меню', 6, $mysqli);
        return;
    }
    else {
        sendTelegramMessage($token, $chat_id, 'Отправь своё фото или видео', 0, $mysqli);
        return;
    }
}

//Функция проверки состояния регистрации (выполняется при каждом запуске скрипта)
function registerCheck ($token, $chat_id, $username, $text, $location, $file_id, $video_id, $mysqli) {
    $sql = "SELECT reg_step, test_step FROM users WHERE chat_id = '$chat_id'";
    $result = $mysqli->query($sql);
    if ($result->num_rows != 0) {
        $row = $result->fetch_assoc();
        switch ($row["reg_step"]) {
			// Ответ на вопрос про возраст
            case '1':
                responseProcessingAge ($token, $chat_id, $text, $mysqli);
                return;
			// Ответ на вопрос про имя
            case '2':
                responseProcessingName ($token, $chat_id, $text, $mysqli);
                return;
			// Ответ на вопрос про пол
            case '3':
                responseProcessingGender ($token, $chat_id, $text, $mysqli);
                return;
			// Ответ на вопрос про искомый пол
            case '4':
                responseProcessingFavoriteGender ($token, $chat_id, $text, $mysqli);
                return;
			// Ответ на вопрос про локацию
            case '5':
                responseProcessingCity ($token, $chat_id, $text, $location, $mysqli);
                return;
			// Ответ на вопрос про описание
            case '6':
                responseProcessingCaption ($token, $chat_id, $text, $mysqli);
                return;
			//Обработка 1 фотографии
            case '7':
                responseProcessingPhoto_1 ($token, $chat_id, $file_id, $video_id, $mysqli);
                return;
			//Обработка 2 фотографии
            case '8':
                responseProcessingPhoto_2 ($token, $chat_id, $text, $file_id, $video_id, $mysqli);

                return;
			//Обработка 3 фотографии
            case '9':
                responseProcessingPhoto_3 ($token, $chat_id, $text, $file_id, $video_id, $mysqli);
                return;
			//Если регистрация завершена, вызывается функция обработки команд
            case '10':
                switch ($row["test_step"]) {
                    case '1':
                        responseProcessingTest_1 ($token, $chat_id, $text, $mysqli);
                        return;
                    case '2':
                        responseProcessingTest_2 ($token, $chat_id, $text, $mysqli);
                        return;
                    case '3':
                        responseProcessingTest_3 ($token, $chat_id, $text, $mysqli);
                        return;
                    case '4':
                        responseProcessingTest_4 ($token, $chat_id, $text, $mysqli);
                        return;
                    case '5':
                        responseProcessingTest_5 ($token, $chat_id, $text, $mysqli);
                        return;
                    case '10':
                        processSwitchCommand($token, $chat_id, $username, $text, $mysqli);
                        return;
                    default:
                        processSwitchCommand($token, $chat_id, $username, $text, $mysqli);
                        return;
                }
        }
    }
    else {
        processSwitchCommand($token, $chat_id, $username, $text, $mysqli);
        return;
    }
}


// Функция обработки команд от пользователя
function processSwitchCommand($token, $chat_id, $username, $text, $mysqli) {
    $sqlShowFlag = "SELECT main_menu_flag, show_flag, coming_flag, filter_flag, filter_age_flag, filter_gender_flag, my_profile_menu_flag, match_menu_flag FROM users WHERE chat_id = '$chat_id'";
    $resultSqlShowFlag = $mysqli->query($sqlShowFlag);
    $showFlag = $resultSqlShowFlag->fetch_assoc();
    //Главное меню
    if ($showFlag ['main_menu_flag'] == true || isset($showFlag ['main_menu_flag']) == false) {
        if ($text == '/start') {
			      deleteMenu($chat_id, $token, $mysqli);
            $sqlFilter = ("UPDATE users SET main_menu_flag = true WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            sendTelegramMessage($token, $chat_id, 'Главное меню:', 1, $mysqli);
            return;
        }
        elseif ($text == '/filter' || $text == 'Фильтр') {
            $sqlFilter = ("UPDATE users SET filter_flag = true, main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            editTelegramMessage($token, $chat_id, 0, $mysqli);
            return;
        }
        elseif ($text == '/matchmenu' || $text == 'Пары') {
            $sqlFilter = ("UPDATE users SET match_menu_flag = true, main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            editTelegramMessage($token, $chat_id, 1, $mysqli);
            return;
        }
        elseif ($text == '/myprofilemenu' || $text == 'Моя анкета') {
            $sqlFilter = ("UPDATE users SET my_profile_menu_flag = true, main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            editTelegramMessage($token, $chat_id, 2, $mysqli);
            return;
        }
        elseif ($text == '/register' || $text == 'Зарегестрироваться' || $text == 'Редактировать мою анкету') {
            $sqlFilter = ("UPDATE users SET main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            deleteMenu($chat_id, $token, $mysqli);
            $sqlCheckReg = "SELECT * FROM users WHERE chat_id = '$chat_id'";
            $resultCheck = $mysqli->query($sqlCheckReg);
            if ($resultCheck->num_rows == 0) {
                $sqlNewReg = "INSERT INTO users (chat_id, username, show_flag, coming_flag, filter_flag, filter_location,
                                                 favorite_age_min, favorite_age_max, filter_age_flag, filter_gender_flag, main_menu_flag, match_menu_flag, my_profile_menu_flag)
                                        VALUES ('$chat_id', '$username', 'false', 'false', 'false', 'local', '18', '25', 'false', 'false', 'false', 'false', 'false')";
                $mysqli->query($sqlNewReg);
            }
            $sqlFilter = ("UPDATE users SET main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            registerStep_1($token, $chat_id, $mysqli);
            return;
        }
        elseif ($text == '/startmatch' || $text == 'Поиск') {
            $sqlFilter = ("UPDATE users SET main_menu_flag = false WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            deleteMenu($chat_id, $token, $mysqli);
            showAlgorithm ($token, $chat_id, $mysqli);
            return;
        }
        else {
            sendTelegramMessage($token, $chat_id, 'Неверная команда', 0, $mysqli);
            return;
        }
    }
    //Меню Пары
    elseif ($showFlag['match_menu_flag'] == true) {
        if ($text == '/matches' || $text == 'Ваши пары') {
            	deleteMenu($chat_id, $token, $mysqli);
            	showMatches ($token, $chat_id, $mysqli);
                return;
            }
        elseif ($text == '/checklike' || $text == 'Анкеты которым вы понравились') {
            $sqlFlag = ("UPDATE users SET match_menu_flag = false  WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFlag);
            deleteMenu($chat_id, $token, $mysqli);
            comingLikes ($token, $chat_id, $mysqli);
            return;
        }
        elseif ($text == '/combacktostartmenu') {
            $sqlFlag = ("UPDATE users SET main_menu_flag = true, match_menu_flag = false  WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFlag);
            editTelegramMessage($token, $chat_id, 3, $mysqli);
            return;
        }
        else {
            sendTelegramMessage ($token, $chat_id, 'Неверная команда', 0, $mysqli);
            return;
        }

    }
    //Меню Моя анкета
    elseif ($showFlag['my_profile_menu_flag'] == true) {
        if ($text == '/showprofile' || $text == 'Показать мою анкету') {
            deleteMenu($chat_id, $token, $mysqli);
            // $sqlCheckReg = "SELECT * FROM users WHERE chat_id = '$chat_id'";
            // $resultCheck = $mysqli->query($sqlCheckReg);
			showProfile ($token, $chat_id, $chat_id, $mysqli);
			sendTelegramMessage ($token, $chat_id, 'Меню анкеты:', 6, $mysqli);
			return;
        }
        elseif ($text == '/soulmatetest' || $text == 'Soul Mate тест') {
            $sqlFilter = ("UPDATE users SET test_flag = true WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            deleteMenu($chat_id, $token, $mysqli);
            testStep_1 ($token, $chat_id, $mysqli);
            return;
        }
        elseif ($text == '/register' || $text == 'Зарегестрироваться' || $text == 'Редактировать мою анкету') {
            deleteMenu($chat_id, $token, $mysqli);
            $sqlCheckReg = "SELECT * FROM users WHERE chat_id = '$chat_id'";
            $resultCheck = $mysqli->query($sqlCheckReg);
            if ($resultCheck->num_rows == 0) {
                $sqlNewReg =
                "INSERT INTO users
                (chat_id, username, description, show_flag, coming_flag, filter_flag, filter_location, favorite_age_min, favorite_age_max, filter_age_flag, filter_gender_flag, test_flag, match_menu_flag, my_profile_menu_flag, main_menu_flag, video_1, video_2, video_3)
                VALUES
                ('$chat_id', '$username', NULL, 'false', 'false', 'false', 'local',
                '18', '25', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'false', 'false')";
                $mysqli->query($sqlNewReg);
            }
            else {
              $sqlNewReg = "UPDATE users SET  username = '$username',
                                              description = NULL,
                                              show_flag = 'false',
                                              coming_flag = 'false',
                                              filter_flag = 'false',
                                              filter_location = 'local',
                                              favorite_age_min = '18',
                                              favorite_age_max = '25',
                                              filter_age_flag = 'false',
                                              filter_gender_flag = 'false',
                                              test_flag = 'false',
                                              match_menu_flag = 'false',
                                              my_profile_menu_flag = 'false',
                                              main_menu_flag = 'false',
                                              video_1 = 'false',
                                              video_2 = 'false',
                                              video_3 = 'false'
                              WHERE chat_id = '$chat_id'";
              $mysqli->query($sqlNewReg);
            }
            registerStep_1($token, $chat_id, $mysqli);
            return;
        }
        elseif ($text == '/combacktostartmatches') {
            $sqlFlag = ("UPDATE users SET main_menu_flag = true, my_profile_menu_flag = false  WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFlag);
            editTelegramMessage($token, $chat_id, 3, $mysqli);
            return;
        }
        else {
            sendTelegramMessage ($token, $chat_id, 'Неверная команда', 0, $mysqli);
            return;
        }
    }
    //Меню Фильтр
    elseif ($showFlag['filter_flag'] == true) {
        if ($showFlag['filter_age_flag'] == true) {
            $delimiter = "-";
            $parts = explode($delimiter, $text);
            if (($parts [0] >= 18 && $parts [0] < 100) && ($parts [1] >= 18 && $parts [1] < 100) && $parts[0] <= $parts[1] ) {
                $sqlSetFavoriteAge = "UPDATE users SET favorite_age_min = '$parts[0]', favorite_age_max = '$parts[1]' WHERE chat_id = '$chat_id'";
                $mysqli->query($sqlSetFavoriteAge);
                $sqlFilter = ("UPDATE users SET filter_age_flag = false WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                sendTelegramMessage ($token, $chat_id, 'Настройки фильтра показа анкет:', 2, $mysqli);
                return;
            }
            else {
                sendTelegramMessage ($token, $chat_id, 'Введите диапазон возраста в формате (Минимальный-Максимальный). Пример: 18-22', 0, $mysqli);
                return;
            }
        }
        elseif ($showFlag['filter_gender_flag'] == true) {
            if ($text == 'Парни' || $text == 'Девушки' || $text == 'Все') {
                $sqlFilter = ("UPDATE users SET filter_gender_flag = false WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                if ($text == 'Парни') {
                    $sqlReg = ("UPDATE users SET favorite_gender = 'Мужской' WHERE chat_id = '$chat_id'");
                }
                elseif ($text == 'Девушки') {
                    $sqlReg = ("UPDATE users SET favorite_gender = 'Женский' WHERE chat_id = '$chat_id'");
                }
                elseif ($text == 'Все') {
                    $sqlReg = ("UPDATE users SET favorite_gender = 'Все' WHERE chat_id = '$chat_id'");
                }
                $mysqli->query($sqlReg);
                sendTelegramMessage ($token, $chat_id, 'Настройки фильтра показа анкет:', 2, $mysqli);
                return;
            }
            else {
                sendTelegramMessage($token, $chat_id, 'Некорректный ответ', 4, $mysqli);
                return;
            }
        }
        elseif ($showFlag['filter_flag'] == true) {
            if ($text == '/distance') {
                $sqlFilter = ("SELECT filter_location FROM users WHERE chat_id = '$chat_id'");
                $result = $mysqli->query($sqlFilter);
                $filter_location = $result->fetch_assoc();
                if ( $filter_location['filter_location'] == 'local') {
                    $sqlFilter = ("UPDATE users SET filter_location = 'global' WHERE chat_id = '$chat_id'");
                    $mysqli->query($sqlFilter);
                }
                elseif ( $filter_location['filter_location'] == 'global') {
                    $sqlFilter = ("UPDATE users SET filter_location = 'local' WHERE chat_id = '$chat_id'");
                    $mysqli->query($sqlFilter);
                }
                editTelegramMessage($token, $chat_id, 0, $mysqli);
                return;
            }
            elseif ($text == '/age') {
                $sqlFilter = ("UPDATE users SET filter_age_flag = true WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                deleteMenu($chat_id, $token, $mysqli);
                sendTelegramMessage ($token, $chat_id, 'Введите диапазон возраста в формате (Минимальный-Максимальный). Пример: 18-22', 0, $mysqli);
                return;
            }
            elseif ($text == '/favorite_gender') {
                $sqlFilter = ("UPDATE users SET filter_gender_flag = true WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                deleteMenu($chat_id, $token, $mysqli);
                sendTelegramMessage ($token, $chat_id, 'Выберите пол котрый вы ищете:', 4.1, $mysqli);
                return;
            }
            elseif ($text == '/combacktostartmatches') {
                $sqlFilter = ("UPDATE users SET filter_flag = false WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                deleteMenu($chat_id, $token, $mysqli);
                showAlgorithm ($token, $chat_id, $mysqli);
                return;
            }
            elseif ($text == '/combacktostartmenu') {
                $sqlFilter = ("UPDATE users SET filter_flag = false, main_menu_flag = true WHERE chat_id = '$chat_id'");
                $mysqli->query($sqlFilter);
                editTelegramMessage($token, $chat_id, 3, $mysqli);
                return;
            }
            else {
                deleteMenu($chat_id, $token, $mysqli);
                sendTelegramMessage ($token, $chat_id, 'Неверная команда', 0, $mysqli);
                sendTelegramMessage ($token, $chat_id, 'Настройки фильтра показа анкет:', 2, $mysqli);
            }
        }

    }
    //Оценка
    elseif ($showFlag['coming_flag'] == true || $showFlag['show_flag'] == true)  {
        $sqlMatchId = "SELECT last_shown_id FROM users WHERE chat_id = '$chat_id'";
        $resultMatchId = $mysqli->query($sqlMatchId);
        $match_id = $resultMatchId->fetch_assoc();
        if ($text == '❤️') {
            if ($showFlag['show_flag'] == true) {
                $sqlLike = ("UPDATE users SET show_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            elseif ($showFlag['coming_flag'] == true) {
                $sqlLike = ("UPDATE users SET coming_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            $mysqli->query($sqlLike);
            $sqlSearchId = "SELECT * FROM rate WHERE (first_id = '$chat_id' AND second_id = {$match_id['last_shown_id']})
                                                  OR (second_id = '$chat_id' AND first_id = {$match_id['last_shown_id']})";
            $resultSqlSearchId = $mysqli->query($sqlSearchId);
            $matchSearchId = $resultSqlSearchId->fetch_assoc();
            // Если строка с NULL NULL
            if (isset($matchSearchId['first_rate']) == false && isset($matchSearchId['second_rate']) == false) {
                if ($matchSearchId['first_id'] == $chat_id) {
                    $sqlSetLike = ("UPDATE rate SET first_rate = TRUE WHERE id = {$matchSearchId['id']}");
                    $mysqli->query($sqlSetLike);
                }
                elseif ($matchSearchId["second_id"] == $chat_id) {
                    $sqlSetLike = ("UPDATE rate SET second_rate = TRUE WHERE id = {$matchSearchId['id']}");
                    $mysqli->query($sqlSetLike);
                }
                sendTelegramMessage($token, $match_id['last_shown_id'], 'Вы кому то понравились. Проверьте раздел Лайки.', 0, $mysqli);
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                elseif ($showFlag['coming_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
            //Если строка с SET NULL
            elseif (isset($matchSearchId['first_rate'])) {
                $sqlSetLike = ("UPDATE rate SET second_rate = TRUE WHERE id = {$matchSearchId['id']}");
                $mysqli->query($sqlSetLike);
                if ($matchSearchId['first_rate'] == 1) {
                    $sqlWhereLike = ("SELECT username FROM users WHERE chat_id = {$match_id['last_shown_id']}");
                    $resultUsernameMatch = $mysqli->query($sqlWhereLike);
                    $usernameMatch = $resultUsernameMatch->fetch_assoc();
                    sendTelegramMessage($token, $chat_id, 'Это взаимно! Начинай общение ➤ @'.$usernameMatch['username'], 0, $mysqli);
					sendTelegramMessage($token, $match_id['last_shown_id'], 'У вас появилась новая взаимная симпатия. Проверьте раздел мэтчей.', 0, $mysqli);
                }
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                if ($showFlag['coming_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
            //Если строка с NULL SET
            elseif (isset($matchSearchId['second_rate'])) {
                $sqlSetLike = ("UPDATE rate SET first_rate = TRUE WHERE id = {$matchSearchId['id']}");
                $mysqli->query($sqlSetLike);
                if ($matchSearchId['second_rate'] == 1) {
                    $sqlWhereLike = ("SELECT username FROM users WHERE chat_id = {$match_id['last_shown_id']}");
                    $resultUsernameMatch = $mysqli->query($sqlWhereLike);
                    $usernameMatch = $resultUsernameMatch->fetch_assoc();
                    sendTelegramMessage($token, $chat_id, 'Это взаимно! Начинай общение ➤ @'.$usernameMatch['username'], 0, $mysqli);
					sendTelegramMessage($token, $match_id['last_shown_id'], 'У вас появилась новая взаимная симпатия. Проверьте раздел мэтчей.', 0, $mysqli);
                }
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                elseif ($showFlag['show_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
        }
        elseif ($text == '👎') {
            if ($showFlag['show_flag'] == true) {
                $sqlLike = ("UPDATE users SET show_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            elseif ($showFlag['coming_flag'] == true) {
                $sqlLike = ("UPDATE users SET coming_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            $mysqli->query($sqlLike);
            $sqlSearchId = "SELECT * FROM rate WHERE (first_id = '$chat_id' AND second_id = {$match_id['last_shown_id']})
                                                  OR (second_id = '$chat_id' AND first_id = {$match_id['last_shown_id']})";
            $resultSqlSearchId = $mysqli->query($sqlSearchId);
            $matchSearchId = $resultSqlSearchId->fetch_assoc();
            // Если строка с NULL NULL
            if (isset($matchSearchId['first_rate']) == false && isset($matchSearchId['second_rate']) == false) {
                if ($matchSearchId['first_id'] == $chat_id) {
                    $sqlSetLike = ("UPDATE rate SET first_rate = FALSE WHERE id = {$matchSearchId['id']}");
                    $mysqli->query($sqlSetLike);
                }
                elseif ($matchSearchId["second_id"] == $chat_id) {
                    $sqlSetLike = ("UPDATE rate SET second_rate = FALSE WHERE id = {$matchSearchId['id']}");
                    $mysqli->query($sqlSetLike);
                }
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                elseif ($showFlag['coming_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
             //Если строка с SET NULL
             elseif (isset($matchSearchId['first_rate'])) {
                $sqlSetLike = ("UPDATE rate SET second_rate = FALSE WHERE id = {$matchSearchId['id']}");
                $mysqli->query($sqlSetLike);
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                elseif ($showFlag['coming_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
              //Если строка с NULL SET
              elseif (isset($matchSearchId['second_rate'])) {
                $sqlSetLike = ("UPDATE rate SET first_rate = FALSE WHERE id = {$matchSearchId['id']}");
                $mysqli->query($sqlSetLike);
                if ($showFlag['show_flag'] == true) {
                    showAlgorithm ($token, $chat_id, $mysqli);
                }
                elseif ($showFlag['coming_flag'] == true) {
                    comingLikes($token, $chat_id, $mysqli);
                }
                return;
            }
        }
        elseif ($text == '🛑') {
            if ($showFlag['show_flag'] == true) {
                $sqlLike = ("UPDATE users SET show_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            elseif ($showFlag['coming_flag'] == true) {
                $sqlLike = ("UPDATE users SET coming_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            $mysqli->query($sqlLike);
            $sqlFilter = ("UPDATE users SET main_menu_flag = true WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            sendTelegramMessage ($token, $chat_id, '🏠', 8, $mysqli);
            sendTelegramMessage ($token, $chat_id, 'Главное меню:', 1, $mysqli);
        }
        elseif ($text == '↩️') {
            if ($showFlag['show_flag'] == true) {
                $sqlLike = ("UPDATE users SET show_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            elseif ($showFlag['coming_flag'] == true) {
                $sqlLike = ("UPDATE users SET coming_flag = FALSE WHERE chat_id = '$chat_id'");
            }
            $sqlFilter = ("UPDATE users SET match_menu_flag = true WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            $mysqli->query($sqlLike);
            sendTelegramMessage ($token, $chat_id, '❤️‍🔥', 8, $mysqli);
            sendTelegramMessage ($token, $chat_id, 'Меню пар:', 7, $mysqli);
        }
        elseif ($text == 'Фильтр') {
            $sqlFilter = ("UPDATE users SET filter_flag = TRUE WHERE chat_id = '$chat_id'");
            $mysqli->query($sqlFilter);
            sendTelegramMessage ($token, $chat_id, 'Настройки фильтра показа анкет:', 2, $mysqli);
        }
        else {
            sendTelegramMessage($token, $chat_id, 'Неверная команда', 0, $mysqli);
            sendTelegramMessage ($token, $chat_id, 'Оцените анкету', 10, $mysqli);
            return;
        }
    }

}

// Вызов функции проверки этапов регистрации
registerCheck ($token, $chat_id, $username, $text, $location, $file_id, $video_id,$mysqli);

$mysqli->close();