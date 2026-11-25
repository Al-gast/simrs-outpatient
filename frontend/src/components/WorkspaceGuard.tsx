import {useEffect,useRef} from 'react';
import {App as AntApp} from 'antd';
import {useBlocker} from 'react-router-dom';
/** Only block unsaved or unresolved work. No modal on ordinary navigation. */
export function useWorkspaceGuard(dirty:boolean,busy:boolean,description:string){
 const {modal,message}=AntApp.useApp();const blocker=useBlocker(dirty||busy);const showing=useRef(false);
 useEffect(()=>{if(blocker.state!=='blocked'||showing.current)return;
  if(busy){blocker.reset();message.warning('Tunggu transaksi selesai sebelum berpindah halaman.');return;}
  showing.current=true;modal.confirm({title:'Tinggalkan halaman?',content:description,okText:'Tinggalkan',cancelText:'Kembali',onOk:()=>{showing.current=false;blocker.proceed();},onCancel:()=>{showing.current=false;blocker.reset();}});
 },[blocker,busy,description,modal,message]);
 useEffect(()=>{if(!dirty&&!busy)return;const guard=(e:BeforeUnloadEvent)=>{e.preventDefault();e.returnValue='';};window.addEventListener('beforeunload',guard);return()=>window.removeEventListener('beforeunload',guard);},[dirty,busy]);
}
