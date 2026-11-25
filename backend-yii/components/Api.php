<?php
namespace app\components;
final class Api
{
    private static string $id = '';
    public static function initialize(?string $candidate): void
    { self::$id = $candidate !== null && preg_match('/\A[A-Za-z0-9_-]{1,80}\z/D', $candidate) ? $candidate : bin2hex(random_bytes(16)); }
    public static function id(): string
    { if (self::$id === '') self::initialize(null); return self::$id; }
    public static function ok(mixed $data): array
    { return ['data' => $data, 'meta' => ['request_id' => self::id()]]; }
    public static function error(string $code, string $message, array $details = []): array
    { return ['error' => ['code' => $code, 'message' => $message, 'details' => (object)$details], 'meta' => ['request_id' => self::id()]]; }
    public static function jsonBody(): array
    {
        $request = \Yii::$app->request;
        if (strtolower(trim(explode(';', $request->contentType ?? '')[0])) !== 'application/json') throw new ApiException(415, 'UNSUPPORTED_MEDIA_TYPE', 'Gunakan application/json.');
        $length = $request->headers->get('Content-Length');
        if (is_string($length) && ctype_digit($length) && (float)$length > 16384) throw new ApiException(413, 'PAYLOAD_TOO_LARGE', 'Request terlalu besar.');
        $raw = $request->rawBody;
        if (strlen($raw) > 16384) throw new ApiException(413, 'PAYLOAD_TOO_LARGE', 'Request terlalu besar.');
        try { $value = json_decode($raw, false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new ApiException(400, 'INVALID_JSON', 'JSON tidak valid.'); }
        if (!$value instanceof \stdClass) throw new ApiException(422, 'VALIDATION_ERROR', 'Body harus berupa object.');
        return (array)$value;
    }
}
