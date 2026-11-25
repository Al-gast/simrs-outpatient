<?php
namespace app\controllers;
use app\components\{Api,ClinicalPolicy};
use app\services\ClinicalService;
final class ClinicalController extends BaseController
{
    public function actionView(string $id): array {return Api::ok((new ClinicalService())->get($this->actor(ClinicalPolicy::ROLES),$id));}
    public function actionSave(string $id): array {return Api::ok((new ClinicalService())->save($this->actor(ClinicalPolicy::ROLES),$id,Api::jsonBody()));}
    public function actionProcedures(string $id): array {return Api::ok((new ClinicalService())->replaceProcedures($this->actor(ClinicalPolicy::ROLES),$id,Api::jsonBody()));}
    public function actionFinish(string $id): array {return Api::ok((new ClinicalService())->finish($this->actor(ClinicalPolicy::ROLES),$id,Api::jsonBody()));}
    public function actionHistory(string $id): array {$f=\Yii::$app->request->queryParams;unset($f['id']);return (new ClinicalService())->history($this->actor(ClinicalPolicy::ROLES),$id,$f);}
    public function actionHistorical(string $id,string $target): array {return Api::ok((new ClinicalService())->historical($this->actor(ClinicalPolicy::ROLES),$id,$target));}
}
