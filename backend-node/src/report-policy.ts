export const metrics=['visits','clinics','procedures','revenue','medicines','payments'] as const;
export type Metric=typeof metrics[number];
export function validDate(value:unknown):value is string {
 if(typeof value!=='string'||!/^20\d{2}-\d{2}-\d{2}$/.test(value))return false;
 const date=new Date(value+'T00:00:00Z');return Number.isFinite(date.getTime())&&date.toISOString().slice(0,10)===value;
}
export function parseReport(q:Record<string,unknown>,detail:boolean){
 const allowed=detail?['from','to','metric','page','per_page','clinic_id']:['from','to'];
 if(Object.keys(q).some(k=>!allowed.includes(k)))throw new Error('Filter tidak didukung.');
 const {from,to}=q;
 if(!validDate(from)||!validDate(to)||from>to||(Date.parse(to)-Date.parse(from))/86400000>365)throw new Error('Periode wajib valid, maksimal 366 hari.');
 const integer=(v:unknown,fallback:number,max:number)=>{if(v===undefined)return fallback;if(typeof v!=='string'||!/^\d{1,6}$/.test(v)||Number(v)<1||Number(v)>max)throw new Error('Pagination tidak valid.');return Number(v);};
 const metric=q.metric;
 if(detail&&(typeof metric!=='string'||!metrics.includes(metric as Metric)))throw new Error('Metrik tidak valid.');
 const clinic=q.clinic_id;
 if(clinic!==undefined&&(!['visits','clinics'].includes(String(metric))||typeof clinic!=='string'||!/^\d{1,19}$/.test(clinic)||BigInt(clinic)<1n||BigInt(clinic)>9223372036854775807n))throw new Error('Filter poli tidak valid.');
 return {from,to,metric:metric as Metric,page:integer(q.page,1,10000),perPage:integer(q.per_page,20,100),clinic:clinic as string|undefined};
}
