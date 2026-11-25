<?php
return [
    'id' => 'simrs-console', 'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\commands', 'timeZone' => 'Asia/Jakarta',
    'components' => ['db' => require __DIR__ . '/db.php'],
    'controllerMap' => ['migrate' => ['class' => yii\console\controllers\MigrateController::class, 'migrationPath' => '@app/migrations']],
];
