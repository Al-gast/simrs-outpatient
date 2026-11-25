import {createContext,useCallback,useContext,useEffect,useRef,useState,type ReactNode} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import {Alert,Button,Spin} from 'antd';
import axios from 'axios';
import {login as requestLogin,restoreSession,logoutSession,setAccessToken,setUnauthorizedHandler,type User} from './api';
import {SESSION_GENERATION,LOGGED_OUT,canRestoreGeneration,ownsGeneration} from './session-policy';
type Session={user:User|null;expired:boolean;signIn:(username:string,password:string)=>Promise<User>;signOut:()=>Promise<boolean>};
const SessionContext=createContext<Session|null>(null);
// Storage contains only non-secret markers. No bearer, profile or clinical data.
function sharedGeneration(){try{return localStorage.getItem(SESSION_GENERATION);}catch{return null;}}
function tabGeneration(){try{return sessionStorage.getItem(SESSION_GENERATION);}catch{return null;}}
function bindGeneration(value:string|null){try{if(value===null)sessionStorage.removeItem(SESSION_GENERATION);else sessionStorage.setItem(SESSION_GENERATION,value);}catch{/* storage may be unavailable */}}
function publishGeneration(value:string){try{localStorage.setItem(SESSION_GENERATION,value);return true;}catch{return false;}}
function suppressRestore(value:boolean){try{if(value)localStorage.setItem(LOGGED_OUT,'1');else localStorage.removeItem(LOGGED_OUT);}catch{/* storage may be unavailable */}}
function restoreSuppressed(){try{return localStorage.getItem(LOGGED_OUT)==='1';}catch{return false;}}
export function SessionProvider({children}:{children:ReactNode}){
 const [user,setUser]=useState<User|null>(null),[expired,setExpired]=useState(false),[boot,setBoot]=useState<'loading'|'ready'|'error'>('loading'),[attempt,setAttempt]=useState(0);const queryClient=useQueryClient();
 const generation=useRef(tabGeneration());
 const clear=useCallback((expiry:boolean)=>{setAccessToken(null);setUser(null);setExpired(expiry);queryClient.clear();},[queryClient]);
 useEffect(()=>{setUnauthorizedHandler(()=>{if(ownsGeneration(generation.current,sharedGeneration()))suppressRestore(true);clear(true);});return()=>setUnauthorizedHandler(()=>{});},[clear]);
 useEffect(()=>{const onStorage=(e:StorageEvent)=>{if(e.key===SESSION_GENERATION&&!ownsGeneration(generation.current,e.newValue)){if(generation.current===null){generation.current='previous-session';bindGeneration(generation.current);}clear(false);}if(e.key===LOGGED_OUT&&e.newValue==='1')clear(false);};window.addEventListener('storage',onStorage);return()=>window.removeEventListener('storage',onStorage);},[clear]);
 useEffect(()=>{let active=true;const startingGeneration=sharedGeneration();if(restoreSuppressed()||!canRestoreGeneration(generation.current,startingGeneration)){setBoot('ready');return;}setBoot('loading');
  restoreSession().then(result=>{if(!active)return;if(!restoreSuppressed()&&ownsGeneration(startingGeneration,sharedGeneration())){generation.current=startingGeneration;bindGeneration(startingGeneration);setAccessToken(result.access_token);setUser(result.user);}setBoot('ready');}).catch(e=>{if(!active)return;if(axios.isAxiosError(e)&&e.response?.status===401){clear(false);setBoot('ready');}else setBoot('error');});
  return()=>{active=false;};
 },[attempt,clear]);
 const signIn=async(username:string,password:string)=>{const result=await requestLogin(username,password);queryClient.clear();const next=crypto.randomUUID();generation.current=publishGeneration(next)?next:null;bindGeneration(generation.current);suppressRestore(false);setAccessToken(result.access_token);setExpired(false);setUser(result.user);return result.user;};
 const signOut=async()=>{if(!ownsGeneration(generation.current,sharedGeneration())){clear(false);return false;}let revoked=true;suppressRestore(true);try{await logoutSession();}catch{revoked=false;}finally{clear(false);}return revoked;};
 if(boot!=='ready')return <main style={{minHeight:'100vh',display:'grid',placeItems:'center',padding:24}}>{boot==='loading'?<Spin size="large" tip="Memeriksa sesi…"><div style={{width:240,height:100}}/></Spin>:<Alert type="warning" showIcon message="Sesi belum dapat diperiksa" description="Periksa koneksi ke layanan. Sesi tidak dihapus karena gangguan jaringan." action={<Button onClick={()=>setAttempt(x=>x+1)}>Coba lagi</Button>}/>}</main>;
 return <SessionContext.Provider value={{user,expired,signIn,signOut}}>{children}</SessionContext.Provider>;
}
export function useSession(){const value=useContext(SessionContext);if(!value)throw new Error('SessionProvider missing');return value;}
