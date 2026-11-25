import type {BillingLine,PaymentStatus} from './billing';
export const clinicalSteps=['record','procedures','rx','lab','review'] as const;
export type ClinicalStep=typeof clinicalSteps[number];
export function nextClinicalStep(step:ClinicalStep,savedRecord:boolean,dirty:boolean,busy:boolean):ClinicalStep|null {
 if(!savedRecord||dirty||busy)return null;
 const i=clinicalSteps.indexOf(step);return i<0?null:clinicalSteps[i+1]??null;
}
export const canFinishReview=(step:string,editing:boolean,savedRecord:boolean,dirty:boolean,busy:boolean,needsReload:boolean)=>step==='review'&&editing&&savedRecord&&!dirty&&!busy&&!needsReload;
/** Presentation only. Keep canonical IDs for API, queries, and keys. */
export function transactionLabel(value:string|null|undefined):string {
 if(!value)return '—';const m=/^(RX|LAB|INV|PAY)-([1-9]\d*)$/.exec(value);return m?`${m[1]}-${m[2].padStart(6,'0')}`:value;
}
/** Only a known formatted invoice number is converted for the existing server-side search. */
export function invoiceSearch(value:string):string {const m=/^INV-0+([1-9]\d*)$/i.exec(value.trim());return m?`INV-${m[1]}`:value;}
const categoryOrder:Record<BillingLine['category'],number>={registration:0,procedure:1,medicine:2,laboratory:3};
export function orderedLines(lines:BillingLine[]):BillingLine[]{return [...lines].sort((a,b)=>categoryOrder[a.category]-categoryOrder[b.category]);}
/** Integer arithmetic avoids rounding large monetary values through Number. */
export function paymentPercent(total:string|null,paid:string,status:PaymentStatus):number {
 if(total===null)return 0;const t=BigInt(total),p=BigInt(paid);if(t===0n)return status==='paid'?100:0;if(p<=0n)return 0;if(p>=t)return 100;return Number(p*1000n/t)/10;
}
export const ancillaryEmpty=(rx:boolean,finished:boolean)=>finished?(rx?'Tidak ada resep obat pada kunjungan ini.':'Tidak ada permintaan laboratorium pada kunjungan ini.'):'Simpan rekam medis dan mulai pemeriksaan untuk membuat permintaan.';
