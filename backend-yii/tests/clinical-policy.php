<?php
namespace yii\web { if(!class_exists(HttpException::class)){class HttpException extends \Exception{public function __construct(public int $statusCode,string $message){parent::__construct($message);}}} }
namespace {
require __DIR__.'/../components/ApiException.php';require __DIR__.'/../components/MasterPolicy.php';require __DIR__.'/../components/RolePolicy.php';require __DIR__.'/../components/ClinicalPolicy.php';
use app\components\{ClinicalPolicy as C,ApiException,RolePolicy};
$n=0;function ck(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new \RuntimeException($label);}
function reject(callable $fn,int $status=422,string $code='VALIDATION_ERROR'):bool{try{$fn();return false;}catch(ApiException $e){return $e->statusCode===$status&&$e->domainCode===$code;}}
$vitals=['systolic'=>120,'diastolic'=>80,'pulse'=>84,'temperature'=>37.2,'respiratory_rate'=>20,'weight'=>65.5];
$body=['complaint'=>' Keluhan dummy ','anamnesis'=>'Dummy','physical_exam'=>'Dummy','primary_diagnosis'=>'Diagnosis A','secondary_diagnoses'=>['Diagnosis B'],'vital_signs'=>(object)$vitals,'expected_version'=>0];
$valid=C::record($body);ck($valid['complaint']==='Keluhan dummy'&&$valid['vital_signs']['systolic']===120.0&&$valid['reason']===null,'normalize');
foreach(C::TEXT as $k=>$max)foreach([null,123,' ',str_repeat('a',$max+1),"a\0b"] as $bad)ck(reject(fn()=>C::record(array_replace($body,[$k=>$bad]))),"bad text $k");
foreach(C::VITALS as $key=>[$min,$max])foreach([null,'80',$min-1,$max+1,INF,NAN] as $bad)ck(reject(fn()=>C::record(array_replace($body,['vital_signs'=>(object)array_replace($vitals,[$key=>$bad])]))),"bad vital $key");
foreach([[],(object)array_replace($vitals,['bogus'=>1]),(object)array_replace($vitals,['systolic'=>70])] as $bad)ck(reject(fn()=>C::record(array_replace($body,['vital_signs'=>$bad]))),'bad vital shape');
foreach([null,'1',-1,1.2,2147483647] as $bad)ck(reject(fn()=>C::record(array_replace($body,['expected_version'=>$bad]))),'bad version');
foreach([null,(object)[],[''],['Diagnosis A'],['B','B'],array_fill(0,11,'B'),[1]] as $bad)ck(reject(fn()=>C::record(array_replace($body,['secondary_diagnoses'=>$bad]))),'bad secondary');
ck(reject(fn()=>C::record($body+['actor_id'=>'1'])),'mass assignment');ck(reject(fn()=>C::record(array_replace($body,['expected_version'=>1]))),'reason required');ck(C::record(array_replace($body,['expected_version'=>1,'reason'=>' koreksi ']))['reason']==='koreksi','reason normalized');
$p=['items'=>[(object)['procedure_id'=>'1','quantity'=>2],(object)['procedure_id'=>'2','quantity'=>1]],'expected_version'=>1,'reason'=>'Dummy'];ck(count(C::procedures($p)['items'])===2,'multiple');ck(C::procedures(array_replace($p,['items'=>[]]))['items']===[],'clear list');
foreach([null,'1',[],(object)['procedure_id'=>'0','quantity'=>1],(object)['procedure_id'=>'01','quantity'=>1],(object)['procedure_id'=>'1','quantity'=>0],(object)['procedure_id'=>'1','quantity'=>101],(object)['procedure_id'=>'1','quantity'=>1.5],(object)['procedure_id'=>'1','quantity'=>1,'unit_price'=>'1']] as $bad)ck(reject(fn()=>C::procedures(array_replace($p,['items'=>[$bad]]))),'bad procedure item');
ck(reject(fn()=>C::procedures(array_replace($p,['items'=>[$p['items'][0],$p['items'][0]]]))),'duplicate procedure');ck(reject(fn()=>C::procedures(array_replace($p,['items'=>array_fill(0,31,$p['items'][0])]))),'too many procedures');ck(reject(fn()=>C::procedures(array_replace($p,['expected_version'=>0]))),'record required');ck(reject(fn()=>C::procedures(array_replace($p,['reason'=>'']))),'procedure reason');
ck(C::finish(['expected_version'=>1])===1,'finish');ck(reject(fn()=>C::finish(['expected_version'=>0])),'finish no record version');ck(reject(fn()=>C::finish(['expected_version'=>1,'status'=>'completed'])),'client status denied');
C::checkVersion(1,1);ck(reject(fn()=>C::checkVersion(2,1),409,'STALE_RECORD'),'stale');ck(reject(fn()=>C::checkVersion(1,0),409,'STALE_RECORD'),'concurrent create');
foreach(['waiting','in_exam','services_pending','ready_for_billing','completed'] as $s){ck(C::editable($s)===($s==='in_exam'),'edit status');ck(C::diagnosisOnly($s)===in_array($s,['services_pending','ready_for_billing','completed'],true),'amend status');}
foreach([false,true] as $rx)foreach([false,true] as $lab)ck(C::nextStatus($rx,$lab)===($rx||$lab?'services_pending':'ready_for_billing'),'finish branches');
ck(C::nonDiagnosisUnchanged($valid,array_replace($valid,['primary_diagnosis'=>'Changed'])),'diagnosis only');ck(!C::nonDiagnosisUnchanged($valid,array_replace($valid,['complaint'=>'Changed'])),'complaint frozen');$changed=$valid;$changed['vital_signs']['pulse']=85;ck(!C::nonDiagnosisUnchanged($valid,$changed),'vitals frozen');
$a=['patient_id'=>'1','doctor_id'=>'1','visit_date'=>'2026-11-24','status'=>'in_exam'];$t=['patient_id'=>'1','doctor_id'=>'2','visit_date'=>'2026-11-20','status'=>'completed'];ck(C::historyAllowed($a,$t,'2026-11-24'),'active own patient');ck(!C::historyAllowed($a,array_replace($t,['patient_id'=>'2']),'2026-11-24'),'different patient');ck(!C::historyAllowed(array_replace($a,['status'=>'completed']),$t,'2026-11-24'),'closed cross doctor');ck(!C::historyAllowed($a,$t,'2026-11-25'),'past cross doctor');ck(!C::historyAllowed($a,array_replace($t,['status'=>'waiting']),'2026-11-24'),'waiting no history');ck(C::historyAllowed(array_replace($a,['status'=>'completed']),array_replace($t,['doctor_id'=>'1']),'2026-11-24'),'own history');
foreach(RolePolicy::ROLES as $r)ck(RolePolicy::permits($r,C::ROLES)===($r==='doctor'),'role '.$r);
ck(C::procedures(['items'=>$p['items'],'expected_version'=>1])['reason']===null,'initial procedure reason optional');
C::requireReason(null,false);ck(reject(fn()=>C::requireReason(null,true)),'persisted edit requires reason');C::requireReason('dummy',true);ck(true,'reason accepted');
echo "PASS: $n clinical policy and validation assertions\n";
}
