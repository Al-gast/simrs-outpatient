<?php
namespace app\services;
use app\components\{Api,ApiException,ClinicalPolicy,MasterPolicy,RolePolicy,VisitPolicy};
use yii\db\{Query,Exception as DbException};

/** All clinical writes serialize on the visit, then check the optimistic clinical version. */
final class ClinicalService
{
    private function db(){return \Yii::$app->db;}
    private function missing(): never {throw new ApiException(404,'NOT_FOUND','Kunjungan tidak ditemukan.');}
    private function conflict(string $code,string $message): never {throw new ApiException(409,$code,$message);}
    private function tx(callable $work): mixed
    {for($i=0;;$i++)try{return $this->db()->transaction($work);}catch(DbException $e){if(!in_array($e->errorInfo[0]??'',['40001','40P01'],true))throw $e;if($i>=2)throw new ApiException(503,'RETRY_LATER','Transaksi sibuk. Muat ulang data.');usleep(20000*($i+1));}}
    private function lockVisit(array $actor,string $id,bool $write): array
    {
        if(!MasterPolicy::id($id))$this->missing();
        $row=$this->db()->createCommand('SELECT id,patient_id,doctor_id,visit_date,status FROM visits WHERE id=:id AND doctor_id=:doctor '.($write?'FOR UPDATE':'FOR SHARE'),[':id'=>$id,':doctor'=>$actor['staff_id']??null])->queryOne();
        if(!$row||!VisitPolicy::assigned($actor,$row))$this->missing();return $row;
    }
    private function record(string $id): ?array
    {
        $row=(new Query())->from('medical_records')->where(['visit_id'=>$id])->one();if(!$row)return null;
        foreach(['id','visit_id','updated_by'] as $k)$row[$k]=(string)$row[$k];$row['version']=(int)$row['version'];$row['updated_at']=(new \DateTimeImmutable($row['updated_at']))->format(DATE_ATOM);
        foreach(['vital_signs','secondary_diagnoses'] as $k)$row[$k]=json_decode($row[$k],true,32,JSON_THROW_ON_ERROR);
        return $row;
    }
    private function procedures(string $id): array
    {
        $rows=(new Query())->from('visit_procedures')->where(['visit_id'=>$id])->orderBy(['id'=>SORT_ASC])->all();
        foreach($rows as &$r){foreach(['id','visit_id','procedure_id','unit_price'] as $k)$r[$k]=(string)$r[$k];$r['quantity']=(int)$r['quantity'];$r['subtotal']=(string)((int)$r['unit_price']*$r['quantity']);}unset($r);return $rows;
    }
    private function snapshot(string $id): array
    {
        $procedures=$this->procedures($id);$total=0;foreach($procedures as $p)$total+=(int)$p['subtotal'];
        return ['record'=>$this->record($id),'procedures'=>$procedures,'procedure_total'=>(string)$total,...(new AncillaryService())->snapshot($id)];
    }
    private function audit(array $actor,string $id,string $action,?array $before,?array $after,?string $reason=null): void
    {$this->db()->createCommand()->insert('audit_logs',['actor_id'=>$actor['id'],'action'=>$action,'entity_type'=>'visits','entity_id'=>$id,'before_data'=>$before===null?null:new \yii\db\JsonExpression($before, 'jsonb'),'after_data'=>$after===null?null:new \yii\db\JsonExpression($after, 'jsonb'),'reason'=>$reason,'request_id'=>Api::id()])->execute();}
    /** Called under the visit lock. Audit is append-only; even cleared lists remain historical edits. */
    public function hasSavedSection(string $id,string $action): bool
    {return (new Query())->from('audit_logs')->where(['entity_type'=>'visits','entity_id'=>$id,'action'=>$action])->exists();}
    private function response(array $actor,string $id): array
    {$visit=(new VisitService())->get($actor,$id);$invoice=(new Query())->from('invoices')->where(['visit_id'=>$id])->one();$hasPayments=$invoice&&(new Query())->from('payments')->where(['invoice_id'=>$invoice['id']])->exists();$snapshot=$this->snapshot($id);$canRevise=\app\components\BillingPolicy::canRevise($visit['status'],(bool)$hasPayments);return ['visit'=>$visit,'can_revise_procedures'=>$canRevise,'reason_required'=>['procedures'=>$canRevise||!empty($snapshot['procedures'])||$this->hasSavedSection($id,'replace_visit_procedures'),'prescription'=>$snapshot['prescription']!==null||$this->hasSavedSection($id,'replace_prescription'),'lab_order'=>!empty($snapshot['lab_orders'])||$this->hasSavedSection($id,'replace_lab_order')],...$snapshot];}
    public function get(array $actor,string $id): array
    {return $this->tx(function()use($actor,$id){$visit=$this->lockVisit($actor,$id,false);$result=$this->response($actor,$id);if($result['record']&&($visit['visit_date']!==VisitPolicy::today()||$visit['status']!=='in_exam'))$this->audit($actor,$id,'read_clinical_history',null,['anchor_visit_id'=>$id]);return $result;});}
    public function save(array $actor,string $id,array $input): array
    {
        $body=ClinicalPolicy::record($input);
        return $this->tx(function()use($actor,$id,$body){
            $visit=$this->lockVisit($actor,$id,true);$old=$this->record($id);$version=$old['version']??0;
            ClinicalPolicy::checkVersion($version,$body['expected_version']);
            if(!ClinicalPolicy::editable($visit['status'])){
                if(!$old||!ClinicalPolicy::diagnosisOnly($visit['status'])||!ClinicalPolicy::nonDiagnosisUnchanged($old,$body))$this->conflict('INVALID_VISIT_STATUS','Setelah pemeriksaan selesai, hanya diagnosis yang dapat dikoreksi.');
            }
            $fields=[];foreach(array_keys(ClinicalPolicy::TEXT) as $k)$fields[$k]=$body[$k];
            foreach(['vital_signs','secondary_diagnoses'] as $k)$fields[$k]=new \yii\db\JsonExpression($body[$k], 'jsonb');
            $fields['updated_by']=$actor['id'];$fields['version']=$version+1;$fields['updated_at']=new \yii\db\Expression('now()');
            if(!$old)$this->db()->createCommand()->insert('medical_records',['visit_id'=>$id,...$fields])->execute();
            else if($this->db()->createCommand()->update('medical_records',$fields,['visit_id'=>$id,'version'=>$version])->execute()!==1)$this->conflict('STALE_RECORD','Data telah berubah.');
            $this->audit($actor,$id,$old?'update_medical_record':'create_medical_record',$old,$this->record($id),$body['reason']);
            return $this->response($actor,$id);
        });
    }
    public function replaceProcedures(array $actor,string $id,array $input): array
    {
        $body=ClinicalPolicy::procedures($input);
        return $this->tx(function()use($actor,$id,$body){
            // Same ordering as registration/master edits: advisory lock before entity locks.
            $db=$this->db();$db->createCommand('SELECT pg_advisory_xact_lock_shared(682361,1)')->queryScalar();
            $visit=$this->lockVisit($actor,$id,true);$record=$this->record($id);ClinicalPolicy::checkVersion($record['version']??0,$body['expected_version']);
            $billing=new BillingService();$revision=$visit['status']==='ready_for_billing';if(!ClinicalPolicy::editable($visit['status'])&&!($revision&&$billing->canReviseLocked($id,$visit['status'])))$this->conflict('BILLING_LOCKED','Tindakan terkunci setelah pembayaran atau saat pelayanan penunjang berlangsung.');if(!$record)$this->conflict('RECORD_REQUIRED','Simpan rekam medis terlebih dahulu.');
            $before=$this->procedures($id);ClinicalPolicy::requireReason($body['reason'],$revision||!empty($before)||$this->hasSavedSection($id,'replace_visit_procedures'));$existing=array_column($before,null,'procedure_id');$desired=[];
            foreach($body['items'] as $item){$pid=$item['procedure_id'];$prior=$existing[$pid]??null;if($prior){$desired[$pid]=['name_snapshot'=>$prior['name_snapshot'],'unit_price'=>$prior['unit_price'],'quantity'=>$item['quantity']];continue;}
                $master=(new Query())->from('procedures')->where(['id'=>$pid])->one();if(!$master||!RolePolicy::active($master['active']))$this->conflict('PROCEDURE_UNAVAILABLE','Tindakan baru harus berasal dari master aktif.');
                if(!MasterPolicy::money((string)$master['price']))$this->conflict('INVALID_PRICE','Tarif tindakan di luar batas sistem.');
                $desired[$pid]=['name_snapshot'=>$master['name'],'unit_price'=>(string)$master['price'],'quantity'=>$item['quantity']];}
            foreach($existing as $pid=>$prior)if(!isset($desired[$pid]))$db->createCommand()->delete('visit_procedures',['id'=>$prior['id']])->execute();
            foreach($desired as $pid=>$fields){if(isset($existing[$pid])){$db->createCommand()->update('visit_procedures',['quantity'=>$fields['quantity']],['id'=>$existing[$pid]['id']])->execute();}else{$db->createCommand()->insert('visit_procedures',['visit_id'=>$id,'procedure_id'=>(string)$pid,...$fields])->execute();}}
            $version=$record['version'];if($db->createCommand()->update('medical_records',['version'=>$version+1,'updated_by'=>$actor['id'],'updated_at'=>new \yii\db\Expression('now()')],['visit_id'=>$id,'version'=>$version])->execute()!==1)$this->conflict('STALE_RECORD','Data telah berubah.');
            $this->audit($actor,$id,'replace_visit_procedures',['version'=>$version,'items'=>$before],['version'=>$version+1,'items'=>$this->procedures($id)],$body['reason']);
            if($revision)$billing->reviseLocked($actor,$id,$body['reason']);return $this->response($actor,$id);
        });
    }
    public function finish(array $actor,string $id,array $input): array
    {
        $version=ClinicalPolicy::finish($input);
        return $this->tx(function()use($actor,$id,$version){$visit=$this->lockVisit($actor,$id,true);$record=$this->record($id);
            if(!$record)$this->conflict('RECORD_REQUIRED','Simpan rekam medis lengkap terlebih dahulu.');ClinicalPolicy::checkVersion($record['version'],$version);
            if(!ClinicalPolicy::editable($visit['status']))$this->conflict('INVALID_VISIT_STATUS','Pemeriksaan sudah selesai atau belum dimulai.');
            $rx=(new Query())->from('prescriptions')->where(['visit_id'=>$id,'status'=>'pending'])->exists();$lab=(new Query())->from('lab_orders')->where(['visit_id'=>$id,'status'=>'pending'])->exists();$next=ClinicalPolicy::nextStatus($rx,$lab);
            $this->db()->createCommand()->update('visits',['status'=>$next,'updated_at'=>new \yii\db\Expression('now()')],['id'=>$id])->execute();
            $this->db()->createCommand()->update('medical_records',['version'=>$version+1,'updated_by'=>$actor['id'],'updated_at'=>new \yii\db\Expression('now()')],['visit_id'=>$id,'version'=>$version])->execute();
            $this->audit($actor,$id,'finish_exam',['status'=>$visit['status'],'version'=>$version],['status'=>$next,'version'=>$version+1]);if($next==='ready_for_billing')(new BillingService())->issueLocked($actor,$id);return $this->response($actor,$id);
        });
    }
    /** Historical metadata only; scoped by an assigned anchor visit. */
    public function history(array $actor,string $id,array $input): array
    {
        if(array_diff(array_keys($input),['page','per_page']))throw new ApiException(422,'VALIDATION_ERROR','Filter riwayat tidak valid.');$f=VisitPolicy::filters($input,true);
        return $this->tx(function()use($actor,$id,$f){$anchor=$this->lockVisit($actor,$id,false);$q=(new Query())->from(['v'=>'visits'])->innerJoin(['d'=>'medical_staff'],'d.id=v.doctor_id')->innerJoin(['c'=>'clinics'],'c.id=v.clinic_id')->innerJoin(['r'=>'medical_records'],'r.visit_id=v.id')->select(['v.id','v.visit_date','v.status','doctor_name'=>'d.name','clinic_name'=>'c.name'])->where(['v.patient_id'=>$anchor['patient_id']])->andWhere(['<>','v.id',$id]);
            if($anchor['visit_date']!==VisitPolicy::today()||$anchor['status']!=='in_exam')$q->andWhere(['v.doctor_id'=>$anchor['doctor_id']]);else $q->andWhere(['<=','v.visit_date',VisitPolicy::today()])->andWhere(['<>','v.status','waiting']);
            $total=(int)(clone $q)->count();$rows=$q->orderBy(['v.visit_date'=>SORT_DESC,'v.id'=>SORT_DESC])->limit($f['per_page'])->offset(($f['page']-1)*$f['per_page'])->all();foreach($rows as &$r)$r['id']=(string)$r['id'];unset($r);return ['data'=>$rows,'meta'=>['page'=>$f['page'],'per_page'=>$f['per_page'],'total'=>$total,'request_id'=>Api::id()]];});
    }
    public function historical(array $actor,string $id,string $target): array
    {
        if(!MasterPolicy::id($target)||$id===$target)$this->missing();
        return $this->tx(function()use($actor,$id,$target){$anchor=$this->lockVisit($actor,$id,false);$row=$this->db()->createCommand('SELECT id,patient_id,doctor_id,visit_date,status FROM visits WHERE id=:id FOR SHARE',[':id'=>$target])->queryOne();if(!$row||!ClinicalPolicy::historyAllowed($anchor,$row,VisitPolicy::today()))$this->missing();$data=$this->snapshot($target);if(!$data['record'])$this->missing();$this->audit($actor,$target,'read_clinical_history',null,['anchor_visit_id'=>$id]);return ['visit_id'=>$target,'visit_date'=>$row['visit_date'],...$data];});
    }
}
