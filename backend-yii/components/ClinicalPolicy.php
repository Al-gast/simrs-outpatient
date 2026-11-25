<?php
namespace app\components;

/** Input validation and state rules, independent of Yii/database. */
final class ClinicalPolicy
{
    public const ROLES=['doctor'];
    public const VITALS=['systolic'=>[30,300],'diastolic'=>[15,200],'pulse'=>[15,300],'temperature'=>[25,45],'respiratory_rate'=>[3,100],'weight'=>[0.2,500]];
    public const TEXT=['complaint'=>1000,'anamnesis'=>2000,'physical_exam'=>2000,'primary_diagnosis'=>300];
    private static function invalid(string $field): never {throw new ApiException(422,'VALIDATION_ERROR','Periksa data pemeriksaan.',[$field=>'Nilai tidak valid.']);}
    private static function keys(array $value,array $allowed): void {if(array_diff(array_keys($value),$allowed))self::invalid('_body');}
    public static function text(mixed $v,int $max,string $field): string
    {if(!is_string($v)||trim($v)===''||strlen($v)>$max||str_contains($v,"\0"))self::invalid($field);return trim($v);}
    public static function optionalReason(mixed $value): ?string
    {return $value===null?null:self::text($value,500,'reason');}
    public static function requireReason(?string $reason,bool $required): void
    {if($required&&$reason===null)self::invalid('reason');}
    public static function version(mixed $v): int
    {if(!is_int($v)||$v<0||$v>=2147483647)self::invalid('expected_version');return $v;}
    public static function record(array $body): array
    {
        self::keys($body,[...array_keys(self::TEXT),'vital_signs','secondary_diagnoses','expected_version','reason']);
        $out=['expected_version'=>self::version($body['expected_version']??null)];
        foreach(self::TEXT as $key=>$max)$out[$key]=self::text($body[$key]??null,$max,$key);
        $v=$body['vital_signs']??null;if(!$v instanceof \stdClass)self::invalid('vital_signs');
        $v=(array)$v;self::keys($v,array_keys(self::VITALS));$out['vital_signs']=[];
        foreach(self::VITALS as $key=>[$min,$max]){$n=$v[$key]??null;if((!is_int($n)&&!is_float($n))||!is_finite((float)$n)||$n<$min||$n>$max)self::invalid('vital_signs.'.$key);$out['vital_signs'][$key]=(float)$n;}
        if($out['vital_signs']['systolic']<$out['vital_signs']['diastolic'])self::invalid('vital_signs.systolic');
        $secondary=$body['secondary_diagnoses']??null;if(!is_array($secondary)||!array_is_list($secondary)||count($secondary)>10)self::invalid('secondary_diagnoses');
        $out['secondary_diagnoses']=[];foreach($secondary as $item)$out['secondary_diagnoses'][]=self::text($item,300,'secondary_diagnoses');
        if(count(array_unique([$out['primary_diagnosis'],...$out['secondary_diagnoses']]))!==count($secondary)+1)self::invalid('secondary_diagnoses');
        $out['reason']=isset($body['reason'])?self::text($body['reason'],500,'reason'):null;
        if($out['expected_version']>0&&$out['reason']===null)self::invalid('reason');return $out;
    }
    public static function procedures(array $body): array
    {
        self::keys($body,['items','expected_version','reason']);$version=self::version($body['expected_version']??null);if($version===0)self::invalid('expected_version');
        $items=$body['items']??null;if(!is_array($items)||!array_is_list($items)||count($items)>30)self::invalid('items');$out=[];$seen=[];
        foreach($items as $item){if(!$item instanceof \stdClass)self::invalid('items');$item=(array)$item;self::keys($item,['procedure_id','quantity']);$id=$item['procedure_id']??null;$qty=$item['quantity']??null;if(!MasterPolicy::id($id)||!is_int($qty)||$qty<1||$qty>100||isset($seen[$id]))self::invalid('items');$seen[$id]=true;$out[]=['procedure_id'=>$id,'quantity'=>$qty];}
        return ['items'=>$out,'expected_version'=>$version,'reason'=>self::optionalReason($body['reason']??null)];
    }
    public static function finish(array $body): int {self::keys($body,['expected_version']);$v=self::version($body['expected_version']??null);if($v===0)self::invalid('expected_version');return $v;}
    public static function checkVersion(int $actual,int $expected): void
    {if($actual!==$expected)throw new ApiException(409,'STALE_RECORD','Data pemeriksaan telah berubah. Muat ulang sebelum menyimpan kembali.');}
    public static function editable(string $status): bool {return $status==='in_exam';}
    public static function diagnosisOnly(string $status): bool {return in_array($status,['services_pending','ready_for_billing','completed'],true);}
    public static function nextStatus(bool $pendingPrescription,bool $pendingLab): string {return $pendingPrescription||$pendingLab?'services_pending':'ready_for_billing';}
    public static function nonDiagnosisUnchanged(array $old,array $new): bool
    {foreach(['complaint','anamnesis','physical_exam'] as $k)if($old[$k]!==$new[$k])return false;foreach(self::VITALS as $k=>$_)if((float)$old['vital_signs'][$k] !== (float)$new['vital_signs'][$k])return false;return true;}
    public static function historyAllowed(array $anchor,array $target,string $today): bool
    {return (string)$anchor['patient_id']===(string)$target['patient_id']&&((string)$anchor['doctor_id']===(string)$target['doctor_id']||($anchor['visit_date']===$today&&$anchor['status']==='in_exam'&&$target['visit_date']<=$today&&$target['status']!=='waiting'));}
}
