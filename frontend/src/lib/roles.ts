import {canMonitorVisits} from './visits';
import {masterResource,canReadMaster} from './master';
export const roles=['admin','registration','doctor','pharmacy','laboratory','cashier'] as const;
export type Role=typeof roles[number];
export const workspace:Record<Role,{path:string;label:string;roleLabel:string;description:string;next:string}>={
 admin:{path:'/dashboard',label:'Dashboard',roleLabel:'Administrator',description:'Pantau kesiapan layanan dan kelola operasional rawat jalan.',next:'Master data, monitoring, statistik operasional, pengguna dan audit tersedia.'},
 registration:{path:'/registration',label:'Pendaftaran',roleLabel:'Petugas Pendaftaran',description:'Ruang kerja pendaftaran dan antrean pasien.',next:'Data pasien tersedia melalui sidebar. Pendaftaran kunjungan dan antrean tersedia.'},
 doctor:{path:'/doctor',label:'Pemeriksaan',roleLabel:'Dokter',description:'Ruang kerja pemeriksaan pasien yang menjadi tanggung jawab Anda.',next:'Antrean dokter, rekam medis, tindakan, resep, lab, dan riwayat klinis tersedia.'},
 pharmacy:{path:'/pharmacy',label:'Farmasi',roleLabel:'Petugas Farmasi',description:'Ruang kerja resep dan penyerahan obat.',next:'Resep, stok, dan penyerahan obat tersedia.'},
 laboratory:{path:'/laboratory',label:'Laboratorium',roleLabel:'Petugas Laboratorium',description:'Ruang kerja permintaan dan hasil pemeriksaan penunjang.',next:'Permintaan lab dan input hasil tersedia.'},
 cashier:{path:'/billing',label:'Billing',roleLabel:'Kasir',description:'Ruang kerja tagihan dan pembayaran pasien.',next:'Invoice, pembayaran bertahap, dan bukti pembayaran tersedia.'}
};
export function canAccess(role:Role,path:string):boolean{const r=masterResource(path);return (/^\/doctor\/[1-9]\d{0,18}$/.test(path)&&role==='doctor')||(/^\/billing\/[1-9]\d{0,18}$/.test(path)&&role==='cashier')||(/^\/billing-monitor\/[1-9]\d{0,18}$/.test(path)&&role==='admin')||(['/users','/audit'].includes(path)&&role==='admin')||(path==='/billing-monitor'&&role==='admin')||(path==='/visits'&&canMonitorVisits(role))||path==='/profile'||path===workspace[role].path||(r!==undefined&&canReadMaster(role,r));}
