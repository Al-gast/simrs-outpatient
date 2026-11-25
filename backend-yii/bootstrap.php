<?php
declare(strict_types=1);
define('YII_DEBUG', false);
define('YII_ENV', 'prod');
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@app', __DIR__);
