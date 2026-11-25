import type {Express} from 'express';
import {z} from 'zod';
import {ApiError,asyncRoute} from './api.js';
import {requireRole} from './auth.js';
export const itemId=z.string().regex(/^[1-9][0-9]{0,18}$/).refine(v=>v.length<19||v<='9223372036854775807');
export const eventKey=z.string().regex(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i).transform(v=>v.toLowerCase());
export const resultBody=z.object({result_text:z.string().refine(v=>Buffer.byteLength(v,'utf8')<=2000&&!v.includes('\0')).transform(v=>v.replace(/^[ \t\n\r\v]+|[ \t\n\r\v]+$/g,'')).refine(v=>v.length>0)}).strict();
const errorEnvelope=z.object({error:z.object({code:z.string(),message:z.string(),details:z.record(z.unknown()).optional()})});
const successEnvelope=z.object({data:z.object({id:z.string(),status:z.enum(['pending','completed']),items:z.array(z.unknown())}).passthrough()});
/** Never writes to PostgreSQL. Forward user bearer and stable key to the authoritative Yii transaction. */
export function mountLaboratory(app:Express,coreUrl:string){
 app.get('/api/v1/laboratory/orders/:id',requireRole(coreUrl,'laboratory'),asyncRoute(async(req,res)=>{
   const id=itemId.safeParse(req.params.id);if(!id.success)throw new ApiError(422,'VALIDATION_ERROR','ID tidak valid.');
   let upstream:globalThis.Response,payload:unknown;
   try{upstream=await fetch(`${coreUrl}/laboratory/orders/${id.data}`,{headers:{Authorization:req.header('Authorization')!,'X-Request-ID':res.locals.requestId},signal:AbortSignal.timeout(4000),redirect:'error'});payload=await upstream.json();}
   catch{throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Core API tidak tersedia.');}
   if(!upstream.ok){const parsed=errorEnvelope.safeParse(payload);if(parsed.success&&[401,403,404,422].includes(upstream.status)){const e=parsed.data.error;throw new ApiError(upstream.status,e.code,e.message,e.details??{});}throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Core API tidak tersedia.');}
   const parsed=successEnvelope.safeParse(payload);if(!parsed.success)throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Respons Core tidak valid.');
   return res.json({data:parsed.data.data,meta:{request_id:res.locals.requestId}});
 }));
 app.post('/api/v1/laboratory/items/:id/result',requireRole(coreUrl,'laboratory'),asyncRoute(async(req,res)=>{
   if(!req.is('application/json'))throw new ApiError(415,'UNSUPPORTED_MEDIA_TYPE','Gunakan application/json.');
   const id=itemId.safeParse(req.params.id),key=eventKey.safeParse(req.header('Idempotency-Key')),body=resultBody.safeParse(req.body);
   if(!id.success||!key.success||!body.success)throw new ApiError(422,'VALIDATION_ERROR','ID, Idempotency-Key UUID, dan hasil pemeriksaan wajib valid.');
   let upstream:globalThis.Response,payload:unknown;
   try{upstream=await fetch(`${coreUrl}/laboratory/items/${id.data}/result`,{method:'POST',headers:{Authorization:req.header('Authorization')!,'Content-Type':'application/json','X-Request-ID':res.locals.requestId,'Idempotency-Key':key.data},body:JSON.stringify(body.data),signal:AbortSignal.timeout(4000),redirect:'error'});payload=await upstream.json();}
   catch{throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Hasil transaksi belum dapat dipastikan. Coba lagi dengan key dan hasil yang sama.');}
   if(!upstream.ok){const parsed=errorEnvelope.safeParse(payload);if(parsed.success&&[400,401,403,404,409,413,415,422].includes(upstream.status)){const e=parsed.data.error;throw new ApiError(upstream.status,e.code,e.message,e.details??{});}throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Hasil transaksi belum dapat dipastikan. Coba lagi dengan key dan hasil yang sama.');}
   const parsed=successEnvelope.safeParse(payload);if(!parsed.success)throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Respons Core tidak valid. Ulangi dengan key yang sama.');
   return res.json({data:parsed.data.data,meta:{request_id:res.locals.requestId}});
 }));
}
