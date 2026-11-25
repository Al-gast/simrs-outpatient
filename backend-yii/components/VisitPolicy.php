<?php
namespace app\components;

final class VisitPolicy
{
    public const READ=['admin','registration','doctor'];
    public const HISTORY=['registration','doctor'];
    public const STATUSES=['waiting','in_exam','services_pending','ready_for_billing','completed'];
    public static function today(): string {return (new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d');}
    public static function registration(array $body): array
    {
        $errors=[];
        foreach(array_diff(array_keys($body),['patient_id','schedule_id']) as $key)$errors[$key]='Field tidak diizinkan.';
        foreach(['patient_id','schedule_id'] as $key)if(!MasterPolicy::id($body[$key]??null))$errors[$key]='ID wajib berupa string numerik positif.';
        if($errors)throw new ApiException(422,'VALIDATION_ERROR','Periksa data pendaftaran.',$errors);
        return ['patient_id'=>$body['patient_id'],'schedule_id'=>$body['schedule_id']];
    }
    public static function filters(array $input,bool $history=false): array
    {
        $allowed=['page','per_page','q','date','clinic_id','doctor_id','status'];
        if(array_diff(array_keys($input),$allowed))self::invalid();
        foreach($input as $v)if(!is_string($v)&&!is_int($v))self::invalid();
        $out=[];
        foreach(['page'=>100000,'per_page'=>100] as $key=>$max){$v=(string)($input[$key]??($key==='page'?1:20));if(!preg_match('/\A[1-9][0-9]{0,5}\z/D',$v)||(int)$v>$max)self::invalid();$out[$key]=(int)$v;}
        if(isset($input['q'])){$q=trim((string)$input['q']);if(strlen($q)>200||str_contains($q,"\0"))self::invalid();if($q!=='')$out['q']=$q;}
        foreach(['clinic_id','doctor_id'] as $key)if(isset($input[$key])){if(!MasterPolicy::id($input[$key]))self::invalid();$out[$key]=$input[$key];}
        if(isset($input['status'])){if(!in_array($input['status'],self::STATUSES,true))self::invalid();$out['status']=$input['status'];}
        if(isset($input['date'])){if(!MasterPolicy::date($input['date']))self::invalid();$out['date']=$input['date'];}elseif(!$history)$out['date']=self::today();
        return $out;
    }
    private static function invalid(): never {throw new ApiException(422,'VALIDATION_ERROR','Filter atau pagination tidak valid.');}
    public static function assigned(array $actor,array $row): bool {return $actor['role']==='doctor'&&isset($actor['staff_id'],$row['doctor_id'])&&(string)$actor['staff_id']===(string)$row['doctor_id'];}
    public static function scheduleAvailable(array $row,string $date): bool
    {return RolePolicy::active($row['active'])&&RolePolicy::active($row['doctor_active'])&&RolePolicy::active($row['clinic_active'])&&$row['profession']==='doctor'&&(int)$row['weekday']===(int)(new \DateTimeImmutable($date))->format('N');}
    public static function startOutcome(string $status): string {return match($status){'waiting'=>'transition','in_exam'=>'replay',default=>'conflict'};}
}
