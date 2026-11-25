<?php
namespace app\components;
final class ApiException extends \yii\web\HttpException
{
    public function __construct(int $status, public readonly string $domainCode, string $message, public readonly array $details = [])
    { parent::__construct($status, $message); }
}
