<?php
namespace app\services;
use app\components\{Api,ApiException,AncillaryPolicy as P,ClinicalPolicy,MasterPolicy,RolePolicy};
use yii\db\{Query,Exception as DbException,Expression};

/** Yii owns all writes. Lock order: shared master guard -> visit -> medicine rows in ID order. */
final class AncillaryService
{
    private function db(){return \Yii::$app->db;}
    private function missing(): never {throw new ApiException(404,'NOT_FOUND','Data pelayanan tidak ditemukan.');}
    private function conflict(string $code,string $message): never {throw new ApiException(409,$code,$message);}
    private function tx(callable $fn): mixed {for($i=0;;$i++)try{return $this->db()->transaction($fn);}catch(DbException $e){if(!in_array($e->errorInfo[0]??'',['40001','40P01'],true))throw $e;if($i>=2)throw new ApiException(503,'RETRY_LATER','Transaksi sibuk. Coba kembali.');usleep(20000*($i+1));}}
    private function guard(): void {$this->db()->createCommand('SELECT pg_advisory_xact_lock_shared(682361,1)')->queryScalar();}
    private function visit(string $id,?array $doctor=null,bool $write=true): array {
        if(!MasterPolicy::id($id))$this->missing();$params=[':id'=>$id];$where='id=:id';if($doctor!==null){$where.=' AND doctor_id=:doctor';$params[':doctor']=$doctor['staff_id']??null;}
        $v=$this->db()->createCommand('SELECT * FROM visits WHERE '.$where.($write?' FOR UPDATE':' FOR SHARE'),$params)->queryOne();if(!$v)$this->missing();return $v;
    }
    private function audit(array $a,string $id,string $action,?array $before,array $after,?string $reason=null): void {$this->db()->createCommand()->insert('audit_logs',['actor_id'=>$a['id'],'action'=>$action,'entity_type'=>'visits','entity_id'=>$id,'before_data'=>$before===null?null:new \yii\db\JsonExpression($before, 'jsonb'),'after_data'=>new \yii\db\JsonExpression($after, 'jsonb'),'reason'=>$reason,'request_id'=>Api::id()])->execute();}
    private function tables(bool $rx): array {return $rx?['prescriptions','prescription_items','prescription_id','medicine_id','medicines']:['lab_orders','lab_order_items','lab_order_id','lab_test_id','lab_tests'];}
    private function dto(array $r): array {foreach($r as $k=>$v)if($v!==null&&($k==='id'||str_ends_with($k,'_id')||in_array($k,['unit_price','created_by','dispensed_by','resulted_by'],true)))$r[$k]=(string)$v;foreach(['quantity','stock'] as $k)if(isset($r[$k]))$r[$k]=(int)$r[$k];foreach(['created_at','dispensed_at','resulted_at'] as $k)if(isset($r[$k]))$r[$k]=(new \DateTimeImmutable($r[$k]))->format(DATE_ATOM);return $r;}
    private function orderData(array $row,bool $rx): array {
        [, $items,$fk,$masterId,$master]=$this->tables($rx);$q=(new Query())->from(['i'=>$items])->select(['i.*'])->where(['i.'.$fk=>$row['id']]);
        if($rx)$q->innerJoin(['m'=>$master],'m.id=i.'.$masterId)->addSelect(['stock'=>'m.stock','unit'=>'m.unit']);
        $row=$this->dto($row);$row['items']=array_map(fn($r)=>$this->dto($r),$q->orderBy(['i.id'=>SORT_ASC])->all());return $row;
    }
    /** Called only inside a visit-locked clinical read, including authorized historical views. */
    public function snapshot(string $id): array {
        $rx=(new Query())->from('prescriptions')->where(['visit_id'=>$id])->one();$labs=(new Query())->from('lab_orders')->where(['visit_id'=>$id])->orderBy(['id'=>SORT_ASC])->all();return ['prescription'=>$rx?$this->orderData($rx,true):null,'lab_orders'=>array_map(fn($r)=>$this->orderData($r,false),$labs)];
    }
    public function replace(array $actor,string $id,array $input,bool $rx): array {
        $b=P::order($input,$rx);return $this->tx(function()use($actor,$id,$b,$rx){$this->guard();$v=$this->visit($id,$actor);$db=$this->db();$r=(new Query())->from('medical_records')->where(['visit_id'=>$id])->one();if(!$r)$this->conflict('RECORD_REQUIRED','Simpan rekam medis terlebih dahulu.');ClinicalPolicy::checkVersion((int)$r['version'],$b['expected_version']);
            if(!ClinicalPolicy::editable($v['status'])||(new Query())->from('invoices')->where(['visit_id'=>$id])->exists())$this->conflict('ORDER_LOCKED','Permintaan hanya dapat diubah saat pemeriksaan.');
            [$table,$items,$fk,$masterId,$master]=$this->tables($rx);$orders=(new Query())->from($table)->where(['visit_id'=>$id])->all();if(count($orders)>1)$this->conflict('ORDER_LOCKED','Terdapat beberapa permintaan. Hubungi administrator.');$old=$orders[0]??null;if($old&&$old['status']!=='pending')$this->conflict('ORDER_LOCKED','Pelayanan telah diproses.');
            $before=$old?$this->orderData($old,$rx):null;if(!$rx)foreach($before['items']??[] as $i)if($i['result_text']!==null)$this->conflict('ORDER_LOCKED','Permintaan lab yang sudah memiliki hasil tidak dapat diubah.');ClinicalPolicy::requireReason($b['reason'],$old!==null||(new ClinicalService())->hasSavedSection($id,$rx?'replace_prescription':'replace_lab_order'));$prior=array_column($before['items']??[],null,$masterId);$desired=[];
            foreach($b['items'] as $line){$mid=$line[$masterId];$m=(new Query())->from($master)->where(['id'=>$mid])->one();if(!$m||!RolePolicy::active($m['active']))$this->conflict('MASTER_UNAVAILABLE','Item tidak aktif atau tidak ditemukan.');if($rx&&(int)$m['stock']<$line['quantity'])$this->conflict('INSUFFICIENT_STOCK','Stok obat tidak mencukupi: '.$m['name']);$p=$prior[$mid]??null;$price=(string)($p['unit_price']??$m['price']);if(!MasterPolicy::money($price))$this->conflict('INVALID_PRICE','Tarif tidak valid.');$desired[$mid]=$line+['name_snapshot'=>$p['name_snapshot']??$m['name'],'unit_price'=>$price];}
            if($old){$oid=(string)$old['id'];$db->createCommand()->delete($items,[$fk=>$oid])->execute();if(!$desired)$db->createCommand()->delete($table,['id'=>$oid])->execute();}
            elseif($desired){$oid=(string)$db->createCommand("INSERT INTO $table (visit_id,created_by) VALUES (:visit,:actor) RETURNING id",[':visit'=>$id,':actor'=>$actor['id']])->queryScalar();}
            foreach($desired as $line)$db->createCommand()->insert($items,[$fk=>$oid,...$line])->execute();
            if($db->createCommand()->update('medical_records',['version'=>(int)$r['version']+1,'updated_by'=>$actor['id'],'updated_at'=>new Expression('now()')],['visit_id'=>$id,'version'=>$r['version']])->execute()!==1)$this->conflict('STALE_RECORD','Data telah berubah.');
            $after=$this->snapshot($id);$this->audit($actor,$id,$rx?'replace_prescription':'replace_lab_order',$before,['order'=>$rx?$after['prescription']:($after['lab_orders'][0]??null),'version'=>(int)$r['version']+1],$b['reason']);return (new ClinicalService())->get($actor,$id);
        });
    }
    private function advance(array $actor,array $v): void {
        $id=(string)$v['id'];$rx=(new Query())->from('prescriptions')->where(['visit_id'=>$id,'status'=>'pending'])->exists();$lab=(new Query())->from('lab_orders')->where(['visit_id'=>$id,'status'=>'pending'])->exists();
        if($v['status']==='services_pending'&&ClinicalPolicy::nextStatus($rx,$lab)==='ready_for_billing'){$this->db()->createCommand()->update('visits',['status'=>'ready_for_billing','updated_at'=>new Expression('now()')],['id'=>$id])->execute();$this->audit($actor,$id,'ancillary_completed',['status'=>'services_pending'],['status'=>'ready_for_billing']);(new BillingService())->issueLocked($actor,$id);}
    }
    private function queueQuery(bool $rx): Query {
        [$table]=$this->tables($rx);return (new Query())->from(['o'=>$table])->innerJoin(['v'=>'visits'],'v.id=o.visit_id')->innerJoin(['p'=>'patients'],'p.id=v.patient_id')->innerJoin(['d'=>'medical_staff'],'d.id=v.doctor_id')->innerJoin(['c'=>'clinics'],'c.id=v.clinic_id')->select(['o.*','visit_status'=>'v.status','v.visit_date','patient_name'=>'p.name','p.medical_record_no','doctor_name'=>'d.name','clinic_name'=>'c.name'])->andWhere(['in','v.status',$rx?['services_pending','ready_for_billing','completed']:['in_exam','services_pending','ready_for_billing','completed']]);
    }
    public function queue(bool $rx,array $input): array {
        $f=P::queue($input);$q=$this->queueQuery($rx);if($f['status']!=='all')$q->andWhere(['o.status'=>$f['status']==='done'?($rx?'dispensed':'completed'):'pending']);if(isset($f['q']))$q->andWhere(['or',['ilike','p.name',$f['q']],['ilike','p.medical_record_no',$f['q']]]);$total=(int)(clone $q)->count();$rows=$q->orderBy(['v.visit_date'=>SORT_DESC,'o.id'=>SORT_ASC])->offset(($f['page']-1)*$f['per_page'])->limit($f['per_page'])->all();return ['data'=>array_map(fn($r)=>$this->dto($r),$rows),'meta'=>['total'=>$total,'page'=>$f['page'],'per_page'=>$f['per_page'],'request_id'=>Api::id()]];
    }
    private function orderVisit(string $id,bool $rx): string {if(!MasterPolicy::id($id))$this->missing();[$table]=$this->tables($rx);$v=(new Query())->from($table)->select('visit_id')->where(['id'=>$id])->scalar();if($v===false)$this->missing();return (string)$v;}
    private function detail(string $id,bool $rx): array {$row=$this->queueQuery($rx)->andWhere(['o.id'=>$id])->one();if(!$row)$this->missing();return $this->orderData($row,$rx);}
    public function get(string $id,bool $rx): array {return $this->tx(function()use($id,$rx){$v=$this->orderVisit($id,$rx);$this->visit($v,null,false);return $this->detail($id,$rx);});}
    public function dispense(array $actor,string $id,array $body): array {
        P::keys($body,[]);return $this->tx(function()use($actor,$id){$this->guard();$vid=$this->orderVisit($id,true);$v=$this->visit($vid);$order=$this->detail($id,true);if($order['status']==='dispensed')return $order;if(!P::canProcess($v['status']))$this->conflict('INVALID_VISIT_STATUS','Pelayanan belum dapat diproses.');$db=$this->db();$items=$order['items'];if(!$items)$this->conflict('EMPTY_ORDER','Resep kosong.');
            // Sort decimal bigint IDs by length then lexical value, independent of platform integer width.
            usort($items,fn($a,$b)=>strlen($a['medicine_id'])<=>strlen($b['medicine_id'])?:strcmp($a['medicine_id'],$b['medicine_id']));
            foreach($items as $item){$balance=$db->createCommand('UPDATE medicines SET stock=stock-:qty WHERE id=:id AND stock>=:minimum RETURNING stock',[':qty'=>$item['quantity'],':minimum'=>$item['quantity'],':id'=>$item['medicine_id']])->queryScalar();if($balance===false)$this->conflict('INSUFFICIENT_STOCK','Stok obat tidak mencukupi: '.$item['name_snapshot']);$db->createCommand()->insert('stock_movements',['medicine_id'=>$item['medicine_id'],'prescription_item_id'=>$item['id'],'kind'=>'dispense','quantity_delta'=>-$item['quantity'],'balance_after'=>(int)$balance,'reason'=>'Penyerahan resep','actor_id'=>$actor['id']])->execute();}
            $db->createCommand()->update('prescriptions',['status'=>'dispensed','dispensed_by'=>$actor['id'],'dispensed_at'=>new Expression('now()')],['id'=>$id])->execute();$this->advance($actor,$v);$after=$this->detail($id,true);$this->audit($actor,$vid,'dispense_prescription',$order,$after);return $after;
        });
    }
    public function result(array $actor,string $id,array $body,mixed $eventKey): array {
        $key=P::eventKey($eventKey);$text=P::result($body);if(!MasterPolicy::id($id))$this->missing();$hash=P::hash($id,$text);
        return $this->tx(function()use($actor,$id,$key,$text,$hash){$db=$this->db();$db->createCommand('SELECT pg_advisory_xact_lock(682362, hashtext(:key))',[':key'=>$key])->queryScalar();$oid=(new Query())->from('lab_order_items')->select('lab_order_id')->where(['id'=>$id])->scalar();if($oid===false)$this->missing();$oid=(string)$oid;$vid=$this->orderVisit($oid,false);$v=$this->visit($vid);$order=$this->detail($oid,false);
            $event=(new Query())->from('lab_result_events')->where(['event_key'=>$key])->one();if($event){if(!P::sameEvent($event,$id,$hash,(string)$actor['id']))$this->conflict('IDEMPOTENCY_CONFLICT','Key sudah digunakan untuk request berbeda.');return $order;}
            if(!P::canProcess($v['status'],false)||$order['status']!=='pending')$this->conflict('ORDER_LOCKED','Pemeriksaan sudah selesai atau belum dapat diproses.');$item=(new Query())->from('lab_order_items')->where(['id'=>$id,'lab_order_id'=>$oid])->one();if(!$item)$this->missing();if($item['result_text']!==null)$this->conflict('RESULT_EXISTS','Hasil sudah disimpan. Muat ulang data.');
            $db->createCommand()->update('lab_order_items',['result_text'=>$text,'resulted_by'=>$actor['id'],'resulted_at'=>new Expression('now()')],['id'=>$id])->execute();$db->createCommand()->insert('lab_result_events',['event_key'=>$key,'lab_order_item_id'=>$id,'payload_hash'=>$hash,'actor_id'=>$actor['id']])->execute();
            if(!(new Query())->from('lab_order_items')->where(['lab_order_id'=>$oid,'result_text'=>null])->exists())$db->createCommand()->update('lab_orders',['status'=>'completed'],['id'=>$oid])->execute();$this->advance($actor,$v);$after=$this->detail($oid,false);$this->audit($actor,$vid,'record_lab_result',['item_id'=>$id,'result_text'=>null],['item_id'=>$id,'result_text'=>$text,'event_key'=>$key,'order_status'=>$after['status']]);return $after;
        });
    }
}
