#!/usr/bin/env python3
"""Disposable dummy DB only. Creates permanent visits, procedures and audit rows.
Parallel clients exercise the same seeded doctor account with separate HTTP connections.
Optional --db-check reads audit records using the local Compose owner; never prints clinical text.
"""
import concurrent.futures, datetime, json, os, pathlib, subprocess, sys, threading, urllib.error, urllib.parse, urllib.request, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX','')); assert PREFIX
BASE='http://localhost:8080/api/v1'; RUN=uuid.uuid4().hex[:12].upper(); count=0; guard=threading.Lock()
def req(path,expected=200,method='GET',body=None,token=None,key=None):
    global count
    headers={'Accept':'application/json','X-Request-ID':'clinical-'+uuid.uuid4().hex}
    if token:headers['Authorization']='Bearer '+token
    if key:headers['Idempotency-Key']=key
    if body is not None:headers['Content-Type']='application/json'
    r=urllib.request.Request(BASE+path,method=method,headers=headers,data=json.dumps(body).encode() if body is not None else None)
    try:response=urllib.request.urlopen(r,timeout=25)
    except urllib.error.HTTPError as e:response=e
    with response:
        payload=json.loads(response.read()); status=response.status
        assert status in ([expected] if isinstance(expected,int) else expected),(path,status,payload.get('error'))
        assert payload['meta']['request_id']==headers['X-Request-ID']
        with guard:count+=1
        return status,payload

def data(path,expected=200,method='GET',body=None,token=None):return req(path,expected,method,body,token)[1]['data']
def code(r,c):assert r[1]['error']['code']==c,r[1].get('error')
def login(role):return data('/auth/login',200,'POST',{'username':role,'password':PREFIX+'-'+role})['access_token']
roles=['admin','registration','doctor','pharmacy','laboratory','cashier'];tokens={r:login(r) for r in roles};admin,reg,doc=(tokens[x] for x in ('admin','registration','doctor'))
def create(resource,body,token=admin):return data('/master/'+resource,201,'POST',body,token)
def query(path,**args):return path+'?'+urllib.parse.urlencode(args)
def patient(suffix):return create('patients',{'name':f'Clinical dummy {RUN} {suffix}','identity_no':f'C-{RUN}-{suffix}','birth_date':'1990-01-01','sex':'U'},reg)
def register(p,s):return data('/visits',201,'POST',{'patient_id':p['id'],'schedule_id':s['id']},reg)
def race(*functions):
    barrier=threading.Barrier(len(functions))
    def go(fn):barrier.wait(timeout=10);return fn()
    with concurrent.futures.ThreadPoolExecutor(max_workers=len(functions)) as ex:return list(ex.map(go,functions))

today=req('/visits',token=reg)[1]['meta']['today'];weekday=datetime.date.fromisoformat(today).isoweekday();staff=data('/auth/me',token=doc)['staff_id']
# Prefer existing seeded schedule; create separate clinic for the second visit.
existing=data(query('/master/doctor-schedules',doctor_id=staff,weekday=weekday,active='true',per_page=100),token=admin)
assert existing,'Seed an active schedule for the doctor first'
schedule=next(s for s in existing if s['doctor_active'] and s['clinic_active'])
clinic=create('clinics',{'code':'CL-'+RUN,'name':'Clinical dummy clinic '+RUN,'registration_fee':'25000'})
def minutes(t):return int(t[:2])*60+int(t[3:5])
free=next((i for i in range(0,1411,30) if all(i+30<=minutes(x['start_time']) or i>=minutes(x['end_time']) for x in existing)),None)
assert free is not None,'Need one free half-hour on disposable DB'
def hhmm(i):return f'{i//60:02d}:{i%60:02d}'
second_schedule=create('doctor-schedules',{'doctor_id':staff,'clinic_id':clinic['id'],'weekday':weekday,'start_time':hhmm(free),'end_time':hhmm(free+30)})
foreign=create('medical-staff',{'code':'CD-'+RUN,'name':'Other dummy doctor '+RUN,'profession':'doctor'})
foreign_schedule=create('doctor-schedules',{'doctor_id':foreign['id'],'clinic_id':clinic['id'],'weekday':weekday,'start_time':'08:00','end_time':'12:00'})
other_username='qa-doctor-'+RUN.lower();other_password='Dummy-'+uuid.uuid4().hex
data('/admin/users',201,'POST',{'username':other_username,'name':'QA doctor','role':'doctor','staff_id':foreign['id'],'active':True,'password':other_password},admin)
other_doc=data('/auth/login',200,'POST',{'username':other_username,'password':other_password})['access_token']
p=patient('1');v=register(p,schedule);foreign_visit=register(patient('2'),foreign_schedule);second=register(p,second_schedule)
vid=v['id'];path='/visits/'+vid;clinical=path+'/clinical';record=path+'/medical-record';procedures=path+'/procedures';finish=path+'/finish-exam'
body={'complaint':'Keluhan dummy','anamnesis':'Anamnesis dummy','physical_exam':'Pemeriksaan dummy','primary_diagnosis':'Diagnosis dummy A','secondary_diagnoses':['Diagnosis dummy B'],'vital_signs':{'systolic':120,'diastolic':80,'pulse':80,'temperature':37.2,'respiratory_rate':20,'weight':65},'expected_version':0}
for r,t in tokens.items():
    if r!='doctor':
        req(clinical,403,token=t);req(record,403,'PUT',body,t);req(procedures,403,'PUT',{},t);req(finish,403,'POST',{},t);req(path+'/clinical-history',403,token=t)
req(clinical,401);req('/visits/'+foreign_visit['id']+'/clinical',404,token=doc);req('/visits/'+foreign_visit['id']+'/medical-record',404,'PUT',body,doc)
req(clinical,404,token=other_doc);req(record,404,'PUT',body,other_doc);req(path+'/start',404,'POST',token=other_doc)
foreign_path='/visits/'+foreign_visit['id'];data(foreign_path+'/start',200,'POST',token=other_doc);data(foreign_path+'/medical-record',200,'PUT',body,other_doc)
assert data(foreign_path+'/clinical',token=other_doc)['record']['version']==1
req(foreign_path+'/clinical',404,token=doc)
req(foreign_path+'/clinical-history/'+vid,404,token=other_doc)
assert data(clinical,token=doc)['record'] is None
code(req(record,409,'PUT',body,doc),'INVALID_VISIT_STATUS')
data(path+'/start',200,'POST',token=doc);code(req(finish,409,'POST',{'expected_version':1},doc),'RECORD_REQUIRED');req(record,422,'PUT',{**body,'vital_signs':{**body['vital_signs'],'pulse':-1}},doc)
# Concurrent first record: exactly one create.
results=race(lambda:req(record,[200,409],'PUT',body,doc),lambda:req(record,[200,409],'PUT',{**body,'complaint':'Keluhan dummy kedua'},doc));assert sorted(r[0] for r in results)==[200,409];code(next(r for r in results if r[0]==409),'STALE_RECORD')
current=data(clinical,token=doc);assert current['record']['version']==1
body['complaint']=current['record']['complaint']
p1=create('procedures',{'code':'CP1-'+RUN,'name':'Tindakan dummy A','price':'100000'});p2=create('procedures',{'code':'CP2-'+RUN,'name':'Tindakan dummy B','price':'50000'});inactive=create('procedures',{'code':'CP3-'+RUN,'name':'Tidak aktif','price':'10000','active':False})
items=[{'procedure_id':p1['id'],'quantity':1},{'procedure_id':p2['id'],'quantity':2}]
change={'items':items,'expected_version':1}
assert current['reason_required']['procedures'] is False
code(req(procedures,409,'PUT',{**change,'items':items+[{'procedure_id':inactive['id'],'quantity':1}]},doc),'PROCEDURE_UNAVAILABLE');assert data(clinical,token=doc)['procedures']==[]
req(procedures,422,'PUT',{**change,'items':[dict(items[0],unit_price='1')]},doc)
# Record and procedure changes share one version; exactly one wins, then apply the losing intent on fresh version.
results=race(lambda:req(record,[200,409],'PUT',{**body,'expected_version':1,'reason':'Koreksi dummy','primary_diagnosis':'Diagnosis dummy C'},doc),lambda:req(procedures,[200,409],'PUT',change,doc));assert sorted(r[0] for r in results)==[200,409];code(next(r for r in results if r[0]==409),'STALE_RECORD')
cur=data(clinical,token=doc);assert cur['record']['version']==2
if not cur['procedures']:cur=data(procedures,200,'PUT',{**change,'expected_version':2},doc)
else:cur=data(record,200,'PUT',{**body,'expected_version':2,'reason':'Koreksi dummy','primary_diagnosis':'Diagnosis dummy C'},doc)
assert cur['record']['version']==3 and cur['procedure_total']=='200000' and len(cur['procedures'])==2
assert cur['reason_required']['procedures'] is True
req(procedures,422,'PUT',{**change,'expected_version':3},doc)
assert data(clinical,token=doc)['record']['version']==3
# Existing snapshot survives name/price/deactivation changes.
data('/master/procedures/'+p1['id'],200,'PATCH',{'price':'999999','name':'Nama baru dummy','active':False},admin)
cur=data(procedures,200,'PUT',{'items':[{'procedure_id':p1['id'],'quantity':2},items[1]],'expected_version':3,'reason':'Ubah jumlah dummy'},doc)
assert cur['record']['version']==4 and cur['procedure_total']=='300000';assert next(x for x in cur['procedures'] if x['procedure_id']==p1['id'])['unit_price']=='100000'
code(req(finish,409,'POST',{'expected_version':3},doc),'STALE_RECORD')
cur=data(finish,200,'POST',{'expected_version':4},doc);assert cur['visit']['status']=='ready_for_billing' and cur['record']['version']==5
assert cur['can_revise_procedures'] is True
bill='/billing/visits/'+vid
invoice=data(bill,token=tokens['cashier']);assert invoice['payment_status']=='unpaid'
req(bill+'/payments',200,'POST',{'amount':'1','method':'cash','expected_revision':invoice['revision']},tokens['cashier'],key=str(uuid.uuid4()))
code(req(procedures,409,'PUT',{**change,'expected_version':5},doc),'BILLING_LOCKED')
code(req(record,409,'PUT',{**body,'expected_version':5,'reason':'Ubah keluhan','complaint':'Keluhan lain'},doc),'INVALID_VISIT_STATUS')
# Diagnosis-only amendment after finish; procedure totals remain the same.
cur=data(record,200,'PUT',{**body,'expected_version':5,'reason':'Koreksi diagnosis setelah selesai','primary_diagnosis':'Diagnosis dummy akhir'},doc);assert cur['record']['version']==6 and cur['procedure_total']=='300000' and cur['visit']['status']=='ready_for_billing'
for t in (admin,reg):assert 'record' not in data(path,token=t) and 'complaint' not in data(path,token=t)
# Open second assigned visit and read the previous clinical record; different patient's ID stays hidden.
second_path='/visits/'+second['id'];data(second_path+'/start',200,'POST',token=doc)
assert any(x['id']==vid for x in data(second_path+'/clinical-history',token=doc))
history=data(second_path+'/clinical-history/'+vid,token=doc);assert history['record']['version']==6 and history['record']['primary_diagnosis']=='Diagnosis dummy akhir'
req(second_path+'/clinical-history/'+foreign_visit['id'],404,token=doc)
if '--db-check' in sys.argv:
    assert vid.isdigit()
    sql=f"SELECT action, count(*) FROM audit_logs WHERE entity_type='visits' AND entity_id={vid} GROUP BY action ORDER BY action"
    output=subprocess.check_output(['docker','compose','exec','-T','db','psql','-U','simrs_owner','-d','simrs','-At','-F','|','-c',sql],cwd=ROOT,text=True)
    counts=dict(line.split('|',1) for line in output.strip().splitlines());expected={'create_medical_record':1,'update_medical_record':2,'replace_visit_procedures':2,'finish_exam':1}
    for action,n in expected.items():assert int(counts.get(action,0))==n,(action,counts)
    assert int(counts.get('read_clinical_history',0))>=1
    print('PASS: audit action counts; rejected/stale clinical writes did not add audit events.')
print(f'PASS: {count} HTTP checks plus assertions. Dummy fixture visit {vid}; records retained. Pending prescription/lab and cross-doctor history fixtures require Phase 6/user-management testing.')
