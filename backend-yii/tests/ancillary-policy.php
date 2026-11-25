<?php
namespace yii\web { if(!class_exists(HttpException::class)){class HttpException extends \Exception{public function __construct(public int $statusCode,string $message){parent::__construct($message);}}} }
namespace {
foreach(['ApiException','MasterPolicy','ClinicalPolicy','VisitPolicy','AncillaryPolicy'] as $f)require __DIR__.'/../components/'.$f.'.php';
use app\components\{AncillaryPolicy as P,ApiException,ClinicalPolicy};
$n=0;function ck(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new \RuntimeException($label);}
function reject(callable $fn):bool{try{$fn();return false;}catch(ApiException $e){return $e->statusCode===422&&$e->domainCode==='VALIDATION_ERROR';}}
$rx=(object)['medicine_id'=>'1','quantity'=>2,'dosage'=>' dummy ','frequency'=>'dummy'];$lab=(object)['lab_test_id'=>'2'];$body=['expected_version'=>1,'reason'=>' dummy ','items'=>[$rx]];
$b=P::order($body,true);ck($b['items'][0]['dosage']==='dummy'&&$b['reason']==='dummy','normalize');ck(P::order([...$body,'items'=>[]],true)['items']===[],'clear rx');ck(P::order([...$body,'items'=>[$lab]],false)['items'][0]['lab_test_id']==='2','lab item');
foreach([null,0,-1,1.2,'1',2147483647] as $v)ck(reject(fn()=>P::order([...$body,'expected_version'=>$v],true)),'version');
foreach(['',0,' ',str_repeat('x',501)] as $v)ck(reject(fn()=>P::order([...$body,'reason'=>$v],true)),'reason');
foreach([null,'1',[],(object)[],(object)['medicine_id'=>'0'],(object)[...(array)$rx,'quantity'=>0],(object)[...(array)$rx,'quantity'=>10001],(object)[...(array)$rx,'quantity'=>1.5],(object)[...(array)$rx,'quantity'=>'2'],(object)[...(array)$rx,'dosage'=>''],(object)[...(array)$rx,'frequency'=>null],(object)[...(array)$rx,'unit_price'=>'1']] as $v)ck(reject(fn()=>P::order([...$body,'items'=>[$v]],true)),'rx invalid');
foreach([null,(object)[],[$rx,$rx],array_fill(0,21,$rx)] as $v)ck(reject(fn()=>P::order([...$body,'items'=>$v],true)),'invalid items');
ck(reject(fn()=>P::order([...$body,'items'=>[$lab,$lab]],false)),'duplicate lab');ck(reject(fn()=>P::order([...$body,'items'=>[(object)['lab_test_id'=>'2','result_text'=>'x']]],false)),'result mass assignment');ck(reject(fn()=>P::order($body+['status'=>'completed'],true)),'status mass assignment');
$key='ABCDEF00-1234-4567-89AB-0123456789AB';ck(P::eventKey($key)===strtolower($key),'key normalize');foreach([null,'',123,'x',str_repeat('x',36),$key."\n"] as $v)ck(reject(fn()=>P::eventKey($v)),'invalid key');
ck(P::result(['result_text'=>' dummy '])==='dummy','normalize result');foreach([null,1,' ',str_repeat('x',2001),"x\0"] as $v)ck(reject(fn()=>P::result(['result_text'=>$v])),'bad result');ck(reject(fn()=>P::result(['result_text'=>'ok','actor_id'=>'1'])),'actor forbidden');
$hash=P::hash('2','dummy');$e=['lab_order_item_id'=>'2','actor_id'=>'1','payload_hash'=>$hash];ck(P::sameEvent($e,'2',$hash,'1'),'replay');ck(!P::sameEvent($e,'3',$hash,'1'),'item mismatch');ck(!P::sameEvent($e,'2',P::hash('2','different'),'1'),'payload mismatch');ck(!P::sameEvent($e,'2',$hash,'2'),'actor mismatch');
foreach(['waiting','in_exam','services_pending','ready_for_billing','completed'] as $s){ck(P::canProcess($s)===($s==='services_pending'),'state');ck(P::canProcess($s,false)===in_array($s,['in_exam','services_pending'],true),'lab state');}
foreach([true,false] as $rxPending)foreach([true,false] as $labPending)ck(ClinicalPolicy::nextStatus($rxPending,$labPending)===($rxPending||$labPending?'services_pending':'ready_for_billing'),'advance');
ck(P::queue([])===['page'=>1,'per_page'=>20,'status'=>'pending'],'default filter');foreach(['pending','done','all'] as $s)ck(P::queue(['status'=>$s,'page'=>'2','q'=>' dummy '])===['page'=>2,'per_page'=>20,'q'=>'dummy','status'=>$s],'queue filter');foreach([['status'=>'in_exam'],['page'=>0],['per_page'=>101],['date'=>'2026-11-24'],['q'=>[]]] as $f)ck(reject(fn()=>P::queue($f)),'bad queue');
ck(P::order(['items'=>[$rx],'expected_version'=>1],true)['reason']===null,'initial rx reason optional');ck(P::order(['items'=>[$lab],'expected_version'=>1],false)['reason']===null,'initial lab reason optional');
echo "PASS: $n ancillary PHP assertions\n";
}
