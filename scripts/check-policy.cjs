const assert=require('node:assert/strict');
const path=require('node:path');
const root=path.resolve(process.argv[2]);
const n=require(path.join(root,'backend-node/src/policy.js'));
const f=require(path.join(root,'frontend/src/lib/roles.js'));
const m=require(path.join(root,'frontend/src/lib/master.js'));
let c=0;
for(const a of n.roles){
 for(const b of n.roles){assert.equal(n.permits(a,[b]),a===b);assert.equal(f.canAccess(a,f.workspace[b].path),a===b);c+=2;}
 assert.equal(f.canAccess(a,'/profile'),true);assert.equal(f.canAccess(a,'/unknown'),false);c+=2;
}
assert.equal(n.permits('ADMIN',['admin']),false);assert.equal(n.permits(null,['admin']),false);
assert.equal(n.validRequestId('request-12'),true);assert.equal(n.validRequestId('bad\nheader'),false);
assert.equal(n.validBearer('Bearer a.b.c'),true);assert.equal(n.validBearer('Bearer a.b'),false);c+=6;
const expected={patients:['admin','registration','doctor'],'medical-staff':['admin','registration','doctor'],clinics:['admin','registration','doctor'],'doctor-schedules':['admin','registration','doctor'],procedures:['admin','doctor'],medicines:['admin','doctor','pharmacy'],'lab-tests':['admin','doctor','laboratory']};
for(const a of n.roles)for(const r of m.resources){assert.equal(f.canAccess(a,m.masterPath(r)),expected[r].includes(a));assert.equal(m.canWriteMaster(a,r),a==='admin'||(a==='registration'&&r==='patients'));c+=2;}
const v=require(path.join(root,'frontend/src/lib/visits.js'));
for(const a of n.roles){assert.equal(f.canAccess(a,'/visits'),a==='admin');assert.equal(v.canRegister(a),a==='registration');assert.equal(v.canReadHistory(a),['registration','doctor'].includes(a));c+=3;for(const status of v.visitStatuses){assert.equal(v.canStartVisit(a,status),a==='doctor'&&status==='waiting');c++;}}
assert.equal(v.queueLabel('UMUM',1),'UMUM-001');assert.equal(v.queueLabel('UMUM',1000),'UMUM-1000');c+=2;
console.log(`PASS: ${c} TypeScript policy assertions`);

const cl=require(path.join(root,'frontend/src/lib/clinical.js'));
let clinical=0;
for(const role of n.roles)for(const status of v.visitStatuses){assert.equal(cl.canOpenClinical(role,status),role==='doctor'&&status!=='waiting');clinical++;}
for(const status of v.visitStatuses){assert.equal(cl.canEditClinical(status),status==='in_exam');assert.equal(cl.canAmendDiagnosis(status),['services_pending','ready_for_billing','completed'].includes(status));clinical+=2;}
const d=cl.draft(null);assert.equal(cl.validDraft(d),false);clinical++;
Object.assign(d,{complaint:'Dummy',anamnesis:'Dummy',physical_exam:'Dummy',primary_diagnosis:'A',secondary_diagnoses:['B'],vital_signs:{systolic:120,diastolic:80,pulse:80,temperature:37,respiratory_rate:20,weight:65}});assert.equal(cl.validDraft(d),true);clinical++;
for(const f of cl.vitalFields){assert.equal(cl.validDraft({...d,vital_signs:{...d.vital_signs,[f.key]:null}}),false);assert.equal(cl.validDraft({...d,vital_signs:{...d.vital_signs,[f.key]:f.max+1}}),false);clinical+=2;}
assert.equal(cl.validDraft({...d,secondary_diagnoses:['A']}),false);assert.equal(cl.validDraft({...d,vital_signs:{...d.vital_signs,systolic:70}}),false);assert.equal(cl.procedureTotal([]),'0');assert.equal(cl.procedureTotal([{unit_price:'999999999999',quantity:100},{unit_price:'1',quantity:2}]),'99999999999902');clinical+=4;
console.log(`PASS: ${clinical} clinical TypeScript assertions`);
const an=require(path.join(root,'frontend/src/lib/ancillary.js'));
let ancillary=0;const check=(actual,expected)=>{assert.equal(actual,expected);ancillary++;};
const line={master_id:'1',name:'Dummy',price:'2500',quantity:2,dosage:'dummy',frequency:'dummy',stock:3};
check(an.validOrder([line],true),true);check(an.validOrder([],true),true);check(an.validOrder([line,line],true),false);check(an.validOrder([line],false),true);
for(const quantity of [0,10001,1.2,-1])check(an.validOrder([{...line,quantity}],true),false);
for(const k of ['dosage','frequency'])check(an.validOrder([{...line,[k]:' '}],true),false);
check(an.validOrder([{...line,master_id:'0'}],false),false);check(an.orderTotal([line],true),'5000');check(an.orderTotal([line],false),'2500');check(an.orderTotal([],true),'0');
const item={id:'1',medicine_id:'1',name_snapshot:'Dummy',unit_price:'2500',quantity:2,dosage:'dummy',frequency:'dummy',stock:3};
const order={id:'1',visit_id:'1',status:'pending',visit_status:'services_pending',items:[item]};
for(const status of ['pending','dispensed','completed'])for(const visit_status of v.visitStatuses)check(an.canDispense({...order,status,visit_status}),status==='pending'&&visit_status==='services_pending');
check(an.canDispense({...order,items:[{...item,stock:1}]}),false);check(an.canDispense({...order,items:[]}),false);check(an.orderDraft(order)[0].master_id,'1');check(an.orderDraft(null).length,0);
console.log(`PASS: ${ancillary} ancillary TypeScript assertions`);
const bill=require(path.join(root,'frontend/src/lib/billing.js'));let billing=0;const bc=(a,b)=>{assert.equal(a,b);billing++;};
for(const role of n.roles){bc(bill.canPay(role),role==='cashier');bc(f.canAccess(role,'/billing-monitor'),role==='admin');}
for(const amount of ['0','01','-1','1.5','1e3',' 1','1000000000000000000',''])bc(bill.validAmount(amount,'100000000000000000'),false);
bc(bill.validAmount('1','1'),true);bc(bill.validAmount('2','1'),false);bc(bill.validAmount('1',null),false);bc(bill.validAmount('999999999999999999','999999999999999999'),true);
bc(bill.lineTotal({unit_price:'999999999999',quantity:10000}),'9999999999990000');
const pending={key:'abcdef00-1234-4567-89ab-0123456789ab',body:{amount:'1',method:'cash',expected_revision:1}};bc(bill.validPending(pending),true);bc(bill.validPending(null),false);bc(bill.validPending({...pending,key:'bad'}),false);for(const body of [{...pending.body,amount:'0'},{...pending.body,method:'other'},{...pending.body,expected_revision:0}])bc(bill.validPending({...pending,body}),false);bc(bill.pendingStorageKey('1','2')===bill.pendingStorageKey('2','1'),false);
console.log(`PASS: ${billing} billing TypeScript assertions`);
const rp=require(path.join(root,'backend-node/src/report-policy.js'));const fr=require(path.join(root,'frontend/src/lib/reporting.js'));let report=0;const rc=(a,b)=>{assert.equal(a,b);report++;};const rr=(fn)=>{assert.throws(fn);report++;};
const period={from:'2024-01-01',to:'2024-12-31'};rc(rp.parseReport(period,false).from,period.from);rc(fr.validPeriod(period),true);
for(const dates of [{from:'2024-02-29',to:'2024-02-29'},{from:'2026-11-01',to:'2026-11-24'}]){rc(fr.validPeriod(dates),true);rc(rp.parseReport(dates,false).to,dates.to);}
for(const dates of [{from:'2026-02-29',to:'2026-03-01'},{from:'2026-11-24',to:'2026-11-23'},{from:'2024-01-01',to:'2025-01-01'},{from:'2026-1-01',to:'2026-11-24'},{from:'2026-04-31',to:'2026-05-01'}]){rc(fr.validPeriod(dates),false);rr(()=>rp.parseReport(dates,false));}
for(const metric of rp.metrics){rc(rp.parseReport({...period,metric,page:'2',per_page:'100'},true).page,2);rc(rp.parseReport({...period,metric},true).metric,metric);}
for(const v of [{metric:'invalid'},{metric:['visits']},{metric:'visits',page:'0'},{metric:'visits',per_page:'101'},{metric:'visits',page:'10001'},{metric:'visits',clinic_id:'9223372036854775808'},{metric:'visits',clinic_id:'0'},{metric:'revenue',clinic_id:'1'},{metric:'visits',page:['1']},{metric:'visits',sort:'amount'}])rr(()=>rp.parseReport({...period,...v},true));
rc(rp.parseReport({...period,metric:'clinics',clinic_id:'1'},true).clinic,'1');rr(()=>rp.parseReport({...period,metric:'visits'},false));rr(()=>rp.parseReport({...period,from:['2024-01-01']},false));
rc(fr.formatNumber('9007199254740993'),'9.007.199.254.740.993');rc(fr.formatNumber('NaN'),'—');rc(fr.formatNumber('0'),'0');for(const role of n.roles)for(const route of ['/users','/audit'])rc(f.canAccess(role,route),role==='admin');
console.log(`PASS: ${report} reporting/admin TypeScript assertions`);

let qa=0;for(const value of ['1.5','1,5','Rp 10000','10.000','10000\n','-5000','1000000000000000000']){assert.equal(bill.validAmount(value,'999999999999999999'),false);qa++;}
assert.equal(bill.validAmount('10000','10000'),true);qa++;
console.log(`PASS: ${qa} QA billing input assertions`);

const ui=require(path.join(root,'frontend/src/lib/presentation.js'));let presentation=0;const uc=(a,b)=>{assert.equal(a,b);presentation++;};
for(const [raw,shown] of [['',''],['0','0'],['1000','1.000'],['150000','150.000'],['9007199254740993','9.007.199.254.740.993'],['1.5','1.5'],['-1000','-1000'],['01','01']])uc(ui.groupRupiah(raw),shown);
for(const [raw,canonical] of [['150.000','150000'],['1.000.001','1000001'],['100','100'],['1.5','1.5'],['1,5','1,5'],['1.000,50','1.000,50'],['-1.000','-1.000'],['Rp 1.000','Rp 1.000'],['01.000','01.000'],['1..000','1..000']])uc(ui.normalizeRupiah(raw),canonical);
for(const [old,next,type,expected] of [['','1','insertText','1'],['999','9999','insertText','9999'],['1000','1.0005','insertText','10005'],['150000','150.00','deleteContentBackward','15000'],['1000','1000','deleteContentBackward','000'],['1000','1000','deleteContentForward','100'],['1','1.','insertText','1.'],['1.','1.5','insertText','1.5'],['150000','1.5','insertFromPaste','1.5'],['150000','250.000','insertFromPaste','250000'],['150000','-50.000','insertFromPaste','-50.000'],['150000','abc','insertFromPaste','abc'],['150000','','deleteByCut',''],['150000','150.000x','insertText','150.000x']])uc(ui.editRupiah(old,next,type),expected);
for(const invalid of ['1.5','1,5','-1.000','Rp 1.000','1.000,50','01.000','1..000','abc','1000000000000000000'])uc(bill.validAmount(ui.normalizeRupiah(invalid),'999999999999999999'),false);
uc(bill.validAmount(ui.normalizeRupiah('150.000'),'150000'),true);uc(bill.validAmount(ui.normalizeRupiah('150.001'),'150000'),false);
uc(ui.dateLabel('2024-02-29'),new Date('2024-02-29T00:00:00Z').toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric',timeZone:'UTC'}));
for(const d of ['2026-02-29','2026-04-31','2026-13-01','2026-1-1','','2026-11-24T00:00:00Z'])uc(ui.dateLabel(d),'—');
uc(ui.dateTimeLabel('2026-11-24 16:30:00+00'),ui.dateTimeLabel('2026-11-24T16:30:00Z'));uc(ui.dateTimeLabel('2026-11-24T16:30:00Z'),ui.dateTimeLabel('2026-11-24T23:30:00+07:00'));uc(ui.dateTimeLabel('2026-11-24 16:30:00'),'—');uc(ui.dateTimeLabel('not a timestamp'),'—');
for(const s of ['paid','completed','dispensed','active'])uc(ui.statusColor(s),'green');for(const s of ['pending','waiting'])uc(ui.statusColor(s),'gold');uc(ui.statusColor('unpaid'),'red');uc(ui.statusColor('partial'),'orange');uc(ui.statusColor('unknown'),'default');
uc(ui.emptyLabel('resep',true),'Tidak ada resep yang sesuai dengan filter.');uc(ui.emptyLabel('resep',false),'Belum ada resep.');
for(const role of n.roles){assert.deepEqual(m.masterNav(role),m.resources.filter(r=>expected[r].includes(role)));presentation++;}
for(const quantity of [null,0,-1,1.5,1000001,NaN,Infinity])for(const direction of [1,-1])uc(ui.signedStockDelta(direction,quantity),null);
uc(ui.signedStockDelta(1,50),50);uc(ui.signedStockDelta(-1,30),-30);uc(ui.signedStockDelta(-1,1000000),-1000000);
console.log(`PASS: ${presentation} presentation assertions`);

const wf=require(path.join(root,'frontend/src/lib/workflow.js'));let workflow=0;const wc=(a,b)=>{assert.deepEqual(a,b);workflow++;};
for(const [index,step] of wf.clinicalSteps.entries()){
 for(const saved of [true,false])for(const dirty of [true,false])for(const busy of [true,false])wc(wf.nextClinicalStep(step,saved,dirty,busy),saved&&!dirty&&!busy?wf.clinicalSteps[index+1]??null:null);
 for(const edit of [true,false])for(const saved of [true,false])for(const dirty of [true,false])for(const busy of [true,false])for(const reload of [true,false])wc(wf.canFinishReview(step,edit,saved,dirty,busy,reload),step==='review'&&edit&&saved&&!dirty&&!busy&&!reload);
}
wc(wf.nextClinicalStep('invalid',true,false,false),null);
for(const [raw,formatted] of [['INV-2','INV-000002'],['PAY-1','PAY-000001'],['RX-12','RX-000012'],['LAB-123','LAB-000123'],['INV-1234567','INV-1234567'],['INV-9007199254740993','INV-9007199254740993'],['INV-000002','INV-000002'],['OTHER-1','OTHER-1'],[null,'—']])wc(wf.transactionLabel(raw),formatted);
for(const [raw,q] of [['INV-000002','INV-2'],['inv-000123','INV-123'],['INV-1234567','INV-1234567'],['INV-000000','INV-000000'],['Siti Rahma','Siti Rahma'],['RM-000003','RM-000003'],['INV-0001x','INV-0001x']])wc(wf.invoiceSearch(raw),q);
for(const [total,paid,status,pct] of [['350000','150000','partial',42.8],['350000','350000','paid',100],['350000','0','unpaid',0],['0','0','paid',100],[null,'0','unissued',0],['999999999999999999','500000000000000000','partial',50],['100','99','partial',99],['100','101','paid',100]])wc(wf.paymentPercent(total,paid,status),pct);
const lines=['laboratory','procedure','medicine','registration','procedure'].map((category,index)=>({category,source_id:String(index),description:'Test',unit_price:'1',quantity:1}));wc(wf.orderedLines(lines).map(x=>x.source_id),['3','1','4','2','0']);wc(lines.map(x=>x.source_id),['0','1','2','3','4']);
wc(wf.ancillaryEmpty(true,true),'Tidak ada resep obat pada kunjungan ini.');wc(wf.ancillaryEmpty(false,true),'Tidak ada permintaan laboratorium pada kunjungan ini.');wc(wf.ancillaryEmpty(true,false),'Simpan rekam medis dan mulai pemeriksaan untuk membuat permintaan.');
for(const role of n.roles){for(const [route,allowed] of [['/doctor/1','doctor'],['/billing/12','cashier'],['/billing-monitor/13','admin']])wc(f.canAccess(role,route),role===allowed);for(const route of ['/doctor/0','/doctor/abc','/doctor/1/2','/billing/-1','/billing-monitor/01','/billing/1/'])wc(f.canAccess(role,route),false);}
console.log(`PASS: ${workflow} workflow and dedicated-route assertions`);

// Execute the actual document generator against a minimal DOM double, not a browser renderer.
const printer=require(path.join(root,'frontend/src/lib/print-document.js'));let printChecks=0;const pc=(a,b)=>{assert.deepEqual(a,b);printChecks++;};
class PrintNode{constructor(tag){this.tag=tag;this.children=[];this.textContent='';this.className='';this.events={};}append(...nodes){this.children.push(...nodes);}createTHead(){const n=new PrintNode('thead');this.append(n);return n;}createTBody(){const n=new PrintNode('tbody');this.append(n);return n;}insertRow(){const n=new PrintNode('tr');this.append(n);return n;}addEventListener(name,fn){this.events[name]=fn;}set innerHTML(v){throw Error('Dynamic HTML prohibited: '+v);}}
function printFake(){const d={head:new PrintNode('head'),body:new PrintNode('body'),documentElement:{},createElement:tag=>new PrintNode(tag)};const win={document:d,opener:{},printCount:0,print(){this.printCount++;},focus(){}};global.window={open:()=>win};return win;}
function descendants(n){return [n,...n.children.flatMap(descendants)];}
const fixture={invoice_no:'INV-2',revision:1,patient_name:'<img src=x onerror=alert(1)>',medical_record_no:'RM-000003',clinic_name:'Poli Umum',visit_date:'2026-11-24',total_amount:'350000',paid_amount:'350000',balance:'0',payment_status:'paid',items:[{category:'laboratory',source_id:'3',description:'Lab',unit_price:'150000',quantity:1},{category:'registration',source_id:'1',description:'Daftar',unit_price:'25000',quantity:1},{category:'medicine',source_id:'2',description:'Obat',unit_price:'2500',quantity:10},{category:'procedure',source_id:'4',description:'Tindakan',unit_price:'150000',quantity:1}]};
let win=printFake();printer.printDocument(fixture);let nodes=descendants(win.document.body);pc(win.opener,null);pc(win.document.title,'INV-000002');pc(nodes.some(n=>n.textContent===fixture.patient_name),true);pc(nodes.some(n=>n.tag==='img'),false);pc(nodes.filter(n=>n.tag==='td').map(n=>n.textContent).filter(t=>t.includes(' · ')),['Pendaftaran · Daftar','Tindakan medis · Tindakan','Obat · Obat','Laboratorium · Lab']);pc(nodes.some(n=>n.textContent==='Rp350.000'),true);pc(nodes.some(n=>n.textContent==='Lunas'),true);nodes.find(n=>n.tag==='button').events.click();pc(win.printCount,1);
win=printFake();printer.printDocument(fixture,{receipt_no:'PAY-1',amount:'150000',method:'cash',cashier_name:'Kasir',paid_at:'2026-11-24T16:21:25Z'});nodes=descendants(win.document.body);pc(win.document.title,'PAY-000001');pc(nodes.some(n=>n.textContent==='Rp150.000'),true);pc(nodes.some(n=>n.textContent.includes('bukan pernyataan')),true);pc(nodes.some(n=>n.tag==='table'),false);delete global.window;
console.log(`PASS: ${printChecks} print DOM-double assertions (not visual/print runtime)`);

const ap=require(path.join(root,'frontend/src/lib/admin-presentation.js'));let adminPresentation=0;const ac=(a,b)=>{assert.deepEqual(a,b);adminPresentation++;};
const g=(status,count,total,paid,outstanding)=>({status,count,total_amount:total,paid_amount:paid,outstanding});
const groups=[g('paid','1','350000','350000','0'),g('unpaid','1','25000','0','25000')];
ac(fr.invoiceTotals(groups),{total:'375000',paid:'350000',outstanding:'25000'});
ac(fr.invoiceTotals([]),{total:'0',paid:'0',outstanding:'0'});
ac(fr.invoiceTotals([g('partial','1','9007199254740993000','9007199254740992999','1')]),{total:'9007199254740993000',paid:'9007199254740992999',outstanding:'1'});
for(const bad of [g('partial','1','100','50','49'),g('partial','1','100','101','-1'),g('partial','1','100','xx','100'),{status:'unpaid',count:'1',outstanding:'50'},g('paid','1','1.5','0','1.5')])ac(fr.invoiceTotals([bad]),null);
ac(fr.paymentSlices(groups),{total:'2',slices:[{status:'paid',start:0,end:50},{status:'unpaid',start:50,end:100}]});
ac(fr.paymentSlices([]),{total:'0',slices:[]});ac(fr.paymentSlices([g('paid','abc','0','0','0')]),{total:'0',slices:[]});
ac(fr.paymentSlices([g('paid','9007199254740993','0','0','0'),g('partial','9007199254740993','0','0','0')]).slices[1].end,100);
ac(fr.chartRatio('1','3'),0.3333);ac(fr.chartRatio('0','0'),0);ac(fr.chartRatio('9007199254740993','9007199254740993'),1);
ac(fr.dailySeries({from:'2024-02-28',to:'2024-03-01'},[{day:'2024-02-29',value:'2'},{day:'2024-03-02',value:'7'}]),[{day:'2024-02-28',value:'0'},{day:'2024-02-29',value:'2'},{day:'2024-03-01',value:'0'}]);
ac(fr.dailySeries({from:'2024-01-01',to:'2024-12-31'},[]).length,366);ac(fr.dailySeries({from:'2024-01-01',to:'2025-01-01'},[]),[]);ac(fr.dailySeries({from:'2026-11-24',to:'2026-11-24'},[]),[{day:'2026-11-24',value:'0'}]);
for(const role of n.roles){const row={name:'Dummy',role,staff_id:role==='doctor'?'9':null,active:true,version:7};const expected={name:row.name,role,staff_id:row.staff_id,active:true,expected_version:7};ac(ap.accountPatch(row,{kind:'reset',password:'Dummy-password-1'}),{...expected,password:'Dummy-password-1'});ac(ap.accountPatch(row,{kind:'status',active:false}),{...expected,active:false});ac(ap.accountPatch({...row,active:false},{kind:'status',active:true}),expected);ac(ap.accountPatch(row,{kind:'edit',name:'New',role,staff_id:row.staff_id}),{...expected,name:'New'});ac('password' in ap.accountPatch(row,{kind:'status',active:false}),false);ac('password' in ap.accountPatch(row,{kind:'edit',name:'New',role,staff_id:row.staff_id}),false);}
ac(ap.accountPatch({name:'Dummy',role:'cashier',staff_id:null,active:true,version:3},{kind:'edit',name:'Dummy',role:'doctor',staff_id:'9'}),{name:'Dummy',role:'doctor',staff_id:'9',active:true,expected_version:3});
for(const code of ['create','update','delete','adjust_stock','register','start_exam','create_medical_record','update_medical_record','replace_visit_procedures','finish_exam','read_clinical_history','replace_prescription','replace_lab_order','dispense_prescription','record_lab_result','ancillary_completed','issue_invoice','revise_invoice','record_payment','complete_visit','create_user','update_user','reset_user_password'])ac(ap.auditLabel(ap.auditActions,code)!==code,true);
for(const code of [...m.resources,'visits','users'])ac(ap.auditLabel(ap.auditEntities,code)!==code,true);
ac(ap.auditLabel(ap.auditActions,'unknown_action'),'unknown_action');ac(ap.auditLabel(ap.auditActions,'constructor'),'constructor');ac(ap.auditOptions(ap.auditActions,['record_payment','legacy_action','legacy_action','<bad>']).filter(o=>o.value==='record_payment').length,1);ac(ap.auditOptions(ap.auditActions,['legacy_action','<bad>']).some(o=>o.value==='legacy_action'),true);ac(ap.auditOptions(ap.auditActions,['legacy_action','<bad>']).some(o=>o.value==='<bad>'),false);
console.log(`PASS: ${adminPresentation} dashboard/account/audit presentation assertions`);

const sp=require(path.join(root,'frontend/src/lib/session-policy.js'));let sessionChecks=0;
for(const [tab,shared,restore,owns] of [[null,null,true,true],[null,'new',true,false],['old','new',false,false],['same','same',true,true],['old',null,false,false]]){
 assert.equal(sp.canRestoreGeneration(tab,shared),restore);assert.equal(sp.ownsGeneration(tab,shared),owns);sessionChecks+=2;
}
console.log(`PASS: ${sessionChecks} browser generation assertions`);
