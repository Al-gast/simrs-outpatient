<?php
// Pure tests with a minimal framework exception stand-in only when vendor is absent.
namespace yii\web { if (!class_exists(HttpException::class)) { class HttpException extends \Exception {public function __construct(public int $statusCode,string $message){parent::__construct($message);}} } }
namespace {
require __DIR__.'/../components/ApiException.php';require __DIR__.'/../components/RolePolicy.php';require __DIR__.'/../components/MasterPolicy.php';
use app\components\{MasterPolicy as M,ApiException,RolePolicy};
$n=0;function check(bool $ok,string $m):void{global $n;$n++;if(!$ok)throw new \RuntimeException($m);}
function rejected(callable $fn):bool{try{$fn();return false;}catch(ApiException $e){return $e->statusCode===422;}}
$expected=['patients'=>['admin','registration','doctor'],'medical-staff'=>['admin','registration','doctor'],'clinics'=>['admin','registration','doctor'],'doctor-schedules'=>['admin','registration','doctor'],'procedures'=>['admin','doctor'],'medicines'=>['admin','doctor','pharmacy'],'lab-tests'=>['admin','doctor','laboratory']];
foreach($expected as $resource=>$roles)foreach(RolePolicy::ROLES as $role){check(in_array($role,M::get($resource)['read'],true)===in_array($role,$roles,true),"read $role $resource");check(in_array($role,M::writeRoles($resource),true)===($role==='admin'||($role==='registration'&&$resource==='patients')),"write $role $resource");}
check(M::validate('clinics',['code'=>' ab-01 ','name'=>' A ','registration_fee'=>'0'],true)===['code'=>'AB-01','name'=>'A','registration_fee'=>'0'],'normalization');
check(M::validate('patients',['identity_no'=>'   ','name'=>' P ','birth_date'=>'2000-02-29','sex'=>'U'],true)['identity_no']===null,'nullable identity');
foreach(['0','1','999999999999'] as $v)check(M::money($v),'valid money');
foreach([-1,0,1.1,'-1','1.0','01','1e3','1000000000000',''] as $v)check(!M::money($v),'invalid money');
foreach(['1','9223372036854775807'] as $v)check(M::id($v),'valid id');
foreach([1,'0','-1','01','9223372036854775808','a','1.0'] as $v)check(!M::id($v),'invalid id');
foreach(['2000-02-29','2026-11-24'] as $v)check(M::date($v),'valid date');
foreach(['2026-02-29','2026-13-01','2026-11-31','26-11-24'] as $v)check(!M::date($v),'invalid date');
foreach(['00:00','23:59','08:00:00'] as $v)check(M::time($v),'valid time');
foreach(['24:00','8:00','12:60','12:00:60'] as $v)check(!M::time($v),'invalid time');
foreach([['stock'=>9],['id'=>'1'],['price'=>100],['active'=>'true'],['name'=>''],['code'=>'bad code'],['name'=>"x\0y"],[]] as $v)check(rejected(fn()=>M::validate('medicines',$v,false)),'invalid patch');
check(rejected(fn()=>M::validate('patients',['name'=>'P','birth_date'=>'2999-01-01','sex'=>'M'],true)),'future birth date');
check(rejected(fn()=>M::validate('clinics',['name'=>'P'],true)),'missing fields');
check(rejected(fn()=>M::validate('doctor-schedules',['weekday'=>'1'],false)),'weekday integer');
check(M::validate('doctor-schedules',['weekday'=>7],false)===['weekday'=>7],'weekday valid');
check(M::validate('lab-tests',['unit'=>''],false)===['unit'=>null],'optional lab unit');
foreach([['medical-staff','doctor','D000042'],['medical-staff','nurse','N000042'],['medical-staff','other','S000042'],['clinics',null,'POL-000042'],['procedures',null,'T000042'],['medicines',null,'OB000042'],['lab-tests',null,'L000042']] as [$r,$profession,$expected])check(M::generatedCode($r,'42',$profession)===$expected,'generated '.$r);
check(M::generatedCode('medicines','1000000')==='OB1000000','no truncation');
check(M::validate('clinics',['name'=>'Auto','registration_fee'=>'0'],true)===['name'=>'Auto','registration_fee'=>'0'],'optional code');
check(rejected(fn()=>M::validate('clinics',['name'=>'Bad','registration_fee'=>'0','code'=>''],true)),'explicit blank code rejected');
echo "PASS: $n master policy and validation assertions\n";
}
