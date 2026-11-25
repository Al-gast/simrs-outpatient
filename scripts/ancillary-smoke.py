#!/usr/bin/env python3
"""Integration scenarios; run ONLY on a disposable dummy database. Not executed in authoring environment."""
import concurrent.futures, datetime, json, os, pathlib, subprocess, sys, threading, urllib.error, urllib.parse, urllib.request, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''));assert PREFIX
CORE='http://localhost:8080/api/v1'; NODE='http://localhost:3001/api/v1'; RUN=uuid.uuid4().hex[:10].upper(); count=0; lock=threading.Lock()
def req(path,expected=200,method='GET',body=None,token=None,key=None,base=CORE):
    global count
    h={'Accept':'application/json','X-Request-ID':'ancillary-'+uuid.uuid4().hex}
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
    p=create('patients',{'name':f'Ancillary dummy {RUN} {suffix}','identity_no':f'A-{RUN}-{suffix}','birth_date':'1990-01-01','sex':'U'},reg)
    v=data('/visits',201,'POST',{'patient_id':p['id'],'schedule_id':schedule['id']},reg);path='/visits/'+v['id'];data(path+'/start',200,'POST',token=doc);data(path+'/medical-record',200,'PUT',record,doc);return path

def stock(m):return int(data('/master/medicines/'+m['id'],token=admin)['stock'])
def adjust(m,delta):return data('/master/medicines/'+m['id']+'/adjust-stock',200,'POST',{'delta':delta,'reason':'Ancillary dummy stock'},admin,str(uuid.uuid4()))
def med(suffix,quantity):
    m=create('medicines',{'code':f'AM-{RUN}-{suffix}','name':f'Obat dummy {suffix}','unit':'tablet','price':'2500'});adjust(m,quantity);return m
extra=med('A',20);scarce=med('B',5)
tests=[create('lab-tests',{'code':f'AL-{RUN}-{i}','name':f'Tes dummy {i}','unit':'unit','price':'150000'}) for i in (1,2)]
paths=[visit('1'),visit('2')];items=[dict(medicine_id=m['id'],quantity=q,dosage='Dosis dummy',frequency='Frekuensi dummy') for m,q in [(extra,2),(scarce,5)]]
rxbody={'items':items,'expected_version':1};labbody={'items':[{'lab_test_id':m['id']} for m in tests],'expected_version':2}
for role,t in tokens.items():
    if role!='doctor':req(paths[0]+'/prescription',403,'PUT',rxbody,t)
    if role!='pharmacy':req('/pharmacy/orders',403,token=t)
    if role!='laboratory':req('/laboratory/orders',403,token=t);req('/laboratory/items/1/result',403,'POST',{'result_text':'dummy'},t,key=str(uuid.uuid4()),base=NODE)
# Both prescriptions validate available stock but neither reserves it.
for p in paths:
    req(p+'/prescription',422,'PUT',{**rxbody,'items':[dict(items[0],quantity=0)]},doc)
    a=data(p+'/prescription',200,'PUT',rxbody,doc);assert a['record']['version']==2 and a['reason_required']['prescription'] is True
    req(p+'/prescription',422,'PUT',{**rxbody,'expected_version':2},doc)
    code(req(p+'/prescription',409,'PUT',rxbody,doc),'STALE_RECORD')
    a=data(p+'/lab-order',200,'PUT',labbody,doc);assert a['record']['version']==3 and len(a['lab_orders'][0]['items'])==2 and a['reason_required']['lab_order'] is True
    req(p+'/lab-order',422,'PUT',{**labbody,'expected_version':3},doc)
    req('/pharmacy/orders/'+a['prescription']['id'],404,token=pharm)
assert stock(extra)==20 and stock(scarce)==5
# Complete one lab item during active exam; no early visit advance and edits lock.
first=data(paths[0]+'/clinical',token=doc);order=first['lab_orders'][0];iid=order['items'][0]['id'];labpath='/laboratory/items/'+iid+'/result';key=str(uuid.uuid4())
assert data('/laboratory/orders/'+order['id'],token=lab,base=NODE)['id']==order['id']
req(labpath,422,'POST',{'result_text':'dummy'},lab,base=NODE)
results=race(lambda:req(labpath,200,'POST',{'result_text':'Hasil dummy'},lab,key, NODE),lambda:req(labpath,200,'POST',{'result_text':'Hasil dummy'},lab,key,NODE));assert all(r[1]['data']['status']=='pending' for r in results)
code(req(labpath,409,'POST',{'result_text':'Berbeda'},lab,key,NODE),'IDEMPOTENCY_CONFLICT')
code(req(labpath,409,'POST',{'result_text':'Hasil dummy'},lab,str(uuid.uuid4()),NODE),'RESULT_EXISTS')
code(req(paths[0]+'/lab-order',409,'PUT',{**labbody,'expected_version':3},doc),'ORDER_LOCKED')
assert data(paths[0]+'/clinical',token=doc)['visit']['status']=='in_exam'
# Finish both and race entire prescriptions. First item is ample, last item scarce: loser rolls back ALL stock movements.
for p in paths:assert data(p+'/finish-exam',200,'POST',{'expected_version':3},doc)['visit']['status']=='services_pending'
orders=[data(p+'/clinical',token=doc)['prescription'] for p in paths];disp=[f"/pharmacy/orders/{o['id']}/dispense" for o in orders]
results=race(*(lambda p=p:req(p,[200,409],'POST',{},pharm) for p in disp));assert sorted(r[0] for r in results)==[200,409]
winner=next(i for i,r in enumerate(results) if r[0]==200);loser=1-winner;code(results[loser],'INSUFFICIENT_STOCK');assert stock(extra)==18 and stock(scarce)==0
assert data(disp[winner],200,'POST',{},pharm)['status']=='dispensed';assert stock(extra)==18
assert data(paths[winner]+'/clinical',token=doc)['visit']['status']=='services_pending'
if '--db-check' in sys.argv:
    for i,o in enumerate(orders):
        oid=int(o['id']);expected=2 if i==winner else 0
        assert int(sql(f'SELECT count(*) FROM stock_movements s JOIN prescription_items p ON p.id=s.prescription_item_id WHERE p.prescription_id={oid}'))==expected
    assert sql(f"SELECT count(*) FROM lab_result_events WHERE event_key='{key}'")=='1'
adjust(scarce,5);assert data(disp[loser],200,'POST',{},pharm)['status']=='dispensed';assert stock(extra)==16 and stock(scarce)==0
# Complete remaining lab items, replay each event, verify visit status and physician clinical history snapshot.
for p in paths:
    o=data(p+'/clinical',token=doc)['lab_orders'][0]
    for i in o['items']:
        if i['result_text'] is not None:continue
        k=str(uuid.uuid4());route='/laboratory/items/'+i['id']+'/result';payload={'result_text':'Hasil dummy'}
        data(route,200,'POST',payload,lab,k,NODE);data(route,200,'POST',payload,lab,k,NODE)
    end=data(p+'/clinical',token=doc);assert end['visit']['status']=='ready_for_billing' and end['lab_orders'][0]['status']=='completed' and end['prescription']['status']=='dispensed'
# A cleared section is still historical: re-add requires reason even without current rows.
p=visit('reason-history')
rx={'items':[dict(medicine_id=extra['id'],quantity=1,dosage='Dummy',frequency='Dummy')],'expected_version':1}
a=data(p+'/prescription',200,'PUT',rx,doc);assert a['record']['version']==2
req(p+'/prescription',422,'PUT',{'items':[],'expected_version':2},doc)
a=data(p+'/prescription',200,'PUT',{'items':[],'expected_version':2,'reason':'Clear dummy'},doc)
assert a['prescription'] is None and a['reason_required']['prescription'] is True
req(p+'/prescription',422,'PUT',{**rx,'expected_version':3},doc)
a=data(p+'/prescription',200,'PUT',{**rx,'expected_version':3,'reason':'Re-add dummy'},doc);assert a['record']['version']==4
lab_initial={'items':[{'lab_test_id':tests[0]['id']}],'expected_version':4}
a=data(p+'/lab-order',200,'PUT',lab_initial,doc);assert a['record']['version']==5
a=data(p+'/lab-order',200,'PUT',{'items':[],'expected_version':5,'reason':'Clear dummy'},doc)
assert not a['lab_orders'] and a['reason_required']['lab_order'] is True
req(p+'/lab-order',422,'PUT',{**lab_initial,'expected_version':6},doc)
# Optional actual service outage test (restart even on failure); core read remains available while Node is offline.
if '--outage' in sys.argv:
    subprocess.run(['docker','compose','stop','node'],cwd=ROOT,check=True)
    try:
        assert data(paths[0]+'/clinical',token=doc)['visit']['status']=='ready_for_billing'
        try:req('/health',base=NODE);raise AssertionError('Node should be offline')
        except urllib.error.URLError:pass
    finally:subprocess.run(['docker','compose','start','node'],cwd=ROOT,check=True)
print(f'PASS: {count} ancillary HTTP checks; concurrency + idempotency scenarios')
