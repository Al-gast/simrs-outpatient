<?php
namespace app\controllers;
use app\components\{Api,VisitPolicy};
use app\services\VisitService;
final class VisitController extends BaseController
{
    public function actionIndex(): array {return (new VisitService())->list($this->actor(VisitPolicy::READ),\Yii::$app->request->queryParams);}
    public function actionView(string $id): array {return Api::ok((new VisitService())->get($this->actor(VisitPolicy::READ),$id));}
    public function actionHistory(string $id): array {$filters=\Yii::$app->request->queryParams;unset($filters['id']);return (new VisitService())->list($this->actor(VisitPolicy::HISTORY),$filters,$id);}
    public function actionCreate(): array {$actor=$this->actor(['registration']);$row=(new VisitService())->register($actor,Api::jsonBody());\Yii::$app->response->statusCode=201;return Api::ok($row);}
    public function actionStart(string $id): array {return Api::ok((new VisitService())->start($this->actor(['doctor']),$id));}
}
