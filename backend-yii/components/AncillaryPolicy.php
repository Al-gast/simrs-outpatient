<?php
namespace app\components;

final class AncillaryPolicy
{
    public static function invalid(string $field): never {throw new ApiException(422,'VALIDATION_ERROR','Periksa data pelayanan.',[$field=>'Nilai tidak valid.']);}
    public static function keys(array $body,array $allowed): void {if(array_diff(array_keys($body),$allowed))self::invalid('_body');}
    public static function order(array $body,bool $rx): array
    {
        self::keys($body,['items','expected_version','reason']);$v=ClinicalPolicy::version($body['expected_version']??null);if(!$v)self::invalid('expected_version');
        $items=$body['items']??null;if(!is_array($items)||!array_is_list($items)||count($items)>20)self::invalid('items');
        $key=$rx?'medicine_id':'lab_test_id';$seen=[];$out=[];
        foreach($items as $item){if(!$item instanceof \stdClass)self::invalid('items');$item=(array)$item;self::keys($item,$rx?[$key,'dosage','frequency','quantity']:[$key]);$id=$item[$key]??null;if(!MasterPolicy::id($id)||isset($seen[$id]))self::invalid($key);$seen[$id]=true;$line=[$key=>$id];if($rx){$qty=$item['quantity']??null;if(!is_int($qty)||$qty<1||$qty>10000)self::invalid('quantity');$line+=['quantity'=>$qty,'dosage'=>ClinicalPolicy::text($item['dosage']??null,200,'dosage'),'frequency'=>ClinicalPolicy::text($item['frequency']??null,200,'frequency')];}$out[]=$line;}
        return ['items'=>$out,'expected_version'=>$v,'reason'=>ClinicalPolicy::optionalReason($body['reason']??null)];
    }
    public static function eventKey(mixed $key): string {if(!is_string($key)||!preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/iD',$key))self::invalid('Idempotency-Key');return strtolower($key);}
    public static function result(array $body): string {self::keys($body,['result_text']);return ClinicalPolicy::text($body['result_text']??null,2000,'result_text');}
    public static function hash(string $item,string $result): string {return hash('sha256',json_encode([$item,$result],JSON_THROW_ON_ERROR));}
    public static function sameEvent(array $event,string $item,string $hash,string $actor): bool {return (string)$event['lab_order_item_id']===$item&&(string)$event['actor_id']===$actor&&hash_equals($event['payload_hash'],$hash);}
    public static function canProcess(string $status,bool $rx=true): bool {return $status==='services_pending'||(!$rx&&$status==='in_exam');}
    public static function queue(array $input): array {
        self::keys($input,['page','per_page','status','q']);$p=VisitPolicy::filters(array_intersect_key($input,array_flip(['page','per_page','q'])),true);$status=$input['status']??'pending';if(!in_array($status,['pending','done','all'],true))self::invalid('status');return $p+['status'=>$status];
    }
}
