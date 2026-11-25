export type AncillaryItem={id:string;medicine_id?:string;lab_test_id?:string;name_snapshot:string;unit_price:string;quantity?:number;dosage?:string;frequency?:string;stock?:number;unit?:string;result_text?:string|null;resulted_at?:string|null};
export type AncillaryOrder={id:string;visit_id:string;status:'pending'|'dispensed'|'completed';items:AncillaryItem[];visit_status?:string;visit_date?:string;patient_name?:string;medical_record_no?:string;doctor_name?:string;clinic_name?:string;dispensed_at?:string|null};
export type AncillarySnapshot={prescription:AncillaryOrder|null;lab_orders:AncillaryOrder[]};
export type OrderDraft={master_id:string;name:string;price:string;quantity:number;dosage:string;frequency:string;stock?:number;unit?:string};
export function orderDraft(order:AncillaryOrder|null):OrderDraft[]{return (order?.items??[]).map(i=>({master_id:(i.medicine_id??i.lab_test_id)!,name:i.name_snapshot,price:i.unit_price,quantity:i.quantity??1,dosage:i.dosage??'',frequency:i.frequency??'',stock:i.stock,unit:i.unit}));}
export function validOrder(items:OrderDraft[],rx:boolean){return items.length<=20&&new Set(items.map(i=>i.master_id)).size===items.length&&items.every(i=>/^[1-9][0-9]{0,18}$/.test(i.master_id)&&(!rx||(Number.isInteger(i.quantity)&&i.quantity>=1&&i.quantity<=10000&&i.dosage.trim()!==''&&i.frequency.trim()!=='')));}
export const orderTotal=(items:OrderDraft[],rx:boolean)=>items.reduce((sum,i)=>sum+BigInt(i.price)*BigInt(rx?i.quantity:1),0n).toString();
export const canDispense=(order:AncillaryOrder)=>order.status==='pending'&&order.visit_status==='services_pending'&&order.items.length>0&&order.items.every(i=>(i.stock??-1)>=(i.quantity??1));
export const serviceStatus={pending:'Menunggu',dispensed:'Diserahkan',completed:'Selesai'} as const;
