<?php
namespace app\services;
use app\components\{Api,ApiException,MasterPolicy,RolePolicy};
use yii\db\{Query,IntegrityException};

final class MasterService
{
    private function db() {return \Yii::$app->db;}
    private function notFound(): never {throw new ApiException(404,'NOT_FOUND','Data tidak ditemukan.');}
    private function conflict(string $message): never {throw new ApiException(409,'CONFLICT',$message);}
    private function dto(array $row): array
    {
        foreach (['id','doctor_id','clinic_id','price','registration_fee'] as $k) if (isset($row[$k])) $row[$k]=(string)$row[$k];
        foreach (['weekday','stock'] as $k) if(isset($row[$k])) $row[$k]=(int)$row[$k];
        foreach (['active','doctor_active','clinic_active'] as $k) if(isset($row[$k])) $row[$k]=RolePolicy::active($row[$k]);
        return $row;
    }
    private function query(string $r,array $actor): Query
    {
        $q=(new Query())->from(['m'=>MasterPolicy::get($r)['table']])->select('m.*');
        if ($r==='patients'&&$actor['role']==='doctor') {
            $q->andWhere(['exists',(new Query())->from(['v'=>'visits'])->where('v.patient_id=m.id')->andWhere(['v.doctor_id'=>$actor['staff_id']])]);
            // Only identifiers/demographics needed by the treating doctor.
            $q->select(['m.id','m.medical_record_no','m.name','m.birth_date','m.sex','m.active']);
        }
        if ($r==='doctor-schedules') $q->leftJoin(['d'=>'medical_staff'],'d.id=m.doctor_id')->leftJoin(['c'=>'clinics'],'c.id=m.clinic_id')->addSelect(['doctor_name'=>'d.name','clinic_name'=>'c.name','doctor_active'=>'d.active','clinic_active'=>'c.active']);
        return $q;
    }
    public function list(string $r,array $actor,array $filters): array
    {
        $def=MasterPolicy::get($r); $q=$this->query($r,$actor);
        $allowed=['page','per_page','q','active']; if($r==='medical-staff')$allowed[]='profession';if($r==='doctor-schedules')$allowed=[...$allowed,'doctor_id','clinic_id','date','weekday'];
        if(array_diff(array_keys($filters),$allowed))throw new ApiException(422,'VALIDATION_ERROR','Filter tidak didukung.');
        foreach($filters as $v) if(!is_string($v)&&!is_int($v))throw new ApiException(422,'VALIDATION_ERROR','Filter tidak valid.');
        foreach(['page'=>100000,'per_page'=>100] as $k=>$max){$v=(string)($filters[$k]??($k==='page'?1:20));if(!preg_match('/\A[1-9][0-9]{0,5}\z/D',$v)||(int)$v>$max)throw new ApiException(422,'VALIDATION_ERROR','Pagination tidak valid.');$$k=(int)$v;}
        if(isset($filters['q'])){ $term=trim((string)$filters['q']);if(strlen($term)>200||str_contains($term,"\0"))throw new ApiException(422,'VALIDATION_ERROR','Pencarian terlalu panjang.');if($term!==''){$cols=$r==='doctor-schedules'?['d.name','c.name']:array_map(fn($c)=>'m.'.$c,$def['search']);if($r==='patients'&&$actor['role']==='doctor')$cols=['m.name','m.medical_record_no'];$or=['or'];foreach($cols as $c)$or[]=['ilike',$c,$term];$q->andWhere($or);}}
        if(isset($filters['active'])){if(!in_array((string)$filters['active'],['true','false','1','0'],true))throw new ApiException(422,'VALIDATION_ERROR','Filter status tidak valid.');$q->andWhere(['m.active'=>in_array((string)$filters['active'],['true','1'],true)]);}
        if(isset($filters['profession'])){if(!in_array($filters['profession'],['doctor','nurse','other'],true))throw new ApiException(422,'VALIDATION_ERROR','Profesi tidak valid.');$q->andWhere(['m.profession'=>$filters['profession']]);}
        foreach(['doctor_id','clinic_id'] as $k) if(isset($filters[$k])){if(!MasterPolicy::id((string)$filters[$k]))throw new ApiException(422,'VALIDATION_ERROR','ID filter tidak valid.');$q->andWhere(['m.'.$k=>(string)$filters[$k]]);}
        if(isset($filters['date'])){if(!MasterPolicy::date($filters['date']))throw new ApiException(422,'VALIDATION_ERROR','Tanggal tidak valid.');$q->andWhere(['m.weekday'=>(int)(new \DateTimeImmutable($filters['date']))->format('N'),'m.active'=>true,'d.active'=>true,'c.active'=>true]);}
        if(isset($filters['weekday'])){if(!preg_match('/\A[1-7]\z/D',(string)$filters['weekday']))throw new ApiException(422,'VALIDATION_ERROR','Hari tidak valid.');$q->andWhere(['m.weekday'=>(int)$filters['weekday']]);}
        $total=(int)(clone $q)->count();$rows=$q->orderBy(['m.id'=>SORT_DESC])->limit($per_page)->offset(($page-1)*$per_page)->all();
        return ['data'=>array_map(fn($x)=>$this->dto($x),$rows),'meta'=>['page'=>$page,'per_page'=>$per_page,'total'=>$total,'request_id'=>Api::id()]];
    }
    public function get(string $r,string $id,array $actor): array
    { if(!MasterPolicy::id($id))$this->notFound();$row=$this->query($r,$actor)->andWhere(['m.id'=>$id])->one();if(!$row)$this->notFound();return $this->dto($row); }
    private function lock(string $table,string $id): array
    { if(!MasterPolicy::id($id))$this->notFound();$row=$this->db()->createCommand('SELECT * FROM '.$table.' WHERE id=:id FOR UPDATE',[':id'=>$id])->queryOne();if(!$row)$this->notFound();return $row; }
    private function audit(array $actor,string $r,string $id,string $action,?array $before,?array $after,?string $reason=null): void
    {
        // Patient administrative details are not duplicated in the audit trail.
        if($r==='patients'){foreach(['before','after'] as $var)if($$var!==null)$$var=array_intersect_key($$var,array_flip(['id','medical_record_no','active']));}
        $this->db()->createCommand()->insert('audit_logs',['actor_id'=>$actor['id'],'action'=>$action,'entity_type'=>$r,'entity_id'=>$id,'before_data'=>$before===null?null:new \yii\db\JsonExpression($before, 'jsonb'),'after_data'=>$after===null?null:new \yii\db\JsonExpression($after, 'jsonb'),'reason'=>$reason,'request_id'=>Api::id()])->execute();
    }
    private function guarded(callable $fn): mixed
    {
        try{return $this->db()->transaction(function()use($fn){$this->db()->createCommand('SELECT pg_advisory_xact_lock(682361,1)')->queryScalar();return $fn();});}
        catch(IntegrityException $e){$state=$e->errorInfo[0]??'';if(in_array($state,['23505','23503','23514'],true))$this->conflict('Data duplikat, terkait transaksi lain, atau melanggar aturan data.');throw $e;}
    }
    private function schedule(array $row,?array $before): void
    {
        $doctor=$this->lock('medical_staff',(string)$row['doctor_id']);$clinic=$this->lock('clinics',(string)$row['clinic_id']);
        if($doctor['profession']!=='doctor'||(RolePolicy::active($row['active']??true)&&(!RolePolicy::active($doctor['active'])||!RolePolicy::active($clinic['active']))))$this->conflict('Dokter atau poli tidak tersedia.');
        if(strcmp(str_pad($row['start_time'],8,':00'),str_pad($row['end_time'],8,':00'))>=0)throw new ApiException(422,'VALIDATION_ERROR','Jam selesai harus setelah jam mulai.');
        if($before&&((string)$before['doctor_id']!==(string)$row['doctor_id']||(string)$before['clinic_id']!==(string)$row['clinic_id'])&&(new Query())->from('visits')->where(['schedule_id'=>$before['id']])->exists())$this->conflict('Dokter dan poli pada jadwal yang telah digunakan tidak dapat diubah.');
        if(RolePolicy::active($row['active']??true)){$q=(new Query())->from('doctor_schedules')->where(['doctor_id'=>$row['doctor_id'],'weekday'=>$row['weekday'],'active'=>true])->andWhere(['<','start_time',$row['end_time']])->andWhere(['>','end_time',$row['start_time']]);if($before)$q->andWhere(['<>','id',$before['id']]);if($q->exists())$this->conflict('Jadwal dokter bertabrakan dengan jadwal aktif lainnya.');}
    }
    public function save(string $r,?string $id,array $input,array $actor): array
    {
        $table=MasterPolicy::get($r)['table'];$values=MasterPolicy::validate($r,$input,$id===null);
        return $this->guarded(function()use($r,$table,$id,$values,$actor){
            $before=$id===null?null:$this->lock($table,$id);$row=array_replace($before??['active'=>true],$values);
            if($r==='doctor-schedules')$this->schedule($row,$before);
            if($r==='medical-staff'&&$before&&($row['profession']!=='doctor'||!RolePolicy::active($row['active']))){if((new Query())->from('users')->where(['staff_id'=>$id,'role'=>'doctor','active'=>true])->exists())$this->conflict('Nonaktifkan akun dokter terkait sebelum mengubah data ini.');if((new Query())->from('doctor_schedules')->where(['doctor_id'=>$id,'active'=>true])->exists())$this->conflict('Nonaktifkan jadwal dokter terlebih dahulu.');}
            if($r==='clinics'&&$before&&!RolePolicy::active($row['active'])&&(new Query())->from('doctor_schedules')->where(['clinic_id'=>$id,'active'=>true])->exists())$this->conflict('Nonaktifkan jadwal poli terlebih dahulu.');
            if($r==='patients'&&$id===null){$n=(string)$this->db()->createCommand("SELECT nextval('medical_record_no_seq')")->queryScalar();$values['medical_record_no']='RM-'.str_pad($n,6,'0',STR_PAD_LEFT);}
            if($id===null&&isset(MasterPolicy::CODE_SEQUENCES[$r])&&!isset($values['code'])){
                // Whitelisted sequence + serialized master writes; skip legacy/manual collisions.
                $seq=MasterPolicy::CODE_SEQUENCES[$r];
                do{$n=(string)$this->db()->createCommand("SELECT nextval('$seq')")->queryScalar();$code=MasterPolicy::generatedCode($r,$n,$values['profession']??null);}while((new Query())->from($table)->where(['code'=>$code])->exists());
                $values['code']=$code;
            }
            if($id===null){$this->db()->createCommand()->insert($table,$values)->execute();$id=(string)$this->db()->createCommand("SELECT currval(pg_get_serial_sequence(:table,'id'))",[':table'=>$table])->queryScalar();}else{$this->db()->createCommand()->update($table,$values,['id'=>$id])->execute();}
            $after=$this->get($r,$id,$actor);$this->audit($actor,$r,$id,$before?'update':'create',$before?$this->dto($before):null,$after);return $after;
        });
    }
    public function delete(string $r,string $id,array $actor,bool $permanent=false): ?array
    {
        $table=MasterPolicy::get($r)['table'];
        return $this->guarded(function()use($r,$table,$id,$actor,$permanent){$before=$this->lock($table,$id);
            if($r==='medicines'&&(int)$before['stock']!==0)$this->conflict('Rekonsiliasi stok menjadi nol sebelum menghapus obat.');
            // Savepoint keeps the outer transaction usable after a foreign key rejection.
            $nested=$this->db()->beginTransaction();try{$this->db()->createCommand()->delete($table,['id'=>$id])->execute();$nested->commit();}
            
            catch(IntegrityException $e){$nested->rollBack();if(($e->errorInfo[0]??'')!=='23503')throw $e;if($permanent)$this->conflict('Data sudah terkait transaksi dan tidak dapat dihapus permanen. Gunakan nonaktifkan.');return $this->save($r,$id,['active'=>false],$actor);}
            $this->audit($actor,$r,$id,'delete',$this->dto($before),null);return null;
        });
    }
    public function adjustStock(string $id,array $input,array $actor,mixed $key): array
    {
        $b=MasterPolicy::adjustment($input,$key);if(!MasterPolicy::id($id))$this->notFound();$hash=MasterPolicy::adjustmentHash($id,$b);
        // The exclusive master guard serializes all adjustments and coordinates with dispensing.
        return $this->guarded(function()use($id,$b,$hash,$actor){
            $db=$this->db();$before=$this->lock('medicines',$id);
            $old=(new Query())->from('stock_adjustment_requests')->where(['event_key'=>$b['key']])->one();
            if($old){
                if((string)$old['medicine_id']!==$id||(string)$old['actor_id']!==(string)$actor['id']||!hash_equals($old['payload_hash'],$hash))throw new ApiException(409,'IDEMPOTENCY_CONFLICT','Key penyesuaian stok telah digunakan untuk transaksi lain.');
                return $this->get('medicines',$id,$actor)+['replayed'=>true,'movement_id'=>(string)$old['movement_id']];
            }
            $delta=$b['delta'];$balance=(int)$before['stock']+$delta;
            if($balance<0||$balance>2147483647)$this->conflict('Stok tidak mencukupi atau melebihi batas.');
            $db->createCommand()->update('medicines',['stock'=>$balance],['id'=>$id])->execute();
            $movement=(string)$db->createCommand("INSERT INTO stock_movements(medicine_id,kind,quantity_delta,balance_after,reason,actor_id) VALUES (:medicine,'adjustment',:delta,:balance,:reason,:actor) RETURNING id",[':medicine'=>$id,':delta'=>$delta,':balance'=>$balance,':reason'=>$b['reason'],':actor'=>$actor['id']])->queryScalar();
            $db->createCommand()->insert('stock_adjustment_requests',['event_key'=>$b['key'],'medicine_id'=>$id,'actor_id'=>$actor['id'],'payload_hash'=>$hash,'movement_id'=>$movement])->execute();
            $after=$this->get('medicines',$id,$actor);$this->audit($actor,'medicines',$id,'adjust_stock',$this->dto($before),$after,$b['reason']);
            return $after+['replayed'=>false,'movement_id'=>$movement];
        });
    }
}
