import {randomUUID} from 'node:crypto';
import type {Request, Response, NextFunction, RequestHandler, ErrorRequestHandler} from 'express';
import {validRequestId} from './policy.js';
export class ApiError extends Error {
  constructor(public status: number, public code: string, message: string, public details: Record<string, unknown> = {}) {super(message);}
}
export const requestContext: RequestHandler = (req,res,next) => {
  const value=req.header('X-Request-ID');
  res.locals.requestId=validRequestId(value)?value:randomUUID();
  res.set({'X-Request-ID':res.locals.requestId,'Cache-Control':'no-store','X-Content-Type-Options':'nosniff'});
  next();
};
export function ok(res: Response, data: unknown) {return res.json({data,meta:{request_id:res.locals.requestId}});}
export const asyncRoute = (fn:(req:Request,res:Response,next:NextFunction)=>Promise<unknown>):RequestHandler => (req,res,next)=>{void fn(req,res,next).catch(next);};
export const notFound:RequestHandler=(_req,_res,next)=>next(new ApiError(404,'NOT_FOUND','Endpoint tidak tersedia.'));
export const errorHandler:ErrorRequestHandler=(error:unknown,_req,res,next)=>{
  if(res.headersSent){next(error);return;}
  const e=error instanceof ApiError?error:
    error instanceof SyntaxError && 'status' in error && error.status===400?new ApiError(400,'INVALID_JSON','JSON tidak valid.'):
    typeof error==='object' && error!==null && 'status' in error && error.status===413?new ApiError(413,'PAYLOAD_TOO_LARGE','Request terlalu besar.'):
    new ApiError(500,'INTERNAL_ERROR','Terjadi kesalahan pada layanan.');
  if(e.status>=500) console.error(`SIMRS support error request_id=${res.locals.requestId} code=${e.code}`);
  res.status(e.status).json({error:{code:e.code,message:e.message,details:e.details},meta:{request_id:res.locals.requestId}});
};
