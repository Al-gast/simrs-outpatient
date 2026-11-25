#!/usr/bin/env python3
"""Run against a disposable dummy DB only. Creates permanent visit/audit fixtures.
Requires the Phase 2 dummy users and unmodified seeded doctor's midnight availability.
Parallel HTTP requests use separate connections; Compose runs four PHP CLI workers.
"""
import concurrent.futures, json, os, pathlib, threading, urllib.parse, urllib.request, urllib.error, uuid, datetime
from zoneinfo import ZoneInfo
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''))
BASE='http://localhost:8080/api/v1'; run=uuid.uuid4().hex[:12].upper(); checks=0; lock=threading.Lock()
assert PREFIX,'Missing demo prefix'
def req(path,expected,method='GET',body=None,token=None):
    global checks
    headers={'Accept':'application/json','X-Request-ID':'visit-'+uuid.uuid4().hex}
    if token: headers['Authorization']='Bearer '+token
    if body is not None: headers['Content-Type']='application/json'
    request=urllib.request.Request(BASE+path,method=method,headers=headers,data=json.dumps(body).encode() if body is not None else None)
    try: response=urllib.request.urlopen(request,timeout=25)
    except urllib.error.HTTPError as e: response=e
    with response:
        data=json.loads(response.read())
        assert response.status in ([expected] if isinstance(expected,int) else expected), (path,response.status,data)
        assert data['meta']['request_id']==headers['X-Request-ID']
        with lock: checks+=1
        return response.status,data

def data(path,expected=200,method='GET',body=None,token=None):return req(path,expected,method,body,token)[1]['data']
def list_path(path,**filters):return path+'?'+urllib.parse.urlencode(filters)
def login(role):return data('/auth/login',200,'POST',{'username':role,'password':PREFIX+'-'+role})['access_token']
roles=['admin','registration','doctor','pharmacy','laboratory','cashier']; tokens={r:login(r) for r in roles}; admin=tokens['admin']; reg=tokens['registration']; doc=tokens['doctor']
def master(resource,body,token=admin):return data('/master/'+resource,201,'POST',body,token)
def patient(suffix,active=True):return master('patients',{'name':f'Visit test {run} {suffix}','identity_no':f'V-{run}-{suffix}','birth_date':'2000-01-01','sex':'U','active':active},reg)
def register(p,s,expected=201):return req('/visits',expected,'POST',{'patient_id':p['id'],'schedule_id':s['id']},reg)
def error(result,code):assert result[1]['error']['code']==code,result
req('/visits',401)
for role,token in tokens.items():
    req('/visits',200 if role in ('admin','registration','doctor') else 403,token=token)
    if role!='registration':req('/visits',403,'POST',{},token)
    if role!='doctor':req('/visits/1/start',403,'POST',token=token)
    if role not in ('registration','doctor'):req('/patients/1/visits',403,token=token)
for filters in ({'page':0},{'per_page':101},{'date':'2026-02-30'},{'status':'paid'},{'q':'x'*201},{'unknown':'1'}):req(list_path('/visits',**filters),422,token=reg)
for body in ({},{'patient_id':1,'schedule_id':'1'},{'patient_id':'1','schedule_id':'1','queue_number':9}):req('/visits',422,'POST',body,reg)
# Server date, not machine clock. Avoid running across WIB midnight.
today=req('/visits',200,token=reg)[1]['meta']['today']; weekday=datetime.date.fromisoformat(today).isoweekday()
assert today==datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date().isoformat()
staff=data('/auth/me',token=doc)['staff_id']; clinic=master('clinics',{'code':'V-'+run,'name':'Visit test clinic '+run,'registration_fee':'25000'})
# A unique clinic provides isolated counter. Find a free half-hour for seeded doctor.
existing=data(list_path('/master/doctor-schedules',doctor_id=staff,weekday=weekday,active='true',per_page=100),token=admin)
def minutes(t):return int(t[:2])*60+int(t[3:5])
free=next((i for i in range(0,1411,30) if all(i+30<=minutes(x['start_time']) or i>=minutes(x['end_time']) for x in existing)),None)
assert free is not None,'No free half-hour for seeded doctor; use clean dummy database'
def hhmm(n):return f'{n//60:02d}:{n%60:02d}'
schedule=master('doctor-schedules',{'doctor_id':staff,'clinic_id':clinic['id'],'weekday':weekday,'start_time':hhmm(free),'end_time':hhmm(free+30)})
other=master('medical-staff',{'code':'VD-'+run,'name':'Other doctor '+run,'profession':'doctor'})
other_schedule=master('doctor-schedules',{'doctor_id':other['id'],'clinic_id':clinic['id'],'weekday':weekday,'start_time':'08:00','end_time':'12:00'})
wrong_day=master('doctor-schedules',{'doctor_id':other['id'],'clinic_id':clinic['id'],'weekday':weekday%7+1,'start_time':'08:00','end_time':'12:00'})
inactive_schedule=master('doctor-schedules',{'doctor_id':other['id'],'clinic_id':clinic['id'],'weekday':weekday,'start_time':'14:00','end_time':'15:00','active':False})
p1,p2,p3,p4,p5=[patient(s) for s in ('1','2','3','4','5')]; inactive=patient('inactive',False)
error(register(inactive,schedule,409),'PATIENT_INACTIVE');error(register(p1,wrong_day,409),'SCHEDULE_UNAVAILABLE');error(register(p1,inactive_schedule,409),'SCHEDULE_UNAVAILABLE')
# Same patient + clinic/date, even different schedule/doctor: one success, one conflict.
def race(functions):
    barrier=threading.Barrier(len(functions))
    def run_one(fn):barrier.wait(timeout=10);return fn()
    with concurrent.futures.ThreadPoolExecutor(max_workers=len(functions)) as executor:return list(executor.map(run_one,functions))
results=race([lambda:register(p1,schedule,[201,409]),lambda:register(p1,other_schedule,[201,409])]);assert sorted(x[0] for x in results)==[201,409];error(next(x for x in results if x[0]==409),'DUPLICATE_VISIT');first=next(x[1]['data'] for x in results if x[0]==201);assert first['queue_number']==1
results=race([lambda p=p:register(p,schedule,201) for p in (p2,p3,p4)]);rows=[r[1]['data'] for r in results];assert sorted(v['queue_number'] for v in rows)==[2,3,4],rows
for v in rows+[first]:assert v['visit_date']==today and v['registration_fee']=='25000' and v['status']=='waiting'
# Fee snapshot survives master changes; failed duplicate did not consume a counter.
data('/master/clinics/'+clinic['id'],200,'PATCH',{'registration_fee':'30000'},admin)
foreign=register(p5,other_schedule)[1]['data'];assert foreign['queue_number']==5 and foreign['registration_fee']=='30000'
assert data('/visits/'+first['id'],token=reg)['registration_fee']=='25000'
all_rows=data(list_path('/visits',clinic_id=clinic['id'],date=today,per_page=100),token=admin);assert len(all_rows)==5 and len({v['queue_number'] for v in all_rows})==5
assert all('identity_no' not in v and 'anamnesis' not in v for v in all_rows)
assert len(data(list_path('/visits',q=p2['identity_no'],clinic_id=clinic['id']),token=reg))==1
assert data(list_path('/visits',q=p2['identity_no'],clinic_id=clinic['id']),token=doc)==[]
assert data('/patients/'+p2['id']+'/visits',token=reg)[0]['id']==rows[0]['id']
assert data('/patients/'+p2['id']+'/visits',token=doc)[0]['doctor_id']==staff
req('/visits/'+foreign['id'],404,token=doc);req('/patients/'+p5['id']+'/visits',404,token=doc);req('/visits/'+foreign['id']+'/start',404,'POST',token=doc)
own=data(list_path('/visits',clinic_id=clinic['id'],status='waiting'),token=doc);assert own and all(v['doctor_id']==staff for v in own)
# Parallel start replays safely; only assigned doctor may transition.
id=rows[0]['id'];results=race([lambda:req('/visits/'+id+'/start',200,'POST',token=doc) for _ in range(2)]);assert all(r[1]['data']['status']=='in_exam' for r in results)
assert data('/visits/'+id,token=reg)['status']=='in_exam';assert data(list_path('/visits',clinic_id=clinic['id'],status='in_exam'),token=reg)[0]['id']==id
print(f'PASS: {checks} HTTP checks plus data assertions. Fixture clinic {clinic["code"]}; 5 visits kept for audit. Parallel clients used the same seeded registration account; separate HTTP connections.')
