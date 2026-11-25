import {Activity} from 'lucide-react';
export function Brand({compact=false}:{compact?:boolean}){return <div className="brand"><div className="brand-mark"><Activity size={25} strokeWidth={2.4}/></div>{!compact&&<div><strong>simrs<span>.</span></strong><small>Outpatient system</small></div>}</div>}
