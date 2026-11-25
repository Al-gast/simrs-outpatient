<?php
namespace yii\web { if(!class_exists(HttpException::class)){class HttpException extends \Exception{public function __construct(public int $statusCode,string $message){parent::__construct($message);}}} }
namespace {
require __DIR__.'/../components/ApiException.php';require __DIR__.'/../components/RolePolicy.php';require __DIR__.'/../components/MasterPolicy.php';require __DIR__.'/../components/VisitPolicy.php';
use app\components\{VisitPolicy as V,RolePolicy,ApiException};
$n=0;function check(bool $ok,string $message):void{global $n;$n++;if(!$ok)throw new \RuntimeException($message);}
function rejects(callable $fn):bool{try{$fn();return false;}catch(ApiException $e){return $e->statusCode===422;}}
foreach(RolePolicy::ROLES as $r){check(RolePolicy::permits($r,V::READ)===in_array($r,['admin','registration','doctor'],true),'read role');check(RolePolicy::permits($r,V::HISTORY)===in_array($r,['registration','doctor'],true),'history role');check(V::assigned(['role'=>$r,'staff_id'=>'7'],['doctor_id'=>7])===($r==='doctor'),'assigned role');}
check(V::registration(['patient_id'=>'1','schedule_id'=>'9223372036854775807'])===['patient_id'=>'1','schedule_id'=>'9223372036854775807'],'valid body');
foreach([[],['patient_id'=>'1'],['patient_id'=>1,'schedule_id'=>'2'],['patient_id'=>'0','schedule_id'=>'2'],['patient_id'=>'01','schedule_id'=>'2'],['patient_id'=>'9223372036854775808','schedule_id'=>'2'],['patient_id'=>'1','schedule_id'=>[]],['patient_id'=>'1','schedule_id'=>'2','queue_number'=>1],['patient_id'=>'1','schedule_id'=>'2','registration_fee'=>'0'],['patient_id'=>'1','schedule_id'=>'2','visit_date'=>'2026-11-24'],['patient_id'=>'1','schedule_id'=>'2','status'=>'completed'],['patient_id'=>'1','schedule_id'=>'2','doctor_id'=>'8']] as $body)check(rejects(fn()=>V::registration($body)),'reject body');
$f=V::filters([]);check($f['page']===1&&$f['per_page']===20&&$f['date']===V::today(),'default date WIB');check(!isset(V::filters([],true)['date']),'history all dates');
check(V::filters(['q'=>'  Budi  ','date'=>'2024-02-29','page'=>'2','per_page'=>'100','clinic_id'=>'1','doctor_id'=>'2','status'=>'waiting'])===['page'=>2,'per_page'=>100,'q'=>'Budi','clinic_id'=>'1','doctor_id'=>'2','status'=>'waiting','date'=>'2024-02-29'],'valid filters');
foreach([['page'=>0],['page'=>'100001'],['page'=>'1e2'],['per_page'=>'101'],['per_page'=>[]],['q'=>str_repeat('a',201)],['q'=>"a\0b"],['q'=>[]],['date'=>'2026-02-29'],['date'=>'2026-11-31'],['date'=>'today'],['clinic_id'=>'0'],['doctor_id'=>'-1'],['status'=>'paid'],['patient_id'=>'1'],['sort'=>'anything']] as $filters)check(rejects(fn()=>V::filters($filters)),'invalid filter');
$row=['active'=>true,'doctor_active'=>'t','clinic_active'=>1,'profession'=>'doctor','weekday'=>2];check(V::scheduleAvailable($row,'2026-11-24'),'Tuesday available');check(!V::scheduleAvailable($row,'2026-11-25'),'weekday mismatch');
foreach(['active','doctor_active','clinic_active'] as $k)check(!V::scheduleAvailable(array_replace($row,[$k=>'f']),'2026-11-24'),'inactive '.$k);
check(!V::scheduleAvailable(array_replace($row,['profession'=>'nurse']),'2026-11-24'),'not doctor');check(!V::assigned(['role'=>'doctor','staff_id'=>'8'],['doctor_id'=>'7']),'other doctor');check(!V::assigned(['role'=>'doctor','staff_id'=>null],['doctor_id'=>'7']),'missing staff');
foreach(V::STATUSES as $s){check(V::filters(['status'=>$s])['status']===$s,'valid status');check(V::startOutcome($s)===match($s){'waiting'=>'transition','in_exam'=>'replay',default=>'conflict'},'transition');}
echo "PASS: $n visit policy and validation assertions\n";
}
