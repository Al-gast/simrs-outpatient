import type {Role} from './roles';
export const auditActions:Record<string,string>={
 create:'Menambahkan master data',update:'Mengubah master data',delete:'Menghapus master data',adjust_stock:'Menyesuaikan stok',
 register:'Mendaftarkan kunjungan',start_exam:'Memulai pemeriksaan',create_medical_record:'Membuat rekam medis',update_medical_record:'Memperbarui rekam medis',replace_visit_procedures:'Menyimpan tindakan medis',finish_exam:'Menyelesaikan pemeriksaan',read_clinical_history:'Melihat riwayat klinis',
 replace_prescription:'Menyimpan resep',replace_lab_order:'Menyimpan permintaan laboratorium',dispense_prescription:'Menyerahkan obat',record_lab_result:'Mencatat hasil laboratorium',ancillary_completed:'Menyelesaikan pelayanan penunjang',
 issue_invoice:'Menerbitkan invoice',revise_invoice:'Merevisi invoice',record_payment:'Mencatat pembayaran',complete_visit:'Menyelesaikan kunjungan',
 create_user:'Menambahkan pengguna',update_user:'Mengubah pengguna',reset_user_password:'Reset password pengguna'
};
export const auditEntities:Record<string,string>={patients:'Pasien','medical-staff':'Tenaga medis',clinics:'Poli pelayanan','doctor-schedules':'Jadwal dokter',procedures:'Tindakan medis',medicines:'Obat & stok','lab-tests':'Pemeriksaan lab',visits:'Kunjungan & pelayanan',users:'Manajemen pengguna'};
export function auditLabel(labels:Record<string,string>,code:string){return Object.prototype.hasOwnProperty.call(labels,code)?labels[code]:code;}
export function auditOptions(labels:Record<string,string>,extra:string[]=[]){return [...new Set([...Object.keys(labels),...extra.filter(x=>/^[a-zA-Z0-9_-]{1,80}$/.test(x))])].map(value=>({value,label:auditLabel(labels,value)}));}
export type AccountFields={name:string;role:Role;staff_id:string|null;active:boolean;version:number};
export function accountPatch(row:AccountFields,change:{kind:'edit';name:string;role:Role;staff_id?:string|null}|{kind:'reset';password:string}|{kind:'status';active:boolean}){
 const common={name:row.name,role:row.role,staff_id:row.staff_id,active:row.active,expected_version:row.version};
 if(change.kind==='edit')return {...common,name:change.name,role:change.role,staff_id:change.role==='doctor'?change.staff_id??null:null};
 if(change.kind==='reset')return {...common,password:change.password};
 return {...common,active:change.active};
}
