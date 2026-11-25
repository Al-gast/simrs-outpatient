<?php
use app\components\Api;
use app\components\ApiException;
return [
    'id' => 'simrs-core', 'basePath' => dirname(__DIR__),
    'controllerNamespace' => 'app\\controllers', 'timeZone' => 'Asia/Jakarta',
    'components' => [
        'db' => require __DIR__ . '/db.php',
        'request' => ['enableCsrfValidation' => false, 'enableCookieValidation' => false, 'scriptUrl' => '/index.php'],
        'response' => ['format' => yii\web\Response::FORMAT_JSON],
        'errorHandler' => ['class' => app\components\ApiErrorHandler::class],
        'urlManager' => ['enablePrettyUrl' => true, 'showScriptName' => false, 'enableStrictParsing' => true, 'rules' => [
            'GET api/v1/health' => 'system/health',
            'GET api/v1/master/<resource:patients|medical-staff|clinics|doctor-schedules|procedures|medicines|lab-tests>' => 'master/index',
            'POST api/v1/master/<resource:patients|medical-staff|clinics|doctor-schedules|procedures|medicines|lab-tests>' => 'master/create',
            'GET api/v1/master/<resource:patients|medical-staff|clinics|doctor-schedules|procedures|medicines|lab-tests>/<id:[0-9]+>' => 'master/view',
            'PATCH api/v1/master/<resource:patients|medical-staff|clinics|doctor-schedules|procedures|medicines|lab-tests>/<id:[0-9]+>' => 'master/update',
            'DELETE api/v1/master/<resource:patients|medical-staff|clinics|doctor-schedules|procedures|medicines|lab-tests>/<id:[0-9]+>' => 'master/delete',
            'POST api/v1/master/medicines/<id:[0-9]+>/adjust-stock' => 'master/adjust-stock',
            'GET api/v1/visits' => 'visit/index',
            'POST api/v1/visits' => 'visit/create',
            'GET api/v1/visits/<id:[0-9]+>' => 'visit/view',
            'GET api/v1/patients/<id:[0-9]+>/visits' => 'visit/history',
            'POST api/v1/visits/<id:[0-9]+>/start' => 'visit/start',
            'GET api/v1/visits/<id:[0-9]+>/clinical' => 'clinical/view',
            'PUT api/v1/visits/<id:[0-9]+>/medical-record' => 'clinical/save',
            'PUT api/v1/visits/<id:[0-9]+>/procedures' => 'clinical/procedures',
            'POST api/v1/visits/<id:[0-9]+>/finish-exam' => 'clinical/finish',
            'GET api/v1/visits/<id:[0-9]+>/clinical-history' => 'clinical/history',
            'GET api/v1/visits/<id:[0-9]+>/clinical-history/<target:[0-9]+>' => 'clinical/historical',
            'PUT api/v1/visits/<id:[0-9]+>/prescription' => 'ancillary/prescription',
            'PUT api/v1/visits/<id:[0-9]+>/lab-order' => 'ancillary/lab-order',
            'GET api/v1/pharmacy/orders' => 'ancillary/pharmacy',
            'GET api/v1/pharmacy/orders/<id:[0-9]+>' => 'ancillary/pharmacy-detail',
            'POST api/v1/pharmacy/orders/<id:[0-9]+>/dispense' => 'ancillary/dispense',
            'GET api/v1/laboratory/orders' => 'ancillary/laboratory',
            'GET api/v1/laboratory/orders/<id:[0-9]+>' => 'ancillary/lab-detail',
            'POST api/v1/laboratory/items/<id:[0-9]+>/result' => 'ancillary/result',
            'GET api/v1/billing/visits' => 'billing/index',
            'GET api/v1/billing/visits/<id:[0-9]+>' => 'billing/view',
            'POST api/v1/billing/visits/<id:[0-9]+>/invoice' => 'billing/issue',
            'POST api/v1/billing/visits/<id:[0-9]+>/payments' => 'billing/pay',
            'GET api/v1/admin/users' => 'admin/users',
            'POST api/v1/admin/users' => 'admin/create',
            'PATCH api/v1/admin/users/<id:[0-9]+>' => 'admin/update',
            'GET api/v1/admin/audit' => 'admin/audit',
            'POST api/v1/auth/browser/login' => 'auth/browser-login',
            'POST api/v1/auth/browser/restore' => 'auth/browser-restore',
            'POST api/v1/auth/browser/logout' => 'auth/browser-logout',
            'POST api/v1/auth/login' => 'auth/login',
            'GET api/v1/auth/me' => 'auth/me',
            'POST api/v1/auth/logout' => 'auth/logout',
            'GET api/v1/system/access/<role:admin|registration|doctor|pharmacy|laboratory|cashier>' => 'system/access',
        ]],
    ],
    'on beforeRequest' => static function () {
        $req = Yii::$app->request; $res = Yii::$app->response;
        Api::initialize($req->headers->get('X-Request-ID'));
        $res->headers->set('X-Request-ID', Api::id());
        $res->headers->set('Cache-Control', 'no-store');
        $res->headers->set('X-Content-Type-Options', 'nosniff');
        $origin = $req->headers->get('Origin');
        $allowed = getenv('FRONTEND_ORIGIN') ?: 'http://localhost:3000';
        if ($origin !== null && $origin === $allowed) {
            $res->headers->set('Access-Control-Allow-Origin', $allowed);
            $res->headers->set('Vary', 'Origin');
            if (str_starts_with($req->pathInfo,'api/v1/auth/browser/') && \app\components\BrowserSessionPolicy::originAllowed($origin,$allowed)) $res->headers->set('Access-Control-Allow-Credentials','true');
            $res->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $res->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Request-ID, Idempotency-Key');
            $res->headers->set('Access-Control-Expose-Headers', 'X-Request-ID, Retry-After');
        }
        if ($req->method === 'OPTIONS') {
            if ($origin !== $allowed) throw new ApiException(403, 'FORBIDDEN', 'Origin tidak diizinkan.');
            $res->statusCode = 204; $res->data = null; Yii::$app->end(0, $res);
        }
    },
];
