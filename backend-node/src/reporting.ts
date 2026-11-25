import type {Express} from 'express';
import type {Pool,PoolClient} from 'pg';
import {ApiError,asyncRoute,ok} from './api.js';
import {requireRole} from './auth.js';
import {parseReport,type Metric} from './report-policy.js';
// Date filters use half-open intervals. Timestamp columns remain bare for index use.
const dates='visit_date >= $1::date AND visit_date < ($2::date + 1)';
const time=(c:string)=>`${c} >= ($1::date::timestamp AT TIME ZONE 'Asia/Jakarta') AND ${c} < (($2::date+1)::timestamp AT TIME ZONE 'Asia/Jakarta')`;
const visit={view:'reporting.visits',where:dates,columns:'id::text, visit_date::text, clinic_id::text, clinic_name, status',order:'r.visit_date DESC,r.id DESC'};
const definitions:Record<Metric,{view:string;where:string;columns:string;order:string}>={
 visits:visit,clinics:visit,
 procedures:{view:'reporting.procedures',where:dates,columns:'id::text, visit_date::text, procedure_id::text,name_snapshot,quantity::text',order:'r.visit_date DESC,r.id DESC'},
 revenue:{view:'reporting.revenue',where:time('paid_at'),columns:'id::text, invoice_id::text, paid_at, amount::text, method',order:'r.paid_at DESC,r.id DESC'},
 medicines:{view:'reporting.medicine_usage',where:time('created_at'),columns:'id::text, created_at,medicine_id::text,medicine_name,quantity::text',order:'r.created_at DESC,r.id DESC'},
 payments:{view:'reporting.invoice_status',where:dates,columns:'id::text,visit_date::text,total_amount::text,paid_amount::text,status',order:'r.visit_date DESC,r.id DESC'}
};
async function snapshot<T>(pool:Pool,fn:(client:PoolClient)=>Promise<T>):Promise<T>{
 let client:PoolClient|undefined;let discard=false;
 try{client=await pool.connect();await client.query('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');const value=await fn(client);await client.query('COMMIT');return value;}
 catch{if(client)try{await client.query('ROLLBACK');}catch{discard=true;}throw new ApiError(503,'REPORT_UNAVAILABLE','Laporan belum dapat dimuat. Coba lagi.');}
 finally{client?.release(discard);}
}
function filters(q:Record<string,unknown>,detail:boolean){try{return parseReport(q,detail);}catch(e){throw new ApiError(422,'VALIDATION_ERROR',e instanceof Error?e.message:'Filter tidak valid.');}}
export function mountReporting(app:Express,pool:Pool,coreUrl:string){
 const auth=requireRole(coreUrl,'admin');
 app.get('/api/v1/reports/summary',auth,asyncRoute(async(req,res)=>{
  const {from,to}=filters(req.query,false);const params=[from,to];
  const data=await snapshot(pool,async c=>{
   const q=async(sql:string)=>(await c.query(sql,params)).rows;
   // Aggregate independently: no fan-out join between procedures, invoices and payments.
   const visits=await q(`SELECT count(*)::text AS visits,(count(*) FILTER (WHERE status='completed'))::text AS completed FROM reporting.visits WHERE ${dates}`);
   const clinics=await q(`SELECT clinic_id::text,clinic_name,count(*)::text AS count FROM reporting.visits WHERE ${dates} GROUP BY clinic_id,clinic_name ORDER BY count(*) DESC,clinic_id LIMIT 10`);
   const procedures=await q(`SELECT coalesce(sum(quantity),0)::text AS quantity FROM reporting.procedures WHERE ${dates}`);
   const procedureGroups=await q(`SELECT procedure_id::text,name_snapshot, sum(quantity)::text AS quantity FROM reporting.procedures WHERE ${dates} GROUP BY procedure_id,name_snapshot ORDER BY sum(quantity) DESC,procedure_id,name_snapshot LIMIT 10`);
   const revenue=await q(`SELECT coalesce(sum(amount),0)::text AS amount,count(*)::text AS transactions FROM reporting.revenue WHERE ${time('paid_at')}`);
   const revenueByDay=await q(`SELECT (paid_at AT TIME ZONE 'Asia/Jakarta')::date::text AS day,sum(amount)::text AS amount FROM reporting.revenue WHERE ${time('paid_at')} GROUP BY 1 ORDER BY 1`);
   const trend=await q(`SELECT visit_date::text AS day,count(*)::text AS count FROM reporting.visits WHERE ${dates} GROUP BY visit_date ORDER BY visit_date`);
   const medicines=await q(`SELECT count(DISTINCT medicine_id)::text AS types,count(*)::text AS lines FROM reporting.medicine_usage WHERE ${time('created_at')}`);
   const medicineGroups=await q(`SELECT medicine_id::text,medicine_name,sum(quantity)::text AS quantity FROM reporting.medicine_usage WHERE ${time('created_at')} GROUP BY medicine_id,medicine_name ORDER BY sum(quantity) DESC,medicine_id,medicine_name LIMIT 10`);
   const payments=await q(`SELECT status,count(*)::text AS count,coalesce(sum(total_amount-paid_amount),0)::text AS outstanding,coalesce(sum(total_amount),0)::text AS total_amount,coalesce(sum(paid_amount),0)::text AS paid_amount FROM reporting.invoice_status WHERE ${dates} GROUP BY status ORDER BY status`);
   return {from,to,visits:visits[0],clinics,procedures:procedures[0],procedure_groups:procedureGroups,revenue:revenue[0],revenue_by_day:revenueByDay,trend,medicines:medicines[0],medicine_groups:medicineGroups,payments};
  });return ok(res,data);
 }));
 app.get('/api/v1/reports/details',auth,asyncRoute(async(req,res)=>{
  const f=filters(req.query,true),d=definitions[f.metric];const params:unknown[]=[f.from,f.to];let where=d.where;
  if(f.clinic){params.push(f.clinic);where+=` AND clinic_id=$${params.length}::bigint`;}
  const data=await snapshot(pool,async c=>{
   const count=await c.query(`SELECT count(*)::text AS total FROM ${d.view} WHERE ${where}`,params);
   const rows=await c.query(`SELECT ${d.columns} FROM ${d.view} AS r WHERE ${where} ORDER BY ${d.order} LIMIT $${params.length+1} OFFSET $${params.length+2}`,[...params,f.perPage,(f.page-1)*f.perPage]);
   return {metric:f.metric,rows:rows.rows,total:count.rows[0].total,page:f.page,per_page:f.perPage};
  });return ok(res,data);
 }));
}
