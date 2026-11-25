<?php
namespace app\controllers;
use app\components\Api;
use app\services\AncillaryService;
final class AncillaryController extends BaseController
{
    public function actionPrescription(string $id): array {return Api::ok((new AncillaryService())->replace($this->actor(['doctor']),$id,Api::jsonBody(),true));}
    public function actionLabOrder(string $id): array {return Api::ok((new AncillaryService())->replace($this->actor(['doctor']),$id,Api::jsonBody(),false));}
    public function actionPharmacy(): array {$this->actor(['pharmacy']);return (new AncillaryService())->queue(true,\Yii::$app->request->queryParams);}
    public function actionLaboratory(): array {$this->actor(['laboratory']);return (new AncillaryService())->queue(false,\Yii::$app->request->queryParams);}
    public function actionPharmacyDetail(string $id): array {$this->actor(['pharmacy']);return Api::ok((new AncillaryService())->get($id,true));}
    public function actionLabDetail(string $id): array {$this->actor(['laboratory']);return Api::ok((new AncillaryService())->get($id,false));}
    public function actionDispense(string $id): array {return Api::ok((new AncillaryService())->dispense($this->actor(['pharmacy']),$id,Api::jsonBody()));}
    public function actionResult(string $id): array {return Api::ok((new AncillaryService())->result($this->actor(['laboratory']),$id,Api::jsonBody(),\Yii::$app->request->headers->get('Idempotency-Key')));}
}
