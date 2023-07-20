<?php
//TODO
// Написать телеграм бота без использования вебхука ( локально ), используя чистый php без библиотек.
//Внутри бота: при нажатии кнопки /start, информация должна вноситься в базу данных ( username, user_id и т.д )
//После, реализовать кнопку рандомайзер, на которую пользователь будет нажимать и ему будет бот
// присылать рандомные анекдоты из интернета
// ( на кнопке должен присутствовать счётчик кликов от юзера )

// Установка токена вашего бота
$botToken = '6000279511:AAHNb7bmBB4p28tJtx8vqd1qlswDKj8bL-M';

// URL API Telegram
$apiUrl = 'https://api.telegram.org/bot' . $botToken . '/';

// Путь к файлу с последним обработанным обновлением
$lastUpdateFile = 'last_update_id.txt';

// Функция для отправки запросов к API Telegram
function sendTelegramRequest($method, $parameters = []) {
    global $apiUrl;
    $url = $apiUrl . $method . '?' . http_build_query($parameters);
    return file_get_contents($url);
}

// Функция для обновления информации о последнем обработанном обновлении
function updateLastUpdate($updateId) {
    global $lastUpdateFile;
    file_put_contents($lastUpdateFile, $updateId);
}

// Функция для получения информации о последнем обработанном обновлении
function getLastUpdate() {
    global $lastUpdateFile;
    return file_exists($lastUpdateFile) ? intval(file_get_contents($lastUpdateFile)) : 0;
}

// Функция для обработки входящих обновлений
function processUpdates($updates) {
    foreach ($updates as $update) {
        $message = $update['message'] ?? null;
        $callback = $update['callback_query'] ?? null;

        if ($message) {
            handleMessage($message);
        } elseif ($callback) {
            handleCallbackQuery($callback);
        }

        // Обновляем информацию о последнем обработанном обновлении
        updateLastUpdate($update['update_id']);
    }
}

// Функция для обработки входящего сообщения
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $text = $message['text'];

    if ($text === '/start') {
        // Обработка команды /start
        $username = $message['from']['username'];
        $userId = $message['from']['id'];

        // Сохраняем информацию о пользователе в базу данных
        saveUser($username, $userId);

        // Отправляем сообщение с кнопками "Рандомайзер"
        sendStartMessage($chatId);
    } elseif ($text === 'Рандомайзер') {
        // Обработка команды "Рандомайзер"
        sendRandomJoke($chatId);
    }
}

// Функция для обработки входящего callback query (при нажатии кнопки)
function handleCallbackQuery($callback) {
    $chatId = $callback['message']['chat']['id'];
    $userId = $callback['from']['id'];
    $messageId = $callback['message']['message_id'];

    if ($callback['data'] === 'random_joke') {
        // Обработка нажатия кнопки "Рандомайзер"
        sendRandomJoke($chatId);

        // Увеличиваем счётчик кликов от пользователя
        increaseClickCount($userId);

        // Удаляем сообщение с кнопками после обработки
        deleteMessage($chatId, $messageId);
    }
}

// Функция для сохранения информации о пользователе в базу данных
function saveUser($username, $userId) {
    // Ваши настройки подключения к базе данных
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPassword = '';
    $dbName = 'telegram_users';

    // Устанавливаем соединение с базой данных
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

    // Проверяем соединение на ошибки
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // Подготавливаем и выполняем запрос на вставку информации о пользователе
    $sql = "INSERT INTO users (username, user_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $username, $userId);
    $stmt->execute();

    // Закрываем соединение с базой данных
    $stmt->close();
    $conn->close();
}

// Функция для отправки сообщения с кнопками
function sendStartMessage($chatId) {
    $message = 'Привет! Нажми на кнопку "Рандомайзер", чтобы получить рандомный анекдот.';

    // Создаем массив с кнопками
    $keyboard = [
        ['Рандомайзер'],
    ];

    // Кодируем массив с кнопками в JSON
    $replyMarkup = json_encode([
        'keyboard' => $keyboard,
        'one_time_keyboard' => true, // Показываем кнопки только один раз
        'resize_keyboard' => true,   // Масштабируем кнопки по размеру чата
    ]);

    // Отправляем сообщение с кнопками
    sendTelegramRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $message,
        'reply_markup' => $replyMarkup,
    ]);
}

// Функция для отправки рандомного анекдота
function sendRandomJoke($chatId) {
    // URL для получения случайного анекдота с API
    $jokeApiUrl = 'https://api.chucknorris.io/jokes/random';

    // Получаем случайный анекдот из API
    $randomJoke = getRandomJokeFromApi($jokeApiUrl);


    // Отправляем сообщение с анекдотом пользователю
    // Здесь вы можете добавить логику для получения рандомного анекдота из интернета
    // Например, использовать API для получения случайного анекдота
    // Здесь предполагается, что вы имеете переменную $randomJoke с текстом анекдота
    sendTelegramRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $randomJoke,
    ]);
}

function getRandomJokeFromApi($url) {
    // Выполняем HTTP-запрос к API
    $response = file_get_contents($url);

    if ($response) {
        // Преобразуем JSON-ответ в ассоциативный массив PHP
        $data = json_decode($response, true);
        // Проверяем, существует ли поле 'value' с текстом анекдота в массиве
        if (isset($data['value'])) {
            // Возвращаем текст анекдота
            return $data['value'];
        }
    }

    // В случае, если запрос к API не удался или анекдот не был получен, вернем сообщение об ошибке
//    return 'К сожалению, не удалось получить анекдот. Попробуйте позже.';
}

// Функция для увеличения счётчика кликов от пользователя
function increaseClickCount($userId) {
    // Ваши настройки подключения к базе данных
    $dbHost = 'localhost';
    $dbUser = 'root';
    $dbPassword = '';
    $dbName = 'telegram_users';

    // Устанавливаем соединение с базой данных
    $conn = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

    // Проверяем соединение на ошибки
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // Подготавливаем и выполняем запрос на обновление счетчика кликов пользователя
    $sql = "UPDATE users SET click_count = click_count + 1 WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();

    // Закрываем соединение с базой данных
    $stmt->close();
    $conn->close();
}

// Функция для удаления сообщения с кнопками
function deleteMessage($chatId, $messageId) {
    sendTelegramRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
    ]);
}

// Главная функция для обработки обновлений
function handleUpdates() {
    $lastUpdateId = getLastUpdate();
    $updates = getUpdates($lastUpdateId + 1);

    if (isset($updates['result'])) {
        processUpdates($updates['result']);
    }
}

// Функция для отправки уведомления новому пользователю
function sendWelcomeMessage($chatId) {
    $message = 'Добро пожаловать! Напишите команду /start, чтобы начать использование бота.';

    sendTelegramRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $message,
    ]);
}

// Функция для получения обновлений от Telegram API
function getUpdates($offset) {
    return json_decode(sendTelegramRequest('getUpdates', [
        'offset' => $offset,
    ]), true);
}

// Запускаем бота
while (true) {
    handleUpdates();
    sleep(1); // Пауза между обновлениями
}






