<?php
namespace app\controllers;
use app\components\{Api, ApiException, BrowserSessionPolicy as B, RolePolicy};
use app\services\AuthService;
final class AuthController extends BaseController
{
    public function actionLogin(): array { return Api::ok((new AuthService())->login(Api::jsonBody(), \Yii::$app->request->userIP ?? 'unknown')); }
    private function browserOrigin(): string
    {
        $allowed=getenv('FRONTEND_ORIGIN') ?: 'http://localhost:3000';
        if(!B::originAllowed(\Yii::$app->request->headers->get('Origin'),$allowed))throw new ApiException(403,'FORBIDDEN','Origin sesi tidak diizinkan.');
        return $allowed;
    }
    private function cookie(string $token,int $ttl,string $origin): void
    { \Yii::$app->response->headers->set('Set-Cookie',B::cookie($token,$ttl,$origin)); }
    private function cookieToken(): ?string
    { $token=$_COOKIE[B::COOKIE]??null;return is_string($token)?$token:null; }
    public function actionBrowserLogin(): array
    {
        $origin=$this->browserOrigin();$result=(new AuthService())->login(Api::jsonBody(),\Yii::$app->request->userIP??'unknown');
        $this->cookie($result['access_token'],$result['expires_in'],$origin);return Api::ok($result);
    }
    public function actionBrowserRestore(): array
    {
        $origin=$this->browserOrigin();if(Api::jsonBody()!==[])throw new ApiException(422,'VALIDATION_ERROR','Body sesi harus kosong.');
        // A late 401 must not overwrite a newer login cookie. Invalid cookies are unusable and expire naturally.
        return Api::ok((new AuthService())->restore($this->cookieToken()));
    }
    public function actionBrowserLogout(): \yii\web\Response
    {
        $origin=$this->browserOrigin();if(Api::jsonBody()!==[])throw new ApiException(422,'VALIDATION_ERROR','Body sesi harus kosong.');
        $auth=new AuthService();$header=\Yii::$app->request->headers->get('Authorization');$cookie=$this->cookieToken();
        $target=B::logoutTarget($header,$cookie);
        if($target['target']!==null){try{$auth->revoke($auth->authenticate($target['target']));}catch(ApiException $e){if($e->statusCode!==401)throw $e;}}
        if($target['clear_cookie'])$this->cookie('',0,$origin);$r=\Yii::$app->response;$r->statusCode=204;$r->data=null;return $r;
    }
    public function actionMe(): array { return Api::ok(RolePolicy::publicUser($this->actor())); }
    public function actionLogout(): \yii\web\Response
    { (new AuthService())->revoke($this->actor()); $r=\Yii::$app->response; $r->statusCode=204; $r->data=null; return $r; }
}
