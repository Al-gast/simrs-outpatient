import {Button,Input,Modal,Tooltip} from 'antd';
import type {InputProps,InputRef,ModalProps} from 'antd';
import {useRef,type ReactNode} from 'react';
import {Eye} from 'lucide-react';
import {editRupiah,groupRupiah} from '../lib/presentation';
/** Centered form/detail surface. Long clinical and billing workspaces intentionally stay separate. */
export function CenterModal({children,className='',...props}:ModalProps){
 return <Modal centered maskClosable={false} {...props} className={`simrs-modal ${className}`}>{children}</Modal>;
}
export function IconAction({label,onClick,icon=<Eye size={17}/>,danger=false,disabled=false}:{label:string;onClick:()=>void;icon?:ReactNode;danger?:boolean;disabled?:boolean}){
 return <Tooltip title={label} trigger={['hover','focus']}><Button type="text" className="simrs-icon-action" aria-label={label} icon={icon} danger={danger} disabled={disabled} onClick={onClick}/></Tooltip>;
}
/** Keep form/transaction value a string; grouping is visual, invalid text remains invalid. */
export function RupiahInput({value,onChange,...props}:Omit<InputProps,'value'|'onChange'> & {value?:string|number|null;onChange?:(value:string)=>void}){
 const raw=value==null?'':String(value),ref=useRef<InputRef>(null);
 return <Input {...props} ref={ref} prefix="Rp" inputMode="numeric" value={groupRupiah(raw)} onChange={e=>{
  const input=e.target,entered=input.value,position=input.selectionStart??entered.length;
  const next=editRupiah(raw,entered,(e.nativeEvent as InputEvent).inputType??'');
  const formatted=groupRupiah(next);onChange?.(next);
  // Restore the caret after grouping, but only if this is still the active input/value.
  if(formatted!==entered&&/^\d[\d.]*$/.test(formatted)){
   const digits=entered.slice(0,position).replace(/[^0-9]/g,'').length;
   requestAnimationFrame(()=>{const el=ref.current?.input;if(!el||document.activeElement!==el||el.value!==formatted)return;let caret=0,count=0;while(caret<formatted.length&&count<digits){if(/\d/.test(formatted[caret]))count++;caret++;}el.setSelectionRange(caret,caret);});
  }
 }}/>;
}
