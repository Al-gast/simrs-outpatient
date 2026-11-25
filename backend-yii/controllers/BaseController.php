<?php
namespace app\controllers;
use app\components\{ApiException, RolePolicy};
use app\services\AuthService;
abstract class BaseController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;
    protected function actor(array $roles = RolePolicy::ROLES): array
    {
        $row = (new AuthService())->authenticate(\Yii::$app->request->headers->get('Authorization'));
        if (!RolePolicy::permits($row['role'], $roles)) throw new ApiException(403, 'FORBIDDEN', 'Anda tidak memiliki hak akses.');
        return $row;
    }
}
