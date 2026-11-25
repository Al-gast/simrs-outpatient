<?php
return [
    'class' => yii\db\Connection::class,
    'dsn' => getenv('DB_DSN') ?: 'pgsql:host=localhost;port=5432;dbname=simrs',
    'username' => getenv('DB_USER') ?: 'simrs_core',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8',
    'enableLogging' => false,
    'enableProfiling' => false,
    'on afterOpen' => static function ($event) {
        $event->sender->createCommand("SET TIME ZONE 'Asia/Jakarta'")->execute();
    },
];
