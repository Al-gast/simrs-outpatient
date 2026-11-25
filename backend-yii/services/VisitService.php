<?php
namespace app\services;
use app\components\{Api,ApiException,MasterPolicy,RolePolicy,VisitPolicy};
use yii\db\{Query,Exception as DbException};

final class VisitService
{
    private function db(){return \Yii::$app->db;}
    private function missing(): never {throw new ApiException(404,'NOT_FOUND','Data kunjungan atau pasien tidak ditemukan.');}
    private function query(array $actor): Query
    {
        $q=(new Query())->from(['v'=>'visits'])->innerJoin(['p'=>'patients'],'p.id=v.patient_id')->innerJoin(['d'=>'medical_staff'],'d.id=v.doctor_id')->innerJoin(['c'=>'clinics'],'c.id=v.clinic_id')->innerJoin(['s'=>'doctor_schedules'],'s.id=v.schedule_id')->select(['v.id','v.patient_id','v.doctor_id','v.clinic_id','v.schedule_id','v.visit_date','v.queue_number','v.registration_fee','v.status','v.created_at','v.updated_at','patient_name'=>'p.name','p.medical_record_no','doctor_name'=>'d.name','clinic_name'=>'c.name','clinic_code'=>'c.code','s.start_time','s.end_time']);
        if($actor['role']==='doctor')$q->andWhere(['v.doctor_id'=>$actor['staff_id']??null]);
        return $q;
    }
    private function dto(array $row): array
    {
        foreach(['id','patient_id','doctor_id','clinic_id','schedule_id','registration_fee'] as $key)$row[$key]=(string)$row[$key];
        $row['queue_number']=(int)$row['queue_number'];
        $row['queue_label']=$row['clinic_code'].'-'.str_pad((string)$row['queue_number'],3,'0',STR_PAD_LEFT);
        foreach(['created_at','updated_at'] as $key)$row[$key]=(new \DateTimeImmutable($row[$key]))->format(DATE_ATOM);
        return $row;
    }
    public function list(array $actor,array $input,?string $patient=null): array
    {
        $f=VisitPolicy::filters($input,$patient!==null);$q=$this->query($actor);
        if($patient!==null){
            if(!MasterPolicy::id($patient))$this->missing();
            $visible=(new Query())->from('patients')->where(['id'=>$patient]);
            if($actor['role']==='doctor')$visible->andWhere(['exists',(new Query())->from('visits')->where(['patient_id'=>$patient,'doctor_id'=>$actor['staff_id']??null])]);
            if(!$visible->exists())$this->missing();
            $q->andWhere(['v.patient_id'=>$patient]);
        }
        foreach(['date'=>'visit_date','status'=>'status','clinic_id'=>'clinic_id','doctor_id'=>'doctor_id'] as $key=>$column)if(isset($f[$key]))$q->andWhere(['v.'.$column=>$f[$key]]);
        if(isset($f['q'])){$or=['or',['ilike','p.name',$f['q']],['ilike','p.medical_record_no',$f['q']]];if($actor['role']!=='doctor')$or[]=['ilike','p.identity_no',$f['q']];$q->andWhere($or);}
        $total=(int)(clone $q)->count();
        $order=$patient!==null?['v.visit_date'=>SORT_DESC,'v.id'=>SORT_DESC]:['v.visit_date'=>SORT_DESC,'v.clinic_id'=>SORT_ASC,'v.queue_number'=>SORT_ASC,'v.id'=>SORT_ASC];
        $rows=$q->orderBy($order)->limit($f['per_page'])->offset(($f['page']-1)*$f['per_page'])->all();
        return ['data'=>array_map(fn($r)=>$this->dto($r),$rows),'meta'=>['page'=>$f['page'],'per_page'=>$f['per_page'],'total'=>$total,'today'=>VisitPolicy::today(),'request_id'=>Api::id()]];
    }
    public function get(array $actor,string $id): array
    {if(!MasterPolicy::id($id))$this->missing();$row=$this->query($actor)->andWhere(['v.id'=>$id])->one();if(!$row)$this->missing();return $this->dto($row);}
    private function audit(array $actor,string $id,string $action,?array $before,array $after): void
    {$this->db()->createCommand()->insert('audit_logs',['actor_id'=>$actor['id'],'action'=>$action,'entity_type'=>'visits','entity_id'=>$id,'before_data'=>$before===null?null:new \yii\db\JsonExpression($before, 'jsonb'),'after_data'=>new \yii\db\JsonExpression($after, 'jsonb'),'request_id'=>Api::id()])->execute();}
    private function transactional(callable $work): mixed
    {
        for($attempt=0;;$attempt++)try{return $this->db()->transaction($work);}catch(DbException $e){$state=$e->errorInfo[0]??'';if(!in_array($state,['40001','40P01'],true))throw $e;if($attempt>=2)throw new ApiException(503,'RETRY_LATER','Transaksi sibuk. Muat ulang data dan coba kembali.');usleep(20000*($attempt+1));}
    }
    public function register(array $actor,array $input): array
    {
        $body=VisitPolicy::registration($input);
        return $this->transactional(function()use($actor,$body){
            $db=$this->db();
            // Master mutations take the exclusive version of this same transaction-scoped lock.
            // Concurrent registrations share it; per-clinic/day counter serializes numbering only.
            $db->createCommand('SELECT pg_advisory_xact_lock_shared(682361,1)')->queryScalar();
            $date=VisitPolicy::today();
            $p=$db->createCommand('SELECT id,active FROM patients WHERE id=:id',[':id'=>$body['patient_id']])->queryOne();
            if(!$p)$this->missing();if(!RolePolicy::active($p['active']))throw new ApiException(409,'PATIENT_INACTIVE','Pasien tidak aktif.');
            $s=$db->createCommand('SELECT s.*,d.active AS doctor_active,d.profession,c.active AS clinic_active,c.registration_fee FROM doctor_schedules s JOIN medical_staff d ON d.id=s.doctor_id JOIN clinics c ON c.id=s.clinic_id WHERE s.id=:id',[':id'=>$body['schedule_id']])->queryOne();
            if(!$s||!VisitPolicy::scheduleAvailable($s,$date))throw new ApiException(409,'SCHEDULE_UNAVAILABLE','Jadwal dokter atau poli tidak tersedia hari ini.');
            $n=$db->createCommand('INSERT INTO queue_counters(clinic_id,visit_date,last_number) VALUES (:clinic,:date,1) ON CONFLICT(clinic_id,visit_date) DO UPDATE SET last_number=queue_counters.last_number+1 RETURNING last_number',[':clinic'=>$s['clinic_id'],':date'=>$date])->queryScalar();
            try{$id=$db->createCommand('INSERT INTO visits(patient_id,doctor_id,clinic_id,schedule_id,visit_date,queue_number,registration_fee,registered_by) VALUES (:patient,:doctor,:clinic,:schedule,:date,:queue,:fee,:actor) ON CONFLICT(patient_id,clinic_id,visit_date) DO NOTHING RETURNING id',[':patient'=>$body['patient_id'],':doctor'=>$s['doctor_id'],':clinic'=>$s['clinic_id'],':schedule'=>$body['schedule_id'],':date'=>$date,':queue'=>$n,':fee'=>$s['registration_fee'],':actor'=>$actor['id']])->queryScalar();}
            catch(DbException $e){if(($e->errorInfo[0]??'')==='23505')throw new ApiException(409,'QUEUE_CONFLICT','Nomor antrean bentrok. Hubungi administrator untuk rekonsiliasi.');throw $e;}
            if($id===false)throw new ApiException(409,'DUPLICATE_VISIT','Pasien sudah terdaftar di poli ini pada tanggal yang sama. Cari kunjungan sebelumnya.');
            $id=(string)$id;$this->audit($actor,$id,'register',null,['status'=>'waiting','queue_number'=>(int)$n,'clinic_id'=>(string)$s['clinic_id'],'visit_date'=>$date]);
            return $this->get($actor,$id);
        });
    }
    public function start(array $actor,string $id): array
    {
        if(!MasterPolicy::id($id))$this->missing();
        return $this->transactional(function()use($actor,$id){
            $row=$this->db()->createCommand('SELECT id,doctor_id,status FROM visits WHERE id=:id AND doctor_id=:doctor FOR UPDATE',[':id'=>$id,':doctor'=>$actor['staff_id']??null])->queryOne();
            if(!$row||!VisitPolicy::assigned($actor,$row))$this->missing();
            $outcome=VisitPolicy::startOutcome($row['status']);
            if($outcome==='conflict')throw new ApiException(409,'INVALID_VISIT_STATUS','Kunjungan tidak dapat dimulai dari status saat ini.');
            if($outcome==='transition'){$this->db()->createCommand("UPDATE visits SET status='in_exam',updated_at=now() WHERE id=:id",[':id'=>$id])->execute();$this->audit($actor,$id,'start_exam',['status'=>'waiting'],['status'=>'in_exam']);}
            return $this->get($actor,$id);
        });
    }
}
