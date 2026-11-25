import React from 'react';
import ReactDOM from 'react-dom/client';
import {App as AntApp,Button,ConfigProvider,Result} from 'antd';
import idID from 'antd/locale/id_ID';
import {QueryClient,QueryClientProvider} from '@tanstack/react-query';
import {createBrowserRouter,RouterProvider,Navigate,Route,Routes,useLocation,useNavigate} from 'react-router-dom';
import {SessionProvider,useSession} from './lib/session';
import {workspace,canAccess} from './lib/roles';
import {Shell} from './components/Shell';
import {Login} from './pages/Login';
import {Reports} from './pages/Reports';
import {Users,AuditLog} from './pages/Admin';
import {Workspace} from './pages/Workspace';
import {Profile} from './pages/Profile';
import {Visits} from './pages/Visits';
import {AncillaryQueue} from './pages/AncillaryQueue';
import {Billing,BillingWorkspace} from './pages/Billing';
import {ClinicalWorkspace} from './pages/Clinical';
import {MasterData} from './pages/MasterData';
import {masterResource} from './lib/master';
import tokens from './tokens.json';
import 'antd/dist/reset.css';
import './styles.css';
const queryClient=new QueryClient({defaultOptions:{queries:{retry:false,staleTime:15000,refetchOnWindowFocus:false}}});
const theme={token:{colorPrimary:tokens.colors.primary,colorLink:tokens.colors.primary,colorLinkHover:tokens.colors.primaryHover,colorLinkActive:tokens.colors.primaryHover,colorInfo:tokens.colors.info,colorSuccess:tokens.colors.success,colorWarning:tokens.colors.warning,colorError:tokens.colors.danger,colorText:tokens.colors.heading,colorTextSecondary:tokens.colors.text,colorBorder:tokens.colors.border,colorBgLayout:tokens.colors.background,borderRadius:tokens.layout.controlRadius,controlHeight:tokens.layout.controlHeight,fontFamily:tokens.typography.family,fontSize:14},components:{Card:{borderRadiusLG:tokens.layout.cardRadius,bodyPadding:tokens.layout.cardPadding},Input:{colorBorder:tokens.colors.controlBorder},Table:{cellPaddingBlock:18},Button:{fontWeight:600}}};
function Protected(){const {user}=useSession(),location=useLocation(),navigate=useNavigate();if(!user)return <Navigate to="/login" replace/>;if(location.pathname==='/')return <Navigate to={workspace[user.role].path} replace/>;const resource=masterResource(location.pathname);return <Shell>{canAccess(user.role,location.pathname)?(location.pathname.startsWith('/doctor/')?<ClinicalWorkspace key={location.pathname} id={location.pathname.split('/')[2]}/>:(/^\/billing(?:-monitor)?\//.test(location.pathname))?<BillingWorkspace key={location.pathname} id={location.pathname.split('/')[2]}/>:location.pathname==='/profile'?<Profile/>:resource?<MasterData key={resource} resource={resource}/>:['/registration','/doctor','/visits'].includes(location.pathname)?<Visits/>:location.pathname==='/pharmacy'?<AncillaryQueue key="pharmacy" rx/>:location.pathname==='/laboratory'?<AncillaryQueue key="laboratory" rx={false}/>:['/billing','/billing-monitor'].includes(location.pathname)?<Billing/>:location.pathname==='/dashboard'?<Reports/>:location.pathname==='/users'?<Users/>:location.pathname==='/audit'?<AuditLog/>:<Workspace/>):<Result status="403" title="Akses tidak tersedia" subTitle="Halaman ini tidak tersedia untuk akun Anda." extra={<Button type="primary" onClick={()=>navigate(workspace[user.role].path)}>Kembali ke ruang kerja</Button>}/>}</Shell>;}
function Root(){return <Routes><Route path="/login" element={<Login/>}/><Route path="*" element={<Protected/>}/></Routes>;}
const router=createBrowserRouter([{path:'*',element:<Root/>}]);
ReactDOM.createRoot(document.getElementById('root')!).render(<React.StrictMode><ConfigProvider locale={idID} theme={theme}><AntApp><QueryClientProvider client={queryClient}><SessionProvider><RouterProvider router={router}/></SessionProvider></QueryClientProvider></AntApp></ConfigProvider></React.StrictMode>);
