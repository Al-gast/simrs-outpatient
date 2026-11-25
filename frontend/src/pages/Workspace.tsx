import {Alert,Button,Card,Skeleton,Tag} from 'antd';
import {useQuery} from '@tanstack/react-query';
import {Activity,ArrowRight,CheckCircle2,Database,RefreshCw,ShieldCheck,Workflow} from 'lucide-react';
import {useSession} from '../lib/session';
import {workspace} from '../lib/roles';
import {getHealth,checkAccess,errorMessage} from '../lib/api';
export function Workspace(){const {user}=useSession();if(!user)return null;return <WorkspaceContent key={user.id+user.role}/>;}
function WorkspaceContent(){
 const {user}=useSession();const role=user!.role,item=workspace[role];
 const core=useQuery({queryKey:['health','yii'],queryFn:()=>getHealth('yii'),retry:false});
 const node=useQuery({queryKey:['health','node'],queryFn:()=>getHealth('node'),retry:false});
 const auth=useQuery({queryKey:['access','yii',role],queryFn:()=>checkAccess('yii',role),retry:false});
 const supportAuth=useQuery({queryKey:['access','node',role],queryFn:()=>checkAccess('node',role),retry:false});
 const refresh=()=>{void core.refetch();void node.refetch();void auth.refetch();void supportAuth.refetch();};
 return <><div className="page-heading"><div><span className="eyebrow">SIMRS OUTPATIENT / RUANG KERJA</span><h1>{item.label}</h1><p>{item.description}</p></div><Button icon={<RefreshCw size={17}/>} loading={core.isFetching||node.isFetching||auth.isFetching||supportAuth.isFetching} onClick={refresh}>Muat ulang</Button></div><section className="welcome-panel"><div><span className="eyebrow">RUANG KERJA SIMRS</span><h2>Selamat datang, {user!.name}.</h2><p>Anda masuk sebagai {item.roleLabel}. Sistem menggunakan hak akses sesuai tanggung jawab masing-masing petugas.</p></div><div className="welcome-icon"><ShieldCheck size={45} strokeWidth={1.4}/></div></section><div className="section-heading"><h2>Kesiapan sistem</h2><span>Hasil pemeriksaan API aktual</span></div><div className="service-grid">{[{name:'Core SIMRS',detail:'Yii2 · PostgreSQL',query:core,Icon:Database},{name:'Supporting API',detail:'Node.js · Reporting',query:node,Icon:Workflow},{name:'Hak akses Core',detail:item.roleLabel,query:auth,Icon:ShieldCheck},{name:'Hak akses Node',detail:'Introspeksi Yii2',query:supportAuth,Icon:Activity}].map(({name,detail,query,Icon})=><Card key={name} className="service-card"><div className="service-top"><div className="service-icon"><Icon size={23}/></div>{query.isPending?<Tag>Memeriksa</Tag>:query.isError?<Tag color="error">Tidak tersedia</Tag>:<Tag color="success">Terverifikasi</Tag>}</div><h3>{name}</h3><p>{detail}</p>{query.isPending?<Skeleton active paragraph={{rows:1}} title={false}/>:query.isError?<div className="service-error">{errorMessage(query.error)}</div>:<div className="service-ok"><CheckCircle2 size={16}/> Respons API berhasil</div>}</Card>)}</div><Card className="next-panel"><div className="next-icon"><ArrowRight size={24}/></div><div><h2>Pengembangan berikutnya</h2><p>{item.next}</p><Alert showIcon type="info" message="Checkpoint Phase 5" description="Pendaftaran, monitoring, rekam medis dan tindakan tersedia. Farmasi, lab, billing dan statistik menyusul."/></div></Card></>;
}
