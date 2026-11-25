<?php
namespace app\services;
use app\components\{AdminPolicy,Api,ApiException,MasterPolicy,RolePolicy};
use yii\db\{Query,IntegrityException,Expression};
final class AdminService
{
    private function db(){return \Yii::$app->db;}
    private function dto(array $row): array {return RolePolicy::publicUser($row)+['active'=>RolePolicy::active($row['active']),'version'=>(int)$row['token_version']];}
    private function accountQuery(): Query {return (new Query())->from('users')->select(['id','username','name','role','staff_id','active','token_version']);}
    private function page(Query $q,array $f,array $order,callable $map): array
    {
        $total=(int)(clone $q)->count('*',$this->db());$rows=$q->orderBy($order)->limit($f['per_page'])->offset(($f['page']-1)*$f['per_page'])->all($this->db());
        return ['data'=>array_map($map,$rows),'meta'=>['page'=>$f['page'],'per_page'=>$f['per_page'],'total'=>$total,'request_id'=>Api::id()]];
    }
    public function users(array $filters): array
    {
        $f=AdminPolicy::filters($filters,false);$q=(new Query())->from(['u'=>'users'])->leftJoin(['s'=>'medical_staff'],'s.id=u.staff_id')->select(['u.id','u.username','u.name','u.role','u.staff_id','u.active','u.token_version','staff_name'=>'s.name']);
        if(isset($f['q'])&&trim($f['q'])!=='')$q->andWhere(['or',['ilike','u.username',trim($f['q'])],['ilike','u.name',trim($f['q'])]]);
        if(isset($f['role']))$q->andWhere(['u.role'=>$f['role']]);if(isset($f['active']))$q->andWhere(['u.active'=>$f['active']==='true']);
        return $this->page($q,$f,['u.id'=>SORT_DESC],fn($r)=>$this->dto($r)+['staff_name'=>$r['staff_name']]);
    }
    public function audit(array $filters): array
    {
        $f=AdminPolicy::filters($filters,true);
        // Explicit projection: no reason, before_data, after_data, patient identity, or password.
        $q=(new Query())->from(['a'=>'audit_logs'])->innerJoin(['u'=>'users'],'u.id=a.actor_id')->select(['a.id','a.actor_id','actor_username'=>'u.username','a.action','a.entity_type','a.entity_id','a.request_id','a.created_at']);
        $q->andWhere(new Expression("a.created_at >= (CAST(:from AS date)::timestamp AT TIME ZONE 'Asia/Jakarta') AND a.created_at < ((CAST(:to AS date)+1)::timestamp AT TIME ZONE 'Asia/Jakarta')",[':from'=>$f['from'],':to'=>$f['to']]));
        foreach(['actor_id','action','entity_type'] as $k)if(isset($f[$k]))$q->andWhere(['a.'.$k=>$f[$k]]);
        return $this->page($q,$f,['a.created_at'=>SORT_DESC,'a.id'=>SORT_DESC],static function($r){foreach(['id','actor_id','entity_id'] as $k)$r[$k]=(string)$r[$k];$r['created_at']=(new \DateTimeImmutable($r['created_at']))->format(DATE_ATOM);return $r;});
    }
    public function save(array $actor,?string $id,array $input): array
    {
        $v=AdminPolicy::account($input,$id===null);if($id!==null&&!MasterPolicy::id($id))throw new ApiException(404,'NOT_FOUND','Akun tidak ditemukan.');
        try{return $this->db()->transaction(function()use($actor,$id,$v){
            // Same global master lock as MasterService: staff changes cannot race account binding.
            $db=$this->db();$db->createCommand('SELECT pg_advisory_xact_lock(682361,1)')->queryScalar();
            $current=$db->createCommand('SELECT id,role,active,token_version FROM users WHERE id=:id FOR UPDATE',[':id'=>$actor['id']])->queryOne();
            if(!$current||$current['role']!=='admin'||!RolePolicy::active($current['active'])||(int)$current['token_version']!==(int)$actor['token_version'])throw new ApiException(401,'UNAUTHORIZED','Sesi administrator telah berubah.');
            $before=null;
            if($id!==null){$before=$db->createCommand('SELECT id,username,name,role,staff_id,active,token_version FROM users WHERE id=:id FOR UPDATE',[':id'=>$id])->queryOne();if(!$before)throw new ApiException(404,'NOT_FOUND','Akun tidak ditemukan.');AdminPolicy::protect($actor,$before,$v);}
            if($v['role']==='doctor'){
                $staff=$db->createCommand('SELECT profession,active FROM medical_staff WHERE id=:id FOR UPDATE',[':id'=>$v['staff_id']])->queryOne();
                if(!$staff||($v['active']&&($staff['profession']!=='doctor'||!RolePolicy::active($staff['active'])))||(!$before&&$staff['profession']!=='doctor')||($before&&$before['role']!=='doctor'&&$staff['profession']!=='doctor'))throw new ApiException(409,'INVALID_STAFF','Tautan dokter tidak valid atau tidak aktif.');
            }
            $values=array_intersect_key($v,array_flip(['name','role','staff_id','active','username']));
            if(isset($v['password']))$values['password_hash']=password_hash($v['password'],PASSWORD_DEFAULT);
            if($id===null){$db->createCommand()->insert('users',$values)->execute();$id=(string)$db->createCommand("SELECT currval(pg_get_serial_sequence('users','id'))")->queryScalar();}
            else{$values['token_version']=new Expression('token_version+1');$db->createCommand()->update('users',$values,['id'=>$id])->execute();}
            $after=$this->dto($this->accountQuery()->where(['id'=>$id])->one($db));
            // Password and hash never enter audit. Only record whether password was reset.
            $db->createCommand()->insert('audit_logs',['actor_id'=>$actor['id'],'entity_type'=>'users','entity_id'=>$id,'action'=>$before?(isset($v['password'])?'reset_user_password':'update_user'):'create_user','before_data'=>$before?new \yii\db\JsonExpression($this->dto($before), 'jsonb'):null,'after_data'=>new \yii\db\JsonExpression($after, 'jsonb'),'request_id'=>Api::id()])->execute();
            return $after;
        });}catch(IntegrityException $e){if(in_array($e->errorInfo[0]??'',['23505','23503','23514'],true))throw new ApiException(409,'ACCOUNT_CONFLICT','Username atau tenaga medis telah digunakan, atau data tidak valid.');throw $e;}
    }
}
