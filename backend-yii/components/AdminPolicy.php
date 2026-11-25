<?php
namespace app\components;
final class AdminPolicy
{
    private static function invalid(string $message): never {throw new ApiException(422,'VALIDATION_ERROR',$message);}
    public static function account(array $input,bool $create): array
    {
        $required=['name','role','staff_id','active'];
        $allowed=[...$required,'password', $create?'username':'expected_version'];
        if(array_diff(array_keys($input),$allowed)||array_diff($required,array_keys($input)))self::invalid('Field akun tidak lengkap atau tidak diizinkan.');
        if(!is_string($input['name'])||trim($input['name'])===''||strlen($input['name'])>200||str_contains($input['name'],"\0"))self::invalid('Nama tidak valid.');
        if(!is_string($input['role'])||!RolePolicy::permits($input['role'],RolePolicy::ROLES)||!is_bool($input['active']))self::invalid('Role atau status tidak valid.');
        if($input['role']==='doctor'?!MasterPolicy::id($input['staff_id']):$input['staff_id']!==null)self::invalid('Akun dokter wajib ditautkan ke tenaga medis dokter; role lain tidak menggunakan tautan.');
        if($create&&(!is_string($input['username']??null)||!preg_match('/\A[a-z0-9_.-]{1,80}\z/D',$input['username'])))self::invalid('Username wajib huruf kecil, angka, titik, garis bawah atau strip.');
        if(!$create&&(!is_int($input['expected_version']??null)||$input['expected_version']<0||$input['expected_version']>=2147483647))self::invalid('Versi akun tidak valid.');
        if($create||array_key_exists('password',$input)){$p=$input['password']??null;if(!RolePolicy::validLoginPassword($p)||strlen($p)<12)self::invalid('Password wajib 12–72 byte, tanpa karakter NUL.');}
        $input['name']=trim($input['name']);return $input;
    }
    public static function protect(array $actor,array $before,array $input): void
    {
        if((string)$actor['id']===(string)$before['id'])throw new ApiException(409,'SELF_EDIT','Akun sendiri tidak dapat diubah melalui manajemen pengguna. Gunakan administrator lain.');
        if((int)$before['token_version']!==$input['expected_version'])throw new ApiException(409,'STALE_USER','Akun telah berubah. Muat ulang data.');
        if($before['role']==='doctor'&&($input['role']!=='doctor'||(string)$before['staff_id']!==$input['staff_id']))throw new ApiException(409,'DOCTOR_IDENTITY_LOCKED','Identitas dokter yang sudah ditautkan tidak dapat dipindahkan. Nonaktifkan akun jika diperlukan.');
    }
    public static function filters(array $f,bool $audit): array
    {
        $allowed=$audit?['page','per_page','from','to','actor_id','action','entity_type']:['page','per_page','q','role','active'];
        if(array_diff(array_keys($f),$allowed))self::invalid('Filter tidak didukung.');
        foreach($f as $v)if(!is_string($v)&&!is_int($v))self::invalid('Filter tidak valid.');
        foreach(['page'=>10000,'per_page'=>100] as $k=>$max){$v=(string)($f[$k]??($k==='page'?1:20));if(!preg_match('/\A[1-9][0-9]{0,5}\z/D',$v)||(int)$v>$max)self::invalid('Pagination tidak valid.');$f[$k]=(int)$v;}
        if($audit){
            foreach(['from','to'] as $k)if(!MasterPolicy::date($f[$k]??null)||!preg_match('/\A20\d\d-/',$f[$k]))self::invalid('Periode audit wajib valid.');
            $a=new \DateTimeImmutable($f['from'],new \DateTimeZone('Asia/Jakarta'));$b=new \DateTimeImmutable($f['to'],new \DateTimeZone('Asia/Jakarta'));
            if($a>$b||$a->diff($b)->days>365)self::invalid('Periode audit maksimal 366 hari.');
            if(isset($f['actor_id'])&&!MasterPolicy::id($f['actor_id']))self::invalid('ID petugas tidak valid.');
            foreach(['action','entity_type'] as $k)if(isset($f[$k])&&(!is_string($f[$k])||!preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/D',$f[$k])))self::invalid('Filter audit tidak valid.');
        }else{
            if(isset($f['role'])&&(!is_string($f['role'])||!RolePolicy::permits($f['role'],RolePolicy::ROLES)))self::invalid('Role tidak valid.');
            if(isset($f['active'])&&!in_array($f['active'],['true','false'],true))self::invalid('Status tidak valid.');
            if(isset($f['q'])&&(!is_string($f['q'])||strlen($f['q'])>200||str_contains($f['q'],"\0")))self::invalid('Pencarian tidak valid.');
        }
        return $f;
    }
}
