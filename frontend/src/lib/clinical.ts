import type {Visit,VisitStatus} from './visits';
import type {AncillarySnapshot} from './ancillary';
import type {Role} from './roles';
export const vitalFields=[{key:'systolic',label:'Sistolik',unit:'mmHg',min:30,max:300},{key:'diastolic',label:'Diastolik',unit:'mmHg',min:15,max:200},{key:'pulse',label:'Denyut nadi',unit:'bpm',min:15,max:300},{key:'temperature',label:'Suhu tubuh',unit:'°C',min:25,max:45},{key:'respiratory_rate',label:'Frekuensi napas',unit:'/menit',min:3,max:100},{key:'weight',label:'Berat badan',unit:'kg',min:0.2,max:500}] as const;
export type VitalKey=typeof vitalFields[number]['key'];
export type RecordDraft={complaint:string;anamnesis:string;physical_exam:string;primary_diagnosis:string;secondary_diagnoses:string[];vital_signs:Record<VitalKey,number|null>};
export type MedicalRecord=RecordDraft&{id:string;visit_id:string;version:number;updated_by:string;updated_at:string};
export type ProcedureLine={procedure_id:string;name_snapshot:string;unit_price:string;quantity:number;id?:string};
export type ClinicalSnapshot=AncillarySnapshot&{record:MedicalRecord|null;procedures:ProcedureLine[];procedure_total:string};
export type Clinical=ClinicalSnapshot&{visit:Visit;can_revise_procedures:boolean;reason_required?:{procedures:boolean;prescription:boolean;lab_order:boolean}};
export type HistoryRow={id:string;visit_date:string;status:VisitStatus;doctor_name:string;clinic_name:string};
export const canOpenClinical=(role:Role,status:VisitStatus)=>role==='doctor'&&status!=='waiting';
export const canEditClinical=(status:VisitStatus)=>status==='in_exam';
export const canAmendDiagnosis=(status:VisitStatus)=>['services_pending','ready_for_billing','completed'].includes(status);
export function draft(r:MedicalRecord|null):RecordDraft {return r?{complaint:r.complaint,anamnesis:r.anamnesis,physical_exam:r.physical_exam,primary_diagnosis:r.primary_diagnosis,secondary_diagnoses:[...r.secondary_diagnoses],vital_signs:{...r.vital_signs}}:{complaint:'',anamnesis:'',physical_exam:'',primary_diagnosis:'',secondary_diagnoses:[],vital_signs:{systolic:null,diastolic:null,pulse:null,temperature:null,respiratory_rate:null,weight:null}};}
export function validDraft(r:RecordDraft):boolean{return [r.complaint,r.anamnesis,r.physical_exam,r.primary_diagnosis].every(s=>s.trim()!=='')&&vitalFields.every(({key,min,max})=>{const v=r.vital_signs[key];return v!==null&&Number.isFinite(v)&&v>=min&&v<=max;})&&Number(r.vital_signs.systolic)>=Number(r.vital_signs.diastolic)&&r.secondary_diagnoses.length<=10&&r.secondary_diagnoses.every(s=>s.trim()!=='')&&new Set([r.primary_diagnosis,...r.secondary_diagnoses].map(s=>s.trim())).size===r.secondary_diagnoses.length+1;}
export function procedureTotal(items:ProcedureLine[]):string{return items.reduce((total,p)=>total+BigInt(p.unit_price)*BigInt(p.quantity),0n).toString();}
export const rupiah=(amount:string)=>`Rp${BigInt(amount).toLocaleString('id-ID')}`;
