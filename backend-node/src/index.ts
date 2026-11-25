import express from 'express';
import cors from 'cors';
import pg from 'pg';
import {z} from 'zod';
import {ApiError,asyncRoute,errorHandler,notFound,ok,requestContext} from './api.js';
import {requireRole} from './auth.js';
import {roles} from './policy.js';
import {mountReporting} from './reporting.js';
import {mountLaboratory} from './laboratory.js';
const config=z.object({DB_HOST:z.string().min(1),DB_PORT:z.coerce.number().int().positive(),DB_NAME:z.string().min(1),DB_USER:z.literal('simrs_report'),DB_PASSWORD:z.string().min(1),CORE_URL:z.string().url(),FRONTEND_ORIGIN:z.string().url()}).parse(process.env);
const pool=new pg.Pool({host:config.DB_HOST,port:config.DB_PORT,database:config.DB_NAME,user:config.DB_USER,password:config.DB_PASSWORD,max:5,connectionTimeoutMillis:2000,query_timeout:5000,statement_timeout:3000});
pool.on('error',()=>console.error('SIMRS report pool idle connection error'));
const app=express(); app.disable('x-powered-by');
app.use(requestContext);
app.use((req,_res,next)=>{if(req.method==='OPTIONS' && req.header('Origin')!==config.FRONTEND_ORIGIN){next(new ApiError(403,'FORBIDDEN','Origin tidak diizinkan.'));return;}next();});
app.use(cors({origin:(origin,cb)=>cb(null,origin===config.FRONTEND_ORIGIN),methods:['GET','POST','PUT','PATCH','DELETE','OPTIONS'],allowedHeaders:['Authorization','Content-Type','X-Request-ID','Idempotency-Key'],exposedHeaders:['X-Request-ID','Retry-After']}));
app.use(express.json({limit:'16kb'}));
app.get('/api/v1/health',asyncRoute(async(_req,res)=>{
  try {await pool.query('SELECT id FROM reporting.visits LIMIT 1');}
  catch {throw new ApiError(503,'UNAVAILABLE','Database reporting belum siap.');}
  try {const r=await fetch(`${config.CORE_URL}/health`,{signal:AbortSignal.timeout(2000),redirect:'error'});if(!r.ok)throw new Error('core unavailable');}
  catch {throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Core API belum siap.');}
  return ok(res,{service:'node',database:'ready',core:'ready',status:'ready'});
}));
app.get('/api/v1/system/access/:role',asyncRoute(async(req,res,next)=>{
  const parsed=z.enum(roles).safeParse(req.params.role);
  if(!parsed.success)throw new ApiError(422,'VALIDATION_ERROR','Role tidak valid.');
  requireRole(config.CORE_URL,parsed.data)(req,res,next);
}),(req,res)=>ok(res,{role:res.locals.user.role,access:'granted'}));
mountLaboratory(app,config.CORE_URL);
mountReporting(app,pool,config.CORE_URL);
app.use(notFound);app.use(errorHandler);
const server=app.listen(3001,'0.0.0.0',()=>console.log('SIMRS support API listening on 3001'));
for(const signal of ['SIGTERM','SIGINT'] as const)process.on(signal,()=>{server.close(()=>{void pool.end().finally(()=>process.exit(0));});setTimeout(()=>process.exit(1),5000).unref();});
