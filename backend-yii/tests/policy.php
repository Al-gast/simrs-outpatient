<?php
require __DIR__.'/../components/RolePolicy.php';
require __DIR__.'/../components/Api.php';
use app\components\{RolePolicy,Api};
function check($condition,$message) {if(!$condition)throw new RuntimeException($message);}
foreach(RolePolicy::ROLES as $actor)foreach(RolePolicy::ROLES as $target)check(RolePolicy::permits($actor,[$target])===($actor===$target),"Role matrix $actor/$target");
check(!RolePolicy::permits('ADMIN',['admin']),'Invalid case');
check(!RolePolicy::active('f') && RolePolicy::active('t'),'Postgres booleans');
$user=RolePolicy::publicUser(['id'=>1,'username'=>'admin','name'=>'Admin','role'=>'admin','staff_id'=>null,'password_hash'=>'secret','token_version'=>0]);
check($user['id']==='1' && !array_key_exists('password_hash',$user) && !array_key_exists('token_version',$user),'Safe DTO');
Api::initialize('request-1');check(Api::ok(['x'=>1])['meta']['request_id']==='request-1','Request ID');
Api::initialize("bad\nheader");check(preg_match('/^[a-f0-9]{32}$/',Api::id())===1,'Regenerate invalid request ID');
check(Api::error('TEST','test')['error']['details'] instanceof stdClass,'Error details object');
echo "PASS: 36 role combinations + 6 policy/response checks\n";
