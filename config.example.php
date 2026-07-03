<?php
// Скопируйте этот файл в config.local.php на сервере и впишите пароль от ящика.
// config.local.php не нужно публиковать в GitHub.

return [
    'smtp_host' => 'mail.hosting.reg.ru',
    'smtp_port' => 465,
    'smtp_secure' => 'ssl',
    'smtp_user' => 'info@riklab.ru',
    'smtp_pass' => 'PASTE_MAILBOX_PASSWORD_HERE',
    'mail_from' => 'info@riklab.ru',
    'mail_from_name' => 'РИК-ЛАБ',
    'mail_to' => 'info@riklab.ru',
    'mail_subject' => 'Заявка с сайта РИК-ЛАБ',
];
