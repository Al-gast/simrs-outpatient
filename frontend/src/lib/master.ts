import type {Role} from './roles';
export const resources=['patients','medical-staff','clinics','doctor-schedules','procedures','medicines','lab-tests'] as const;
export type Resource=typeof resources[number];
export type Field={key:string;label:string;kind?:'date'|'time'|'select'|'money'|'textarea'|'doctor'|'clinic';optional?:boolean;options?:{value:string|number;label:string}[];max?:number};
const code:Field={key:'code',label:'Kode',max:40},name:Field={key:'name',label:'Nama',max:200},price:Field={key:'price',label:'Tarif / harga (Rp)',kind:'money'};
export const weekdays=['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];
export const professions=[{value:'doctor',label:'Dokter'},{value:'nurse',label:'Perawat'},{value:'other',label:'Lainnya'}];
export const sexes=[{value:'M',label:'Laki-laki'},{value:'F',label:'Perempuan'},{value:'U',label:'Tidak diketahui'}];
export const catalog:Record<Resource,{label:string;singular:string;description:string;fields:Field[];columns:string[];read:Role[]}>= {
 patients:{label:'Pasien',singular:'pasien',description:'Kelola identitas pasien dan nomor rekam medis.',fields:[name,{key:'identity_no',label:'Nomor identitas',optional:true,max:80},{key:'birth_date',label:'Tanggal lahir',kind:'date'},{key:'sex',label:'Jenis kelamin',kind:'select',options:sexes},{key:'phone',label:'Telepon',optional:true,max:80},{key:'address',label:'Alamat',kind:'textarea',optional:true,max:1000}],columns:['medical_record_no','name','identity_no','birth_date','sex','phone'],read:['admin','registration','doctor']},
 'medical-staff':{label:'Tenaga medis',singular:'tenaga medis',description:'Kelola dokter, perawat, dan tenaga medis lainnya.',fields:[code,name,{key:'profession',label:'Profesi',kind:'select',options:professions}],columns:['code','name','profession'],read:['admin','registration','doctor']},
 clinics:{label:'Poli pelayanan',singular:'poli',description:'Kelola unit pelayanan dan biaya pendaftaran.',fields:[code,name,{key:'registration_fee',label:'Biaya pendaftaran (Rp)',kind:'money'}],columns:['code','name','registration_fee'],read:['admin','registration','doctor']},
 'doctor-schedules':{label:'Jadwal dokter',singular:'jadwal',description:'Atur jadwal praktik dokter pada setiap poli.',fields:[{key:'doctor_id',label:'Dokter',kind:'doctor'},{key:'clinic_id',label:'Poli',kind:'clinic'},{key:'weekday',label:'Hari',kind:'select',options:weekdays.map((label,i)=>({label,value:i+1}))},{key:'start_time',label:'Jam mulai',kind:'time'},{key:'end_time',label:'Jam selesai',kind:'time'}],columns:['doctor_name','clinic_name','weekday','start_time','end_time'],read:['admin','registration','doctor']},
 procedures:{label:'Tindakan medis',singular:'tindakan',description:'Kelola tindakan medis beserta tarifnya.',fields:[code,name,price],columns:['code','name','price'],read:['admin','doctor']},
 medicines:{label:'Obat & stok',singular:'obat',description:'Kelola daftar obat, harga, dan penyesuaian stok.',fields:[code,name,{key:'unit',label:'Satuan',max:80},price],columns:['code','name','unit','price','stock'],read:['admin','doctor','pharmacy']},
 'lab-tests':{label:'Pemeriksaan lab',singular:'pemeriksaan lab',description:'Kelola jenis pemeriksaan penunjang dan tarif.',fields:[code,name,{key:'unit',label:'Satuan hasil',optional:true,max:80},price],columns:['code','name','unit','price'],read:['admin','doctor','laboratory']},
};
export const masterPath=(r:Resource)=>`/master/${r}`;
export function masterResource(path:string):Resource|undefined{return resources.find(r=>masterPath(r)===path);}
export function canReadMaster(role:Role,r:Resource){return catalog[r].read.includes(role);}
export function canWriteMaster(role:Role,r:Resource){return role==='admin'||(role==='registration'&&r==='patients');}
export function masterNav(role:Role){return resources.filter(r=>canReadMaster(role,r));}
export const columnLabels:Record<string,string>={medical_record_no:'No. rekam medis',identity_no:'No. identitas',name:'Nama',birth_date:'Tanggal lahir',sex:'Jenis kelamin',phone:'Telepon',code:'Kode',profession:'Profesi',registration_fee:'Biaya daftar',doctor_name:'Dokter',clinic_name:'Poli',weekday:'Hari',start_time:'Mulai',end_time:'Selesai',price:'Harga / tarif',unit:'Satuan',stock:'Stok'};
