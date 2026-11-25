<?php
namespace yii\web { class HttpException extends \Exception {public $statusCode;public function __construct($status,$message=null){$this->statusCode=$status;parent::__construct($message??'');}} }
namespace {
require __DIR__.'/../components/ApiException.php';
require __DIR__.'/../components/RolePolicy.php';
require __DIR__.'/../components/MasterPolicy.php';
require __DIR__.'/../components/AncillaryPolicy.php';
use app\components\{ApiException,RolePolicy,MasterPolicy};
$n=0;function check($value){global $n;if(!$value)throw new \RuntimeException('QA policy failure #'.($n+1));$n++;}
foreach(['a',str_repeat('x',72),str_repeat('é',36)] as $p)check(RolePolicy::validLoginPassword($p));
foreach(['',str_repeat('a',73),str_repeat('é',37),"abc\0def",null,12,[]] as $p)check(!RolePolicy::validLoginPassword($p));
$key='ABCD0000-1234-4567-89AB-0123456789AB';$body=['delta'=>5,'reason'=>'  dummy  '];$b=MasterPolicy::adjustment($body,$key);check($b['key']===strtolower($key));check($b['reason']==='dummy');check($b['delta']===5);
check(MasterPolicy::adjustment(['delta'=>-1000000,'reason'=>'dummy'],$key)['delta']===-1000000);
check(MasterPolicy::adjustmentHash('1',$b)===MasterPolicy::adjustmentHash('1',MasterPolicy::adjustment(['delta'=>5,'reason'=>'dummy'],strtolower($key))));
check(MasterPolicy::adjustmentHash('1',$b)!==MasterPolicy::adjustmentHash('2',$b));
check(MasterPolicy::adjustmentHash('1',$b)!==MasterPolicy::adjustmentHash('1',['delta'=>6,'reason'=>'dummy']));
check(MasterPolicy::adjustmentHash('1',$b)!==MasterPolicy::adjustmentHash('1',['delta'=>5,'reason'=>'other']));
foreach([[['delta'=>0,'reason'=>'dummy'],$key],[['delta'=>1000001,'reason'=>'dummy'],$key],[['delta'=>'5','reason'=>'dummy'],$key],[['delta'=>5,'reason'=>''],$key],[['delta'=>5,'reason'=>"x\0y"],$key],[$body,null],[$body,'bad'],[$body+['stock'=>5],$key]] as [$input,$k]){
 try{MasterPolicy::adjustment($input,$k);throw new \RuntimeException('Expected rejection');}catch(ApiException $e){check($e->statusCode===422);}
}
check(MasterPolicy::id('9223372036854775807'));check(!MasterPolicy::id('9223372036854775808'));
echo "PASS: $n QA PHP assertions\n";
}
