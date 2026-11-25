import axios from 'axios';
import type {Role} from './roles';
export type User={id:string;username:string;name:string;role:Role;staff_id:string|null};
export type LoginResult={access_token:string;expires_in:number;user:User};
export type Envelope<T>={data:T;meta:{request_id:string}};
export type Health={service:string;database:string;status:string;core?:string};
let accessToken:string|null=null;
let onUnauthorized:()=>void=()=>{};
export function setAccessToken(token:string|null){accessToken=token;}
export function setUnauthorizedHandler(callback:()=>void){onUnauthorized=callback;}
function client(baseURL:string){
 const c=axios.create({baseURL,timeout:6000,headers:{Accept:'application/json'}});
 c.interceptors.request.use(config=>{if(accessToken&&!config.url?.endsWith('/auth/login'))config.headers.Authorization=`Bearer ${accessToken}`;return config;});
 c.interceptors.response.use(r=>r,e=>{if(axios.isAxiosError(e)&&e.response?.status===401&&e.config?.headers?.Authorization===`Bearer ${accessToken}`&&accessToken)onUnauthorized();return Promise.reject(e);});
 return c;
}
export const core=client(import.meta.env.VITE_CORE_URL||'http://localhost:8080/api/v1');
export const support=client(import.meta.env.VITE_NODE_URL||'http://localhost:3001/api/v1');
// Browser-only credentialed client. Business APIs remain explicit Bearer requests.
const browserAuth=axios.create({baseURL:core.defaults.baseURL,timeout:6000,withCredentials:true,headers:{Accept:'application/json','Content-Type':'application/json'}});
export const login=async(username:string,password:string)=>(await browserAuth.post<Envelope<LoginResult>>('/auth/browser/login',{username,password})).data.data;
export const restoreSession=async()=>(await browserAuth.post<Envelope<LoginResult>>('/auth/browser/restore',{})).data.data;
export const logoutSession=async()=>browserAuth.post('/auth/browser/logout',{},accessToken?{headers:{Authorization:`Bearer ${accessToken}`}}:undefined);
export const getMe=async()=>(await core.get<Envelope<User>>('/auth/me')).data.data;
export const getHealth=async(service:'yii'|'node')=>(await (service==='yii'?core:support).get<Envelope<Health>>('/health')).data.data;
export const checkAccess=async(service:'yii'|'node',role:Role)=>(await (service==='yii'?core:support).get<Envelope<{role:Role;access:string}>>(`/system/access/${role}`)).data.data;
export function errorMessage(error:unknown):string{
 if(axios.isAxiosError(error)){
  if(!error.response)return 'Layanan tidak dapat dihubungi. Periksa koneksi dan coba lagi.';
  const msg=error.response.data?.error?.message;if(typeof msg==='string')return msg;
 }
 return 'Terjadi kesalahan. Silakan coba lagi.';
}
