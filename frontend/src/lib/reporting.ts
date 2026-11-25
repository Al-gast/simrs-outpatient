export type Metric='visits'|'clinics'|'procedures'|'revenue'|'medicines'|'payments';
export type Period={from:string;to:string};
export function periodToday():Period {const d=new Intl.DateTimeFormat('sv-SE',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());return {from:d.slice(0,7)+'-01',to:d};}
export function validPeriod(p:Period){const valid=(v:string)=>/^20\d\d-\d\d-\d\d$/.test(v)&&Number.isFinite(Date.parse(v))&&new Date(v).toISOString().slice(0,10)===v;return valid(p.from)&&valid(p.to)&&p.from<=p.to&&Date.parse(p.to)-Date.parse(p.from)<=365*86400000;}
export function formatNumber(v:string){return /^-?\d+$/.test(v)?BigInt(v).toLocaleString('id-ID'):'—';}

export type PaymentGroup={status:string;count:string;outstanding:string;total_amount?:string;paid_amount?:string};
const natural=(v:unknown):v is string=>typeof v==='string'&&/^\d+$/.test(v);
/** No mixing of visit-date invoices with cash received by paid_at. Fail closed on incomplete or inconsistent API data. */
export function invoiceTotals(groups:PaymentGroup[]){
 let total=0n,paid=0n,outstanding=0n;
 for(const g of groups){if(!natural(g.total_amount)||!natural(g.paid_amount)||!natural(g.outstanding))return null;
  const t=BigInt(g.total_amount),p=BigInt(g.paid_amount),o=BigInt(g.outstanding);if(t!==p+o)return null;total+=t;paid+=p;outstanding+=o;}
 return {total:total.toString(),paid:paid.toString(),outstanding:outstanding.toString()};
}
/** Include zero-activity dates; bounded by the same server period policy. */
export function dailySeries(period:Period,rows:{day:string;value:string}[]){
 if(!validPeriod(period))return [];
 const byDay=new Map(rows.map(r=>[r.day,r.value]));const result:{day:string;value:string}[]=[];
 for(let ms=Date.parse(period.from);ms<=Date.parse(period.to);ms+=86400000){const day=new Date(ms).toISOString().slice(0,10);result.push({day,value:byDay.get(day)??'0'});}
 return result;
}
/** Only chart coordinates are rounded; exact amounts stay as decimal strings. */
export function chartRatio(value:string,max:string){return natural(value)&&natural(max)&&BigInt(max)>0n?Number(BigInt(value)*10000n/BigInt(max))/10000:0;}
export function paymentSlices(groups:PaymentGroup[]){
 if(groups.some(g=>!natural(g.count)))return {total:'0',slices:[]};
 const total=groups.reduce((a,g)=>a+BigInt(g.count),0n);let cursor=0n;
 const slices=groups.map(g=>{const start=chartRatio(cursor.toString(),total.toString())*100;cursor+=BigInt(g.count);return {status:g.status,start,end:chartRatio(cursor.toString(),total.toString())*100};});
 return {total:total.toString(),slices};
}
