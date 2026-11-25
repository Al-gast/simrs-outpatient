import {useEffect,useState,type ReactNode} from 'react';
import {Button,Drawer,Dropdown,Tooltip,App as AntApp} from 'antd';
import {Activity,Database,ChevronDown,ClipboardList,FlaskConical,LayoutDashboard,LogOut,Menu,PanelLeftClose,PanelLeftOpen,Pill,Stethoscope,UserRound,UsersRound,History,Wallet} from 'lucide-react';
import {Link,useLocation,useNavigate} from 'react-router-dom';
import {useSession} from '../lib/session';
import {workspace,type Role} from '../lib/roles';
import {Brand} from './Brand';
import {catalog,masterNav,masterPath,masterResource} from '../lib/master';
const icons={admin:LayoutDashboard,registration:ClipboardList,doctor:Stethoscope,pharmacy:Pill,laboratory:FlaskConical,cashier:Wallet};
function Nav({role,compact=false,onNavigate}:{role:Role;compact?:boolean;onNavigate?:()=>void}){
 const location=useLocation(),item=workspace[role],Icon=icons[role];
 const masters=masterNav(role),masterActive=masters.some(r=>masterPath(r)===location.pathname);
 const [masterOpen,setMasterOpen]=useState(masterActive);
 useEffect(()=>{if(masterActive)setMasterOpen(true);},[location.pathname,masterActive]);
 const links=[{path:item.path,label:item.label,Icon},...(role==='admin'?[{path:'/visits',label:'Monitoring kunjungan',Icon:ClipboardList},{path:'/billing-monitor',label:'Monitoring tagihan',Icon:Wallet},{path:'/users',label:'Pengguna',Icon:UsersRound},{path:'/audit',label:'Audit log',Icon:History}]:[])];
 const navLink=({path,label,Icon}:{path:string;label:string;Icon:typeof UserRound})=><Tooltip key={path} title={compact?label:undefined} placement="right"><Link to={path} onClick={onNavigate} aria-label={label} aria-current={(location.pathname===path||location.pathname.startsWith(path+'/'))?'page':undefined} className={`nav-link ${(location.pathname===path||location.pathname.startsWith(path+'/'))?'active':''}`}><Icon size={20}/>{!compact&&<span>{label}</span>}</Link></Tooltip>;
 return <div className="nav-inner"><Brand compact={compact}/><nav aria-label="Navigasi utama"><div className="nav-label">{compact?'':'RUANG KERJA'}</div>{links.map(navLink)}{masters.length>0&&(compact?<Dropdown trigger={['click']} placement="bottomRight" menu={{selectedKeys:masterActive?[location.pathname]:[],items:masters.map(r=>({key:masterPath(r),label:<Link to={masterPath(r)} onClick={onNavigate}>{catalog[r].label}</Link>}))}}><button type="button" className={`nav-link nav-parent ${masterActive?'active':''}`} aria-label="Master Data" title="Master Data"><Database size={20}/></button></Dropdown>:<><button type="button" className={`nav-link nav-parent ${masterActive?'active':''}`} aria-expanded={masterOpen} onClick={()=>setMasterOpen(v=>!v)}><Database size={20}/><span>Master Data</span><ChevronDown size={16} className={masterOpen?'nav-chevron open':'nav-chevron'}/></button>{masterOpen&&<div className="nav-submenu">{masters.map(r=><Link key={r} to={masterPath(r)} onClick={onNavigate} aria-current={location.pathname===masterPath(r)?'page':undefined} className={`nav-sublink ${location.pathname===masterPath(r)?'active':''}`}>{catalog[r].label}</Link>)}</div>}</>)}{navLink({path:'/profile',label:'Profil saya',Icon:UserRound})}</nav><div className="sidebar-footer">{compact?<Activity size={20}/>:<><span className="status-dot"/> <div><strong>SIMRS Outpatient</strong><small>Demo • v1.1.0</small></div></>}</div></div>;
}
export function Shell({children}:{children:ReactNode}){
 const {user,signOut}=useSession(),navigate=useNavigate(),location=useLocation(),{message,modal}=AntApp.useApp();
 const [width,setWidth]=useState(window.innerWidth),[collapsed,setCollapsed]=useState(window.innerWidth<1280),[drawer,setDrawer]=useState(false),[busy,setBusy]=useState(false);
 useEffect(()=>{const onResize=()=>setWidth(window.innerWidth);window.addEventListener('resize',onResize);return()=>window.removeEventListener('resize',onResize);},[]);
 useEffect(()=>{setCollapsed(width<1280);if(width>=1024)setDrawer(false);},[width<1280,width<1024]);
 useEffect(()=>{window.scrollTo(0,0);},[location.pathname]);
 if(!user)return null;
 const item=workspace[user.role],mobile=width<1024,resource=masterResource(location.pathname);
 const logout=()=>modal.confirm({title:'Keluar dari aplikasi?',content:'Sesi Anda akan diakhiri. Draft yang belum disimpan akan hilang; pastikan transaksi sudah terkonfirmasi.',okText:'Keluar',cancelText:'Batal',onOk:async()=>{setBusy(true);try{const revoked=await signOut();if(!revoked)message.warning('Sesi lokal dihapus, tetapi pencabutan token di server belum terkonfirmasi.');navigate('/login',{replace:true});}finally{setBusy(false);}}});
 return <div className={`app-shell ${!mobile&&collapsed?'collapsed':''}`}>
  {!mobile&&<aside className="sidebar"><Nav role={user.role} compact={collapsed}/></aside>}
  <Drawer placement="left" open={mobile&&drawer} onClose={()=>setDrawer(false)} width={280} title="Menu" styles={{body:{padding:0}}}><Nav role={user.role} onNavigate={()=>setDrawer(false)}/></Drawer>
  <div className="app-main"><a className="skip-link" href="#main-content" onClick={e=>{e.preventDefault();document.getElementById('main-content')?.focus();}}>Lewati ke konten utama</a><header className="topbar"><div className="top-left"><Button type="text" aria-label={mobile?'Buka menu':collapsed?'Perluas sidebar':'Ciutkan sidebar'} icon={mobile?<Menu size={20}/>:collapsed?<PanelLeftOpen size={20}/>:<PanelLeftClose size={20}/>} onClick={()=>mobile?setDrawer(true):setCollapsed(!collapsed)}/><div className="breadcrumb">Ruang kerja <span>/</span> <strong>{location.pathname==='/users'?'Pengguna':location.pathname==='/audit'?'Audit log':location.pathname==='/profile'?'Profil saya':location.pathname==='/visits'?'Monitoring kunjungan':location.pathname.startsWith('/billing-monitor')?'Monitoring tagihan':resource?catalog[resource].label:item.label}</strong></div></div><Dropdown trigger={['click']} menu={{items:[{key:'profile',label:'Profil saya',icon:<UserRound size={16}/>,onClick:()=>navigate('/profile')},{key:'logout',label:'Keluar',icon:<LogOut size={16}/>,danger:true,onClick:logout}]}}><button className="profile-trigger" disabled={busy} aria-label="Menu akun"><span className="avatar">{user.name.slice(0,1).toUpperCase()}</span><span className="profile-name"><strong>{user.name}</strong><small>{item.roleLabel}</small></span><ChevronDown size={16}/></button></Dropdown></header><main className="content" id="main-content" tabIndex={-1}>{children}</main></div>
 </div>;
}
