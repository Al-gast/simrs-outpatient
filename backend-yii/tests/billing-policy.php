<?php
namespace yii\web { if(!class_exists(HttpException::class)){class HttpException extends \Exception{public function __construct(public int $statusCode,string $message){parent::__construct($message);}}} }
namespace {
foreach(['ApiException','MasterPolicy','ClinicalPolicy','VisitPolicy','AncillaryPolicy','BillingPolicy'] as $f)require __DIR__.'/../components/'.$f.'.php';
use app\components\{BillingPolicy as P,ApiException};
$n=0;function ck(bool $ok,string $label):void{global $n;$n++;if(!$ok)throw new \RuntimeException($label);}
function rejects(callable $fn,string $code):bool{try{$fn();return false;}catch(ApiException $e){return $e->domainCode===$code;}}
$key='ABCDEF00-1234-4567-89AB-0123456789AB';$body=['amount'=>'125000','method'=>'cash','expected_revision'=>1];$b=P::payment($body,$key);ck($b['amount']===125000&&$b['key']===strtolower($key),'valid');
foreach([null,0,1,1.5,'0','01','-1','1.0','1e3',' 1','1000000000000000000',"1\n",[],true] as $v)ck(rejects(fn()=>P::payment([...$body,'amount'=>$v],$key),'VALIDATION_ERROR'),'bad amount');
foreach(['cash','non_cash'] as $v)ck(P::payment([...$body,'method'=>$v],$key)['method']===$v,'method');
foreach([null,'card','CASH',[],true] as $v)ck(rejects(fn()=>P::payment([...$body,'method'=>$v],$key),'VALIDATION_ERROR'),'method');
foreach([null,'1',0,-1,1.5,2147483647] as $v)ck(rejects(fn()=>P::payment([...$body,'expected_revision'=>$v],$key),'VALIDATION_ERROR'),'rev');
foreach([null,'','a',123] as $v)ck(rejects(fn()=>P::payment($body,$v),'VALIDATION_ERROR'),'key');
ck(rejects(fn()=>P::payment($body+['cashier_id'=>'1'],$key),'VALIDATION_ERROR'),'actor injection');
$hash=P::hash('1',$b);$p=['invoice_id'=>'2','cashier_id'=>'3','payload_hash'=>$hash];ck(P::same($p,'2','3',$hash),'replay');ck(!P::same($p,'1','3',$hash),'invoice conflict');ck(!P::same($p,'2','4',$hash),'actor conflict');foreach([['amount'=>126000],['method'=>'non_cash'],['expected_revision'=>2]] as $change)ck(!P::same($p,'2','3',P::hash('1',array_replace($b,$change))),'payload conflict');ck(P::hash('2',$b)!==$hash,'visit scope');
foreach([[0,0,'paid'],[100,0,'unpaid'],[100,1,'partial'],[100,100,'paid']] as [$t,$p,$s])ck(P::status($t,$p)===$s,'status');
P::checkPayment(100,20,80,2,2,'ready_for_billing');ck(true,'exact');P::checkPayment(100,0,1,2,2,'ready_for_billing');ck(true,'partial');
ck(rejects(fn()=>P::checkPayment(100,20,81,2,2,'ready_for_billing'),'OVERPAYMENT'),'overpay');ck(rejects(fn()=>P::checkPayment(100,20,80,2,1,'ready_for_billing'),'STALE_INVOICE'),'stale');ck(rejects(fn()=>P::checkPayment(0,0,1,1,1,'completed'),'INVOICE_CLOSED'),'zero');
foreach(['waiting','in_exam','services_pending','ready_for_billing','completed'] as $s){foreach([false,true] as $paid)ck(P::canRevise($s,$paid)===($s==='ready_for_billing'&&!$paid),'revision');if($s!=='ready_for_billing')ck(rejects(fn()=>P::checkPayment(100,0,1,1,1,$s),'INVOICE_CLOSED'),'state');}
ck(P::total([['unit_price'=>'25000','quantity'=>1],['unit_price'=>'50000','quantity'=>2],['unit_price'=>'150000','quantity'=>1],['unit_price'=>'2500','quantity'=>10]])===300000,'four categories');ck(P::total([['unit_price'=>'0','quantity'=>1]])===0,'zero');ck(P::total([['unit_price'=>(string)P::MAX,'quantity'=>1]])===P::MAX,'max');ck(rejects(fn()=>P::total([['unit_price'=>(string)P::MAX,'quantity'=>2]]),'INVALID_TOTAL'),'overflow');ck(rejects(fn()=>P::total([['unit_price'=>(string)P::MAX,'quantity'=>1],['unit_price'=>'1','quantity'=>1]]),'INVALID_TOTAL'),'sum overflow');
foreach(['all','unpaid','partial','paid','unissued'] as $s)ck(P::filters(['status'=>$s,'page'=>'2'])['status']===$s,'filter');foreach([['status'=>'bad'],['page'=>0],['per_page'=>101],['amount'=>'1']] as $f)ck(rejects(fn()=>P::filters($f),'VALIDATION_ERROR'),'bad filter');
echo "PASS: $n billing PHP assertions\n";
}
