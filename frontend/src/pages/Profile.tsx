import {Alert,Card,Descriptions,Skeleton} from 'antd';
import {useQuery} from '@tanstack/react-query';
import {getMe,errorMessage} from '../lib/api';
import {workspace} from '../lib/roles';
import {useSession} from '../lib/session';
export function Profile(){const {user}=useSession();const q=useQuery({queryKey:['profile',user?.id],queryFn:getMe,retry:false});return <><div className="page-heading"><div><h1>Profil saya</h1><p>Identitas dan hak akses akun yang sedang digunakan.</p></div></div><Card>{q.isPending?<Skeleton active/>:q.isError?<Alert type="error" showIcon message={errorMessage(q.error)}/>:<Descriptions column={1} items={[{key:'name',label:'Nama',children:q.data.name},{key:'username',label:'Username',children:q.data.username},{key:'role',label:'Hak akses',children:workspace[q.data.role].roleLabel}]}/>}</Card></>;}
