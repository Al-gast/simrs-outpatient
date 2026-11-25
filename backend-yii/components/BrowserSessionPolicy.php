<?php
namespace app\components;

/** Cookie is only used by explicit browser session endpoints, never business APIs. */
final class BrowserSessionPolicy
{
    public const COOKIE = 'simrs_browser_session';
    public const PATH = '/api/v1/auth/browser/';
    public static function originAllowed(?string $origin,string $allowed): bool
    {return $origin!==null && $origin===$allowed && ($allowed==='http://localhost:3000'||preg_match('~\Ahttps://[a-zA-Z0-9.-]+(?::[0-9]+)?\z~D',$allowed)===1);}
    /** Explicit bearer takes precedence. Never revoke/expire another tab's newer cookie. */
    public static function logoutTarget(?string $header,?string $cookie): array
    {
        $cookieHeader=$cookie===null?null:'Bearer '.$cookie;
        return ['target'=>$header??$cookieHeader,'clear_cookie'=>$header===null||$cookieHeader===null||hash_equals($cookieHeader,$header)];
    }
    public static function cookie(string $token,int $maxAge,string $origin): string
    {
        if($token!==''&&!preg_match('/\A[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z/D',$token))throw new \InvalidArgumentException('Invalid session token');
        return self::COOKIE.'='.$token.'; Path='.self::PATH.'; Max-Age='.$maxAge.'; HttpOnly; SameSite=Strict'.(str_starts_with($origin,'https://')?'; Secure':'');
    }
}
