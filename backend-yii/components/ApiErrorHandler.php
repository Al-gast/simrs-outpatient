<?php
namespace app\components;
final class ApiErrorHandler extends \yii\web\ErrorHandler
{
    // Avoid default logging of request payloads or authorization headers.
    public function logException($exception) { if (!$exception instanceof \yii\web\HttpException) error_log('SIMRS core error; request_id=' . Api::id() . '; type=' . get_class($exception)); }
    protected function renderException($exception)
    {
        $response = \Yii::$app->response;
        $status = $exception instanceof \yii\web\HttpException ? $exception->statusCode : 500;
        $codes = [400=>'BAD_REQUEST',401=>'UNAUTHORIZED',403=>'FORBIDDEN',404=>'NOT_FOUND',405=>'METHOD_NOT_ALLOWED',422=>'VALIDATION_ERROR',429=>'RATE_LIMITED',503=>'UNAVAILABLE'];
        $message = $status >= 500 ? 'Layanan tidak tersedia atau terjadi kesalahan.' : 'Request tidak dapat diproses.';
        $response->format = \yii\web\Response::FORMAT_JSON;
        $response->statusCode = $status;
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Request-ID', Api::id());
        $response->data = $exception instanceof ApiException ? Api::error($exception->domainCode, $exception->getMessage(), $exception->details) : Api::error($codes[$status] ?? 'INTERNAL_ERROR', $message);
        $response->send();
    }
}
