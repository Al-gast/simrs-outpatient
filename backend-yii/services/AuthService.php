<?php
namespace app\services;
use app\components\{ApiException, RolePolicy};
use Firebase\JWT\{JWT, Key};
use yii\db\Query;
final class AuthService
{
    private const TTL = 7200;
    private const ISSUER = 'simrs-core';
    private const AUDIENCE = 'simrs-outpatient';
    private function secret(): string
    {
        $secret = getenv('JWT_SECRET') ?: '';
        if (strlen($secret) < 32 || str_starts_with($secret, 'replace-')) throw new \RuntimeException('JWT secret is not configured');
        return $secret;
    }
    private function unauthorized(): never { throw new ApiException(401, 'UNAUTHORIZED', 'Sesi tidak valid atau telah berakhir.'); }
    private function throttle(string $bucket, int $limit): void
    {
        $sql = "INSERT INTO login_attempts(bucket, window_start, attempts) VALUES (:bucket, now(), 1) ON CONFLICT (bucket) DO UPDATE SET attempts = CASE WHEN login_attempts.window_start <= now()-interval '15 minutes' THEN 1 ELSE least(login_attempts.attempts+1, 1000000) END, window_start = CASE WHEN login_attempts.window_start <= now()-interval '15 minutes' THEN now() ELSE login_attempts.window_start END RETURNING attempts";
        $count = (int)\Yii::$app->db->createCommand($sql, [':bucket'=>hash('sha256', $bucket)])->queryScalar();
        if ($count > $limit) { \Yii::$app->response->headers->set('Retry-After', '900'); throw new ApiException(429, 'RATE_LIMITED', 'Terlalu banyak percobaan masuk. Coba lagi nanti.'); }
    }
    public function login(array $input, string $ip): array
    {
        $username = $input['username'] ?? null; $password = $input['password'] ?? null;
        if (array_diff(array_keys($input), ['username','password']) || !is_string($username) || !RolePolicy::validLoginPassword($password) || !preg_match('/\A[a-zA-Z0-9_.-]{1,80}\z/D', trim($username))) throw new ApiException(422, 'VALIDATION_ERROR', 'Username atau password tidak valid.');
        $username = strtolower(trim($username));
        $this->throttle('ip:'.$ip, 100); $this->throttle('pair:'.$ip.':'.$username, 10);
        $row = (new Query())->from('users')->where(['username'=>$username])->one();
        // A fixed dummy hash avoids a fast path for unknown usernames.
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $verified = password_verify($password, $row ? $row['password_hash'] : $dummy);
        if (!$row || !$verified || !RolePolicy::active($row['active'])) throw new ApiException(401, 'INVALID_CREDENTIALS', 'Username atau password salah.');
        \Yii::$app->db->createCommand()->delete('login_attempts', ['bucket'=>hash('sha256','pair:'.$ip.':'.$username)])->execute();
        $now = time();
        $token = JWT::encode(['sub'=>(string)$row['id'],'iat'=>$now,'exp'=>$now+self::TTL,'iss'=>self::ISSUER,'aud'=>self::AUDIENCE,'jti'=>bin2hex(random_bytes(16)),'token_version'=>(int)$row['token_version']], $this->secret(), 'HS256');
        return ['access_token'=>$token,'expires_in'=>self::TTL,'user'=>RolePolicy::publicUser($row)];
    }
    public function authenticate(?string $header,?int &$expiry=null): array
    {
        if ($header === null || !preg_match('/\ABearer ([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+)\z/iD', $header, $matches) || strlen($header)>4096) $this->unauthorized();
        $key = new Key($this->secret(), 'HS256');
        try { $claims = JWT::decode($matches[1], $key); } catch (\UnexpectedValueException|\InvalidArgumentException|\DomainException) { $this->unauthorized(); }
        if (($claims->iss ?? null)!==self::ISSUER || ($claims->aud ?? null)!==self::AUDIENCE || !is_string($claims->sub ?? null) || !\app\components\MasterPolicy::id($claims->sub) || !is_int($claims->token_version ?? null) || !is_int($claims->iat ?? null) || !is_int($claims->exp ?? null) || $claims->exp <= time() || $claims->exp-$claims->iat !== self::TTL || !is_string($claims->jti ?? null)) $this->unauthorized();
        $row = (new Query())->from('users')->where(['id'=>$claims->sub])->one();
        if (!$row || !RolePolicy::active($row['active']) || (int)$row['token_version'] !== $claims->token_version || !RolePolicy::permits($row['role'], RolePolicy::ROLES)) $this->unauthorized();
        $expiry=$claims->exp;return $row;
    }
    public function restore(?string $token): array
    {
        $expiry=null;$row=$this->authenticate($token===null?null:'Bearer '.$token,$expiry);
        $remaining=$expiry-time();if($remaining<=0)$this->unauthorized();
        return ['access_token'=>$token,'expires_in'=>$remaining,'user'=>RolePolicy::publicUser($row)];
    }
    public function revoke(array $row): void
    {
        \Yii::$app->db->createCommand('UPDATE users SET token_version=token_version+1 WHERE id=:id AND token_version=:version', [':id'=>$row['id'], ':version'=>$row['token_version']])->execute();
    }
}
