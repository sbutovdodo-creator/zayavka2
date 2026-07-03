<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_value(string $value, int $limit = 1200): string
{
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $limit, 'UTF-8');
}

function encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function smtp_read($socket): string
{
    $data = '';

    while (($line = fgets($socket, 515)) !== false) {
        $data .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function smtp_command($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function smtp_send(array $config, string $subject, string $body, string $replyTo = ''): void
{
    $host = (string) $config['smtp_host'];
    $port = (int) $config['smtp_port'];
    $user = (string) $config['smtp_user'];
    $pass = (string) $config['smtp_pass'];
    $from = (string) $config['mail_from'];
    $fromName = (string) $config['mail_from_name'];
    $to = (string) $config['mail_to'];

    $socket = fsockopen('ssl://' . $host, $port, $errno, $errstr, 15);

    if (!$socket) {
        throw new RuntimeException('SMTP connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, 15);

    try {
        $greeting = smtp_read($socket);

        if ((int) substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
        }

        smtp_command($socket, 'EHLO riklab.ru', [250]);
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($user), [334]);
        smtp_command($socket, base64_encode($pass), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $headers = [
            'From: ' . encode_header($fromName) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . encode_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: RIK-LAB site form',
        ];

        if ($replyTo !== '') {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $message = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $message);

        smtp_command($socket, $message . "\r\n.", [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function verify_smartcaptcha(array $config, string $token): bool
{
    if (empty($config['captcha_enabled'])) {
        return true;
    }

    $secret = (string) ($config['captcha_secret'] ?? '');

    if ($secret === '' || $secret === 'PASTE_YANDEX_SMARTCAPTCHA_SERVER_KEY_HERE') {
        throw new RuntimeException('SmartCaptcha secret is not configured.');
    }

    if ($token === '') {
        return false;
    }

    $payload = http_build_query([
        'secret' => $secret,
        'token' => $token,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $host = 'smartcaptcha.yandexcloud.net';
    $socket = fsockopen('ssl://' . $host, 443, $errno, $errstr, 10);

    if (!$socket) {
        throw new RuntimeException('SmartCaptcha connection failed: ' . $errstr);
    }

    stream_set_timeout($socket, 10);

    $request = implode("\r\n", [
        'POST /validate HTTP/1.1',
        'Host: ' . $host,
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($payload),
        'Connection: close',
        '',
        $payload,
    ]);

    fwrite($socket, $request);

    $response = '';

    while (!feof($socket)) {
        $response .= fgets($socket, 1024);
    }

    fclose($socket);

    $parts = explode("\r\n\r\n", $response, 2);
    $body = $parts[1] ?? '';
    $result = json_decode($body, true);

    return is_array($result) && ($result['status'] ?? '') === 'ok';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Метод не поддерживается.', 405);
}

$configPath = __DIR__ . '/config.local.php';

if (!is_file($configPath)) {
    respond(false, 'Отправка пока не настроена на сервере.', 500);
}

$config = require $configPath;

if (!is_array($config) || empty($config['smtp_pass']) || $config['smtp_pass'] === 'PASTE_MAILBOX_PASSWORD_HERE') {
    respond(false, 'Не заполнены настройки отправки на сервере.', 500);
}

if (!empty($_POST['website'])) {
    respond(true, 'Спасибо, заявка отправлена.');
}

$captchaToken = clean_value((string) ($_POST['smart-token'] ?? ''), 4096);
$name = clean_value((string) ($_POST['name'] ?? ''), 120);
$company = clean_value((string) ($_POST['company'] ?? ''), 160);
$phone = clean_value((string) ($_POST['phone'] ?? ''), 80);
$message = clean_value((string) ($_POST['message'] ?? ''), 2000);

try {
    if (!verify_smartcaptcha($config, $captchaToken)) {
        respond(false, 'Подтвердите, что вы не робот.', 422);
    }
} catch (Throwable $error) {
    error_log('RIK-LAB captcha error: ' . $error->getMessage());
    respond(false, 'Проверка капчи временно недоступна. Позвоните нам: +7 995 918-65-16.', 500);
}

if ($name === '' || $phone === '' || $message === '') {
    respond(false, 'Заполните имя, телефон и описание задачи.', 422);
}

$body = implode("\n", [
    'Новая заявка с сайта РИК-ЛАБ',
    '',
    'Имя: ' . $name,
    'Компания: ' . ($company !== '' ? $company : 'не указана'),
    'Телефон: ' . $phone,
    '',
    'Описание задачи:',
    $message,
    '',
    'Страница: ' . ($_SERVER['HTTP_REFERER'] ?? 'не указана'),
    'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'не определен'),
    'Дата: ' . date('Y-m-d H:i:s'),
]);

try {
    smtp_send($config, (string) $config['mail_subject'], $body);
    respond(true, 'Спасибо, заявка отправлена. Специалисты РИК-ЛАБ свяжутся с вами.');
} catch (Throwable $error) {
    error_log('RIK-LAB form error: ' . $error->getMessage());
    respond(false, 'Не удалось отправить заявку. Позвоните нам: +7 995 918-65-16.', 500);
}
