<?php
namespace app\controllers;
use app\components\{Api, ApiException};
final class SystemController extends BaseController
{
    public function actionHealth(): array
    {
        try { \Yii::$app->db->createCommand('SELECT id FROM users LIMIT 1')->queryScalar(); }
        catch (\Throwable) { throw new ApiException(503, 'UNAVAILABLE', 'Database belum siap.'); }
        return Api::ok(['service'=>'yii','database'=>'ready','status'=>'ready']);
    }
    public function actionAccess(string $role): array
    { $actor=$this->actor([$role]); return Api::ok(['role'=>$actor['role'],'access'=>'granted']); }
}
