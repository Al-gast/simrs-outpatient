import type {RequestHandler} from 'express';
import {z} from 'zod';
import {ApiError,asyncRoute} from './api.js';
import {roles,permits,validBearer,type Role} from './policy.js';
const userSchema=z.object({id:z.string(),username:z.string(),name:z.string(),role:z.enum(roles),staff_id:z.string().nullable()});
export type User=z.infer<typeof userSchema>;
export async function introspect(coreUrl:string,bearer:string,requestId:string):Promise<User>{
  let response: globalThis.Response;
  try {response=await fetch(`${coreUrl}/auth/me`,{headers:{Authorization:bearer,'X-Request-ID':requestId},signal:AbortSignal.timeout(3000),redirect:'error'});}
  catch {throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Layanan autentikasi tidak tersedia.');}
  if(response.status===401)throw new ApiError(401,'UNAUTHORIZED','Sesi tidak valid atau telah berakhir.');
  if(!response.ok)throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Layanan autentikasi tidak tersedia.');
  try {return z.object({data:userSchema}).parse(await response.json()).data;}
  catch {throw new ApiError(503,'UPSTREAM_UNAVAILABLE','Respons autentikasi tidak valid.');}
}
export function requireRole(coreUrl:string,...allowed:Role[]):RequestHandler{
  return asyncRoute(async(req,res,next)=>{
    const bearer=req.header('Authorization');
    if(!validBearer(bearer))throw new ApiError(401,'UNAUTHORIZED','Sesi tidak valid atau telah berakhir.');
    const user=await introspect(coreUrl,bearer,res.locals.requestId);
    if(!permits(user.role,allowed))throw new ApiError(403,'FORBIDDEN','Anda tidak memiliki hak akses.');
    res.locals.user=user;next();
  });
}
