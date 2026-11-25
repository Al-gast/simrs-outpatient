<?php
namespace app\controllers;
use app\components\Api;
use app\services\BillingService;
final class BillingController extends BaseController
{
    public function actionIndex(): array {$this->actor(['admin','cashier']);return (new BillingService())->queue(\Yii::$app->request->queryParams);}
    public function actionView(string $id): array {$this->actor(['admin','cashier']);return Api::ok((new BillingService())->get($id));}
    public function actionIssue(string $id): array {return Api::ok((new BillingService())->issue($this->actor(['cashier']),$id,Api::jsonBody()));}
    public function actionPay(string $id): array {return Api::ok((new BillingService())->pay($this->actor(['cashier']),$id,Api::jsonBody(),\Yii::$app->request->headers->get('Idempotency-Key')));}
}
