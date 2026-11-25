<?php
namespace yii\web { if(!class_exists(HttpException::class)){class HttpException extends \Exception{public function __construct(public int $statusCode,string $message){parent::__construct($message);}}} }
namespace {
foreach(['ApiException','RolePolicy','MasterPolicy','AdminPolicy'] as $f)require __DIR__.'/../components/'.$f.'.php';
use app\components\{AdminPolicy as P,RolePolicy,ApiException};
$n=0;function ck(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new \RuntimeException($label);}
function rejects(callable $fn,string $code='VALIDATION_ERROR'):bool{try{$fn();return false;}catch(ApiException $e){return $e->domainCode===$code;}}
$base=['username'=>'dummy-1','name'=>' Dummy ','role'=>'cashier','staff_id'=>null,'active'=>true,'password'=>'test-password-123'];
ck(P::account($base,true)['name']==='Dummy','trim');
foreach(RolePolicy::ROLES as $r){$v=[...$base,'role'=>$r,'staff_id'=>$r==='doctor'?'10':null];ck(P::account($v,true)['role']===$r,'role');ck(rejects(fn()=>P::account([...$v,'staff_id'=>$r==='doctor'?null:'10'],true)),'staff required only doctor');}
foreach(['',null,[],123,'UPPER','a b','a/b',str_repeat('a',81)] as $v)ck(rejects(fn()=>P::account([...$base,'username'=>$v],true)),'username');
foreach(['',null,[],123,'short',str_repeat('x',73),str_repeat('x',12)."\0"] as $v)ck(rejects(fn()=>P::account([...$base,'password'=>$v],true)),'password');
foreach(['',null,[],123,'bad',true] as $v)ck(rejects(fn()=>P::account([...$base,'role'=>$v],true)),'role');
foreach(['true',1,null,[]] as $v)ck(rejects(fn()=>P::account([...$base,'active'=>$v],true)),'active');
foreach(['',null,[],str_repeat('x',201)] as $v)ck(rejects(fn()=>P::account([...$base,'name'=>$v],true)),'name');
foreach(['token_version','password_hash','id'] as $key)ck(rejects(fn()=>P::account($base+[$key=>'injected'],true)),'unknown');
$edit=$base;unset($edit['username'],$edit['password']);$edit['expected_version']=5;ck(P::account($edit,false)['expected_version']===5,'edit');
foreach([-1,'5',null,1.5,2147483647] as $v)ck(rejects(fn()=>P::account([...$edit,'expected_version'=>$v],false)),'version');
$actor=['id'=>'1'];$before=['id'=>'2','role'=>'cashier','staff_id'=>null,'token_version'=>5];P::protect($actor,$before,$edit);ck(true,'valid edit');
ck(rejects(fn()=>P::protect(['id'=>'2'],$before,$edit),'SELF_EDIT'),'self');ck(rejects(fn()=>P::protect($actor,$before,[...$edit,'expected_version'=>4]),'STALE_USER'),'stale');
$doctor=[...$before,'role'=>'doctor','staff_id'=>'9'];ck(rejects(fn()=>P::protect($actor,$doctor,$edit),'DOCTOR_IDENTITY_LOCKED'),'doctor identity');ck(rejects(fn()=>P::protect($actor,$doctor,[...$edit,'role'=>'doctor','staff_id'=>'10']),'DOCTOR_IDENTITY_LOCKED'),'doctor reassignment');P::protect($actor,$doctor,[...$edit,'role'=>'doctor','staff_id'=>'9','active'=>false]);ck(true,'deactivate doctor');
ck(P::filters([],false)['per_page']===20,'default page');foreach([['page'=>0],['per_page'=>101],['role'=>'bad'],['active'=>true],['password'=>'x'],['q'=>[]]] as $f)ck(rejects(fn()=>P::filters($f,false)),'bad user filter');
$dates=['from'=>'2024-01-01','to'=>'2024-12-31'];ck(P::filters($dates,true)['page']===1,'leap range');foreach([['from'=>'2026-02-30'],['to'=>'2025-01-01'],['to'=>'2023-01-01'],['actor_id'=>'0'],['action'=>"x'"],['entity_type'=>'a b'],['before_data'=>'yes']] as $f)ck(rejects(fn()=>P::filters(array_replace($dates,$f),true)),'bad audit filter');
echo "PASS: $n admin PHP assertions\n";
}
