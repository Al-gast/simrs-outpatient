#!/usr/bin/env python3
"""Integration scenarios; run ONLY on a disposable dummy database. Not executed in authoring environment."""
import concurrent.futures, datetime, json, os, pathlib, subprocess, sys, threading, urllib.error, urllib.parse, urllib.request, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''));assert PREFIX
CORE='http://localhost:8080/api/v1'; NODE='http://localhost:3001/api/v1'; RUN=uuid.uuid4().hex[:10].upper(); count=0; lock=threading.Lock()
def req(path,expected=200,method='GET',body=None,token=None,key=None,base=CORE):
    global count
    h={'Accept':'application/json','X-Request-ID':'billing-'+uuid.uuid4().hex}
    if token:h['Authorization']='Bearer '+token
    if key:h['Idempotency-Key']=key
    if body is not None:h['Content-Type']='application/json'
    r=urllib.request.Request(base+path,method=method,headers=h,data=json.dumps(body).encode() if body is not None else None)
    try:response=urllib.request.urlopen(r,timeout=25)
    except urllib.error.HTTPError as e:response=e
    with response:
        p=json.loads(response.read());s=response.status
        assert s in ([expected] if isinstance(expected,int) else expected),(path,s,p.get('error'))
        assert p['meta']['request_id']==h['X-Request-ID']
        with lock:count+=1
        return s,p

def data(*args,**kwargs):return req(*args,**kwargs)[1]['data']
def code(r,c):assert r[1]['error']['code']==c,r[1].get('error')
def login(role):return data('/auth/login',200,'POST',{'username':role,'password':PREFIX+'-'+role})['access_token']
roles=['admin','registration','doctor','pharmacy','laboratory','cashier']; tokens={r:login(r) for r in roles};admin,reg,doc,pharm,lab=(tokens[x] for x in roles[:5])
def create(resource,body,token=admin):return data('/master/'+resource,201,'POST',body,token)
def race(*functions):
    b=threading.Barrier(len(functions))
    def go(fn):b.wait(timeout=10);return fn()
    with concurrent.futures.ThreadPoolExecutor(max_workers=len(functions)) as ex:return list(ex.map(go,functions))
def sql(q):
    return subprocess.check_output(['docker','compose','exec','-T','db','psql','-U','simrs_owner','-d','simrs','-At','-c',q],cwd=ROOT,text=True).strip()
today=req('/visits',token=reg)[1]['meta']['today'];staff=data('/auth/me',token=doc)['staff_id'];weekday=datetime.date.fromisoformat(today).isoweekday()
schedules=data('/master/doctor-schedules?'+urllib.parse.urlencode(dict(doctor_id=staff,weekday=weekday,active='true',per_page=100)),token=admin);schedule=next(s for s in schedules if s['doctor_active'] and s['clinic_active'])
record={'complaint':'Dummy','anamnesis':'Dummy','physical_exam':'Dummy','primary_diagnosis':'Diagnosis dummy','secondary_diagnoses':[],'vital_signs':{'systolic':120,'diastolic':80,'pulse':80,'temperature':37.2,'respiratory_rate':20,'weight':65},'expected_version':0}
def visit(suffix):
    p=create('patients',{'name':f'Billing dummy {RUN} {suffix}','identity_no':f'B-{RUN}-{suffix}','birth_date':'1990-01-01','sex':'U'},reg)
    v=data('/visits',201,'POST',{'patient_id':p['id'],'schedule_id':schedule['id']},reg);path='/visits/'+v['id'];data(path+'/start',200,'POST',token=doc);data(path+'/medical-record',200,'PUT',record,doc);return path

cash=tokens['cashier']
other_username='qa-cashier-'+RUN.lower();other_password='Dummy-'+uuid.uuid4().hex
data('/admin/users',201,'POST',{'username':other_username,'name':'QA cashier','role':'cashier','staff_id':None,'active':True,'password':other_password},admin)
other_cash=data('/auth/login',200,'POST',{'username':other_username,'password':other_password})['access_token']
proc=create('procedures',{'code':'BP-'+RUN,'name':'Tindakan billing dummy','price':'100000'})
medicine=create('medicines',{'code':'BM-'+RUN,'name':'Obat billing dummy','unit':'tablet','price':'2500'})
data('/master/medicines/'+medicine['id']+'/adjust-stock',200,'POST',{'delta':50,'reason':'Dummy billing'},admin,str(uuid.uuid4()))
test=create('lab-tests',{'code':'BL-'+RUN,'name':'Lab billing dummy','unit':'unit','price':'150000'})
def prepare(suffix,all_services=False):
    p=visit(suffix);bill='/billing/visits/'+p.split('/')[-1];req(bill,404,token=cash)
    data(p+'/procedures',200,'PUT',{'items':[{'procedure_id':proc['id'],'quantity':1}],'expected_version':1,'reason':'Tindakan dummy'},doc);version=2
    if all_services:
        data(p+'/prescription',200,'PUT',{'items':[{'medicine_id':medicine['id'],'quantity':10,'dosage':'dummy','frequency':'dummy'}],'expected_version':version,'reason':'Resep dummy'},doc);version+=1
        data(p+'/lab-order',200,'PUT',{'items':[{'lab_test_id':test['id']}],'expected_version':version,'reason':'Lab dummy'},doc);version+=1
    r=data(p+'/finish-exam',200,'POST',{'expected_version':version},doc)
    if all_services:
        assert r['visit']['status']=='services_pending';req(bill,404,token=cash)
        data('/pharmacy/orders/'+r['prescription']['id']+'/dispense',200,'POST',{},pharm);req(bill,404,token=cash)
        data('/laboratory/items/'+r['lab_orders'][0]['items'][0]['id']+'/result',200,'POST',{'result_text':'Dummy'},lab,str(uuid.uuid4()),NODE)
    invoice=data(bill,token=cash);assert invoice['invoice_id'] and invoice['revision']==1 and invoice['payment_status']=='unpaid'
    assert int(invoice['total_amount'])==sum(int(i['unit_price'])*i['quantity'] for i in invoice['items'])
    return p,bill,invoice,version+1
p,bill,inv,ver=prepare('1',True)
assert {i['category'] for i in inv['items']}=={'registration','procedure','medicine','laboratory'}
fees={i['category']:int(i['unit_price'])*i['quantity'] for i in inv['items']};assert fees['procedure']==100000 and fees['medicine']==25000 and fees['laboratory']==150000
assert data(bill+'/invoice',200,'POST',{},cash)['invoice_id']==inv['invoice_id']
for role,t in tokens.items():
    if role not in ['admin','cashier']:req('/billing/visits',403,token=t);req(bill,403,token=t)
    if role!='cashier':req(bill+'/payments',403,'POST',{},t);req(bill+'/invoice',403,'POST',{},t)
assert data(bill,token=admin)['invoice_id']==inv['invoice_id']
data('/master/procedures/'+proc['id'],200,'PATCH',{'price':'999999'},admin)
change={'items':[{'procedure_id':proc['id'],'quantity':2}],'expected_version':ver,'reason':'Revisi dummy'}
data(p+'/procedures',200,'PUT',change,doc);ver+=1
new=data(bill,token=cash);assert new['revision']==2 and int(new['total_amount'])==int(inv['total_amount'])+100000
assert next(i for i in new['items'] if i['category']=='procedure')['unit_price']=='100000'
key=str(uuid.uuid4());body={'amount':'10000','method':'cash','expected_revision':2}
code(req(bill+'/payments',409,'POST',{**body,'expected_revision':1},cash,str(uuid.uuid4())),'STALE_INVOICE')
code(req(bill+'/payments',409,'POST',{**body,'amount':str(int(new['total_amount'])+1)},cash,str(uuid.uuid4())),'OVERPAYMENT')
req(bill+'/payments',422,'POST',body,cash)
res=race(lambda:req(bill+'/payments',200,'POST',body,cash,key),lambda:req(bill+'/payments',200,'POST',body,cash,key))
a,b=[r[1]['data'] for r in res];assert a['payment_id']==b['payment_id'] and sorted([a['replayed'],b['replayed']])==[False,True]
code(req(bill+'/payments',409,'POST',{**body,'method':'non_cash'},cash,key),'IDEMPOTENCY_CONFLICT')
code(req(bill+'/payments',409,'POST',body,other_cash,key),'IDEMPOTENCY_CONFLICT')
code(req(p+'/procedures',409,'PUT',{**change,'expected_version':ver},doc),'BILLING_LOCKED')
cur=data(bill,token=cash);assert cur['paid_amount']=='10000' and cur['payment_status']=='partial' and len(cur['payments'])==1 and cur['visit_status']=='ready_for_billing'
last={'amount':cur['balance'],'method':'non_cash','expected_revision':2};keys=[str(uuid.uuid4()),str(uuid.uuid4())]
cashiers=[cash,other_cash]
res=race(*(lambda k=k,t=t:req(bill+'/payments',[200,409],'POST',last,t,k) for k,t in zip(keys,cashiers)));assert sorted(r[0] for r in res)==[200,409];code(next(r for r in res if r[0]==409),'INVOICE_CLOSED')
win=next(i for i,r in enumerate(res) if r[0]==200);assert data(bill+'/payments',200,'POST',last,cashiers[win],keys[win])['replayed'] is True
cur=data(bill,token=cash);assert cur['balance']=='0' and cur['payment_status']=='paid' and cur['visit_status']=='completed' and len(cur['payments'])==2
assert data(p+'/clinical',token=doc)['can_revise_procedures'] is False
p2,b2,i2,v2=prepare('2');code(req(b2+'/payments',409,'POST',body,cash,key),'IDEMPOTENCY_CONFLICT')
chg={'items':[{'procedure_id':proc['id'],'quantity':2}],'expected_version':v2,'reason':'Race dummy'}
racebody={'amount':'1','method':'cash','expected_revision':i2['revision']}
r=race(lambda:req(p2+'/procedures',[200,409],'PUT',chg,doc),lambda:req(b2+'/payments',[200,409],'POST',racebody,cash,str(uuid.uuid4())));assert sorted(x[0] for x in r)==[200,409]
if r[0][0]==200:code(r[1],'STALE_INVOICE');assert data(b2,token=cash)['revision']==2
else:code(r[0],'BILLING_LOCKED');assert data(b2,token=cash)['paid_amount']=='1'
record_data=data(p+'/clinical',token=doc)['record'];record_update={k:record_data[k] for k in ['complaint','anamnesis','physical_exam','vital_signs','primary_diagnosis','secondary_diagnoses']};record_update.update(expected_version=record_data['version'],reason='Koreksi dummy',primary_diagnosis='Diagnosis dummy baru')
data(p+'/medical-record',200,'PUT',record_update,doc);assert data(bill,token=cash)['total_amount']==cur['total_amount']
if '--db-check' in sys.argv:
    iid=int(inv['invoice_id']);vid=int(inv['visit_id']);assert sql(f'SELECT count(*) FROM payments WHERE invoice_id={iid}')=='2'
    assert sql(f'SELECT sum(amount) FROM payments WHERE invoice_id={iid}')==cur['total_amount']
    for action,n in [('issue_invoice',1),('revise_invoice',1),('record_payment',2),('complete_visit',1)]:assert sql(f"SELECT count(*) FROM audit_logs WHERE entity_type='visits' AND entity_id={vid} AND action='{action}'")==str(n)
print(f'PASS: {count} HTTP checks plus billing assertions. Dummy data retained.')
