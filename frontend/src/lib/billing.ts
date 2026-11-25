import type {Role} from './roles';
import {statusColor} from './presentation';
export type PaymentStatus='unissued'|'unpaid'|'partial'|'paid';
export type Method='cash'|'non_cash';
export type BillingRow={visit_id:string;visit_status:'ready_for_billing'|'completed';visit_date:string;patient_name:string;medical_record_no:string;clinic_name:string;invoice_id:string|null;invoice_no:string|null;revision:number|null;total_amount:string|null;paid_amount:string;balance:string|null;payment_status:PaymentStatus;created_at:string|null;updated_at:string|null};
export type BillingLine={category:'registration'|'procedure'|'laboratory'|'medicine';source_id:string;description:string;unit_price:string;quantity:number};
export type Payment={id:string;receipt_no:string;amount:string;method:Method;cashier_name:string;paid_at:string};
export type Invoice=BillingRow&{items:BillingLine[];payments:Payment[]};
export type PaymentBody={amount:string;method:Method;expected_revision:number};
export type PendingPayment={key:string;body:PaymentBody};
export const paymentStatus:Record<PaymentStatus,{label:string;color:string}>={unissued:{label:'Belum terbit',color:statusColor('unissued')},unpaid:{label:'Belum dibayar',color:statusColor('unpaid')},partial:{label:'Dibayar sebagian',color:statusColor('partial')},paid:{label:'Lunas',color:statusColor('paid')}};
export const categoryLabel={registration:'Pendaftaran',procedure:'Tindakan medis',laboratory:'Laboratorium',medicine:'Obat'};
export const methodLabel={cash:'Tunai',non_cash:'Non-tunai (simulasi)'};
export const canPay=(role:Role)=>role==='cashier';
export const validAmount=(amount:string,balance:string|null)=>/^[1-9][0-9]{0,17}$/.test(amount)&&balance!==null&&BigInt(amount)<=BigInt(balance);
export const lineTotal=(line:BillingLine)=>(BigInt(line.unit_price)*BigInt(line.quantity)).toString();
export const pendingStorageKey=(scope:string,id:string)=>`simrs-payment-v1:${scope}:${id}`;
export function validPending(p:unknown):p is PendingPayment {if(!p||typeof p!=='object')return false;const x=p as Partial<PendingPayment>;return typeof x.key==='string'&&/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(x.key)&&!!x.body&&typeof x.body.amount==='string'&&/^[1-9][0-9]{0,17}$/.test(x.body.amount)&&['cash','non_cash'].includes(x.body.method)&&Number.isInteger(x.body.expected_revision)&&x.body.expected_revision>0;}
