<?php
namespace app\controllers;
use app\components\Api;
use app\services\AdminService;
final class AdminController extends BaseController
{
    public function actionUsers(): array {$this->actor(['admin']);return (new AdminService())->users(\Yii::$app->request->queryParams);}
    public function actionCreate(): array {$actor=$this->actor(['admin']);$row=(new AdminService())->save($actor,null,Api::jsonBody());\Yii::$app->response->statusCode=201;return Api::ok($row);}
    public function actionUpdate(string $id): array {$actor=$this->actor(['admin']);return Api::ok((new AdminService())->save($actor,$id,Api::jsonBody()));}
    public function actionAudit(): array {$this->actor(['admin']);return (new AdminService())->audit(\Yii::$app->request->queryParams);}
}
