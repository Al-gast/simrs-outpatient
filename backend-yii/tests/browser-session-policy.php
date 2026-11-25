<?php
require __DIR__.'/../components/BrowserSessionPolicy.php';
use app\components\BrowserSessionPolicy as P;
$n=0;function check(bool $ok,string $msg): void{global $n;$n++;if(!$ok)throw new RuntimeException($msg);}
$local='http://localhost:3000';$https='https://simrs.example.test';
check(P::originAllowed($local,$local),'localhost');check(P::originAllowed($https,$https),'https');
foreach([null,'null','http://127.0.0.1:3000','http://localhost:3001','https://evil.example'] as $o)check(!P::originAllowed($o,$local),'origin rejected');
check(!P::originAllowed('http://example.test','http://example.test'),'nonlocal HTTP rejected');
$cookie=P::cookie('abc.def.ghi',7200,$local);
foreach(['simrs_browser_session=abc.def.ghi','Path=/api/v1/auth/browser/','Max-Age=7200','HttpOnly','SameSite=Strict'] as $s)check(str_contains($cookie,$s),'cookie attr');
check(!str_contains($cookie,'Domain='),'host only');check(!str_contains($cookie,'; Secure'),'local exception');
check(str_contains(P::cookie('abc.def.ghi',7200,$https),'; Secure'),'HTTPS secure');
check(str_contains(P::cookie('',0,$local),'Max-Age=0'),'logout expiry');
try{P::cookie("a\r\n.b.c",7200,$local);check(false,'invalid header');}catch(InvalidArgumentException){check(true,'invalid header rejected');}
foreach([
 [null,null,null,true],[null,'a.b.c','Bearer a.b.c',true],['Bearer a.b.c',null,'Bearer a.b.c',true],
 ['Bearer a.b.c','a.b.c','Bearer a.b.c',true],['Bearer a.b.c','d.e.f','Bearer a.b.c',false],['invalid','d.e.f','invalid',false]
] as [$header,$cookieToken,$target,$clear]){check(P::logoutTarget($header,$cookieToken)===['target'=>$target,'clear_cookie'=>$clear],'logout target isolation');}
echo "PASS: $n browser session policy assertions\n";
