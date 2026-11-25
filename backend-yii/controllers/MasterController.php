<?php
namespace app\controllers;
use app\components\{Api,ApiException,MasterPolicy};
use app\services\MasterService;
final class MasterController extends BaseController
{
    public function actionIndex(string $resource): array {$filters=\Yii::$app->request->queryParams;unset($filters['resource']);return (new MasterService())->list($resource,$this->actor(MasterPolicy::get($resource)['read']),$filters);}
    public function actionView(string $resource,string $id): array {return Api::ok((new MasterService())->get($resource,$id,$this->actor(MasterPolicy::get($resource)['read'])));}
    public function actionCreate(string $resource): array {$actor=$this->actor(MasterPolicy::writeRoles($resource));$row=(new MasterService())->save($resource,null,Api::jsonBody(),$actor);\Yii::$app->response->statusCode=201;return Api::ok($row);}
    public function actionUpdate(string $resource,string $id): array {$actor=$this->actor(MasterPolicy::writeRoles($resource));return Api::ok((new MasterService())->save($resource,$id,Api::jsonBody(),$actor));}
    public function actionDelete(string $resource,string $id): ?array {$actor=$this->actor(MasterPolicy::writeRoles($resource));$mode=\Yii::$app->request->get('mode');if($mode!==null&&$mode!=='permanent')throw new ApiException(422,'VALIDATION_ERROR','Mode penghapusan tidak valid.');$row=(new MasterService())->delete($resource,$id,$actor,$mode==='permanent');if($row===null){\Yii::$app->response->statusCode=204;return null;}return Api::ok($row);}
    public function actionAdjustStock(string $id): array {$actor=$this->actor(['admin']);return Api::ok((new MasterService())->adjustStock($id,Api::jsonBody(),$actor,\Yii::$app->request->headers->get('Idempotency-Key')));}
}
