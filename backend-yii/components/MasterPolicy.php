<?php
namespace app\components;

/** Pure resource definitions; no request-controlled SQL identifiers. */
final class MasterPolicy
{
    public const RESOURCES = [
        'patients'=>['table'=>'patients','fields'=>['identity_no','name','birth_date','sex','phone','address'],'search'=>['medical_record_no','identity_no','name'],'read'=>['admin','registration','doctor']],
        'medical-staff'=>['table'=>'medical_staff','fields'=>['code','name','profession'],'search'=>['code','name'],'read'=>['admin','registration','doctor']],
        'clinics'=>['table'=>'clinics','fields'=>['code','name','registration_fee'],'search'=>['code','name'],'read'=>['admin','registration','doctor']],
        'doctor-schedules'=>['table'=>'doctor_schedules','fields'=>['doctor_id','clinic_id','weekday','start_time','end_time'],'search'=>[],'read'=>['admin','registration','doctor']],
        'procedures'=>['table'=>'procedures','fields'=>['code','name','price'],'search'=>['code','name'],'read'=>['admin','doctor']],
        'medicines'=>['table'=>'medicines','fields'=>['code','name','unit','price'],'search'=>['code','name'],'read'=>['admin','doctor','pharmacy']],
        'lab-tests'=>['table'=>'lab_tests','fields'=>['code','name','unit','price'],'search'=>['code','name'],'read'=>['admin','doctor','laboratory']],
    ];
    public const CODE_SEQUENCES=['medical-staff'=>'staff_code_seq','clinics'=>'clinic_code_seq','procedures'=>'procedure_code_seq','medicines'=>'medicine_code_seq','lab-tests'=>'lab_test_code_seq'];
    public static function generatedCode(string $resource,string $number,?string $profession=null): string
    {
        if(!self::id($number))throw new \InvalidArgumentException('Invalid code sequence');
        $prefix=match($resource){'medical-staff'=>match($profession){'doctor'=>'D','nurse'=>'N','other'=>'S',default=>throw new \InvalidArgumentException('Invalid profession')},'clinics'=>'POL-','procedures'=>'T','medicines'=>'OB','lab-tests'=>'L',default=>throw new \InvalidArgumentException('Invalid code resource')};
        return $prefix.str_pad($number,6,'0',STR_PAD_LEFT);
    }
    public static function get(string $resource): array
    { return self::RESOURCES[$resource] ?? throw new ApiException(404,'NOT_FOUND','Master data tidak ditemukan.'); }
    public static function writeRoles(string $resource): array { return $resource==='patients'?['admin','registration']:['admin']; }
    public static function id(mixed $v): bool { return is_string($v)&&preg_match('/\A[1-9][0-9]{0,18}\z/D',$v)&& (strlen($v)<19||strcmp($v,'9223372036854775807')<=0); }
    public static function date(mixed $v): bool { return is_string($v)&&preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/D',$v,$m)&&checkdate((int)$m[2],(int)$m[3],(int)$m[1]); }
    public static function money(mixed $v): bool { return is_string($v)&&preg_match('/\A(?:0|[1-9][0-9]{0,11})\z/D',$v); }
    public static function time(mixed $v): bool { return is_string($v)&&preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/D',$v); }
    public static function adjustment(array $input,mixed $key): array
    {
        $delta=$input['delta']??null;$reason=$input['reason']??null;
        if(array_diff(array_keys($input),['delta','reason'])||!is_int($delta)||$delta===0||abs($delta)>1000000||!is_string($reason)||trim($reason)===''||strlen($reason)>500||str_contains($reason,"\0"))throw new ApiException(422,'VALIDATION_ERROR','Masukkan perubahan stok dan alasan yang valid.');
        return ['delta'=>$delta,'reason'=>trim($reason),'key'=>AncillaryPolicy::eventKey($key)];
    }
    public static function adjustmentHash(string $id,array $body): string
    {return hash('sha256',json_encode([$id,$body['delta'],$body['reason']],JSON_THROW_ON_ERROR));}
    public static function validate(string $resource,array $input,bool $create): array
    {
        $fields=self::get($resource)['fields']; $allowed=[...$fields,'active']; $errors=[]; $out=[];
        foreach ($input as $k=>$v) {
            if (!in_array($k,$allowed,true)) {$errors[$k]='Field tidak diizinkan.';continue;}
            $nullable=in_array($k,['identity_no','phone','address'],true)||($resource==='lab-tests'&&$k==='unit');
            if ($nullable&&($v===null||(is_string($v)&&trim($v)===''))) {$out[$k]=null;continue;}
            $ok=match($k){
                'active'=>is_bool($v), 'doctor_id','clinic_id'=>self::id($v), 'weekday'=>is_int($v)&&$v>=1&&$v<=7,
                'birth_date'=>self::date($v)&&$v<=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d'),
                'sex'=>in_array($v,['M','F','U'],true),'profession'=>in_array($v,['doctor','nurse','other'],true),
                'price','registration_fee'=>self::money($v),'start_time','end_time'=>self::time($v),
                'code'=>is_string($v)&&preg_match('/\A[A-Za-z0-9_.-]{1,40}\z/D',trim($v)),
                default=>is_string($v)&&trim($v)!==''&&strlen($v)<=($k==='address'?1000:($k==='name'?200:80))&&!str_contains($v,"\0"),
            };
            if (!$ok) {$errors[$k]='Nilai tidak valid.';continue;}
            $out[$k]=is_string($v)?($k==='code'?strtoupper(trim($v)):trim($v)):$v;
        }
        if ($create) foreach ($fields as $k) if (!($k==='code'&&isset(self::CODE_SEQUENCES[$resource]))&&!array_key_exists($k,$out)&&!in_array($k,['identity_no','phone','address'],true)&&!($resource==='lab-tests'&&$k==='unit')) $errors[$k]??='Wajib diisi.';
        if (!$input) $errors['_body']='Tidak ada data untuk disimpan.';
        if ($errors) throw new ApiException(422,'VALIDATION_ERROR','Periksa data yang dimasukkan.',$errors);
        return $out;
    }
}
