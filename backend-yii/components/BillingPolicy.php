<?php
namespace app\components;
final class BillingPolicy
{
    public const MAX=999999999999999999;
    private static function invalid(string $field): never {throw new ApiException(422,'VALIDATION_ERROR','Periksa data pembayaran.',[$field=>'Nilai tidak valid.']);}
    public static function amount(mixed $value): int {if(!is_string($value)||!preg_match('/\A[1-9][0-9]{0,17}\z/D',$value))self::invalid('amount');return (int)$value;}
    public static function payment(array $body,mixed $key): array {
        if(array_diff(array_keys($body),['amount','method','expected_revision']))self::invalid('_body');
        $amount=self::amount($body['amount']??null);$method=$body['method']??null;$rev=$body['expected_revision']??null;
        if(!in_array($method,['cash','non_cash'],true))self::invalid('method');if(!is_int($rev)||$rev<1||$rev>=2147483647)self::invalid('expected_revision');
        return ['amount'=>$amount,'method'=>$method,'expected_revision'=>$rev,'key'=>AncillaryPolicy::eventKey($key)];
    }
    public static function hash(string $visit,array $body): string {return hash('sha256',json_encode([$visit,(string)$body['amount'],$body['method'],$body['expected_revision']],JSON_THROW_ON_ERROR));}
    public static function same(array $payment,string $invoice,string $actor,string $hash): bool {return (string)$payment['invoice_id']===$invoice&&(string)$payment['cashier_id']===$actor&&hash_equals($payment['payload_hash'],$hash);}
    public static function status(int $total,int $paid): string {return $paid===$total?'paid':($paid>0?'partial':'unpaid');}
    public static function checkPayment(int $total,int $paid,int $amount,int $actual,int $expected,string $status): void {
        if($actual!==$expected)throw new ApiException(409,'STALE_INVOICE','Tagihan telah berubah. Muat ulang sebelum membayar.');
        if($status!=='ready_for_billing'||$paid>=$total)throw new ApiException(409,'INVOICE_CLOSED','Tagihan sudah selesai atau belum siap.');
        if($amount>$total-$paid)throw new ApiException(409,'OVERPAYMENT','Nominal melebihi sisa tagihan.');
    }
    public static function canRevise(string $status,bool $hasPayments): bool {return $status==='ready_for_billing'&&!$hasPayments;}
    public static function total(array $items): int { $sum=0;foreach($items as $item){$price=(int)$item['unit_price'];$qty=(int)$item['quantity'];if($price<0||$qty<1||$price>intdiv(self::MAX-$sum,$qty))throw new ApiException(409,'INVALID_TOTAL','Total tagihan di luar batas sistem.');$sum+=$price*$qty;}return $sum; }
    public static function filters(array $input): array {if(array_diff(array_keys($input),['page','per_page','q','status']))self::invalid('_query');$f=VisitPolicy::filters(array_intersect_key($input,array_flip(['page','per_page','q'])),true);$status=$input['status']??'all';if(!in_array($status,['all','unpaid','partial','paid','unissued'],true))self::invalid('status');return $f+['status'=>$status];}
}
