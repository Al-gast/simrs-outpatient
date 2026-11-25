#!/usr/bin/env python3
"""Integration checks against disposable dummy DB. Creates test data and audit records."""
import json, re, pathlib, urllib.request, urllib.error, uuid, os, concurrent.futures
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''))
BASE='http://localhost:8080/api/v1'; run=uuid.uuid4().hex[:12].upper(); checks=0

def req(path,expected,method='GET',body=None,token=None,key=None):
    global checks
    headers={'Accept':'application/json','X-Request-ID':'master-'+uuid.uuid4().hex}
    if token: headers['Authorization']='Bearer '+token
    if key: headers['Idempotency-Key']=key
    if body is not None: headers['Content-Type']='application/json'
    request=urllib.request.Request(BASE+path,method=method,headers=headers,data=json.dumps(body).encode() if body is not None else None)
    try: response=urllib.request.urlopen(request,timeout=15)
    except urllib.error.HTTPError as e: response=e
    with response:
        raw=response.read(); payload=json.loads(raw) if raw else None
        assert response.status in ([expected] if isinstance(expected,int) else expected), (path,response.status,payload)
        if response.status!=204: assert payload['meta']['request_id']==headers['X-Request-ID']
        checks+=1
        return response.status,payload

def login(role): return req('/auth/login',200,'POST',{'username':role,'password':PREFIX+'-'+role})[1]['data']['access_token']
def api(r,id=None): return '/master/'+r+('/'+str(id) if id else '')
roles=['admin','registration','doctor','pharmacy','laboratory','cashier']
resources={'patients':['admin','registration','doctor'],'medical-staff':['admin','registration','doctor'],'clinics':['admin','registration','doctor'],'doctor-schedules':['admin','registration','doctor'],'procedures':['admin','doctor'],'medicines':['admin','doctor','pharmacy'],'lab-tests':['admin','doctor','laboratory']}
assert PREFIX,'Missing prefix'
tokens={r:login(r) for r in roles}; admin=tokens['admin']; reg=tokens['registration']
for r,allowed in resources.items():
    req(api(r),401)
    for role,token in tokens.items():
        req(api(r)+'?page=1&per_page=1',200 if role in allowed else 403,token=token)
        if role!='admin' and not (role=='registration' and r=='patients'): req(api(r),403,'POST',{},token)

def create(r,data,t=admin): return req(api(r),201,'POST',data,t)[1]['data']
def delete(r,row,t=admin): return req(api(r,row['id']),204,'DELETE',token=t)
def view(r,row,t=admin): return req(api(r,row['id']),200,token=t)[1]['data']
patient=create('patients',{'name':'Test '+run,'identity_no':'TEST-'+run,'birth_date':'2000-01-01','sex':'U'},reg)
assert patient['medical_record_no'].startswith('RM-')
req(api('patients'),409,'POST',{'name':'Duplicate','identity_no':'TEST-'+run,'birth_date':'2000-01-01','sex':'U'},reg)
req(api('patients',patient['id']),404,token=tokens['doctor'])
req(api('patients',patient['id']),200,'PATCH',{'phone':'0800000000'},reg)
assert view('patients',patient,reg)['phone']=='0800000000'
assert req(api('patients')+'?q=TEST-'+run,200,token=reg)[1]['meta']['total']==1
delete('patients',patient,reg)
clinic=create('clinics',{'code':'CL-'+run.lower(),'name':'Test clinic','registration_fee':'20000'})
assert clinic['code']=='CL-'+run
staff=create('medical-staff',{'code':'DR-'+run,'name':'Test doctor','profession':'doctor'})
schedule={'doctor_id':staff['id'],'clinic_id':clinic['id'],'weekday':1,'start_time':'09:00','end_time':'12:00'}
s=create('doctor-schedules',schedule)
req(api('doctor-schedules'),409,'POST',{**schedule,'start_time':'10:00','end_time':'11:00'},admin)
req(api('doctor-schedules'),422,'POST',{**schedule,'start_time':'14:00','end_time':'13:00'},admin)
req(api('clinics',clinic['id']),409,'DELETE',token=admin)
delete('doctor-schedules',s);delete('medical-staff',staff);delete('clinics',clinic)
for r in ('procedures','lab-tests'):
    row=create(r,{'code':'T-'+run,'name':'Test','price':'25000'});req(api(r,row['id']),200,'PATCH',{'price':'30000'},admin);delete(r,row)
med=create('medicines',{'code':'M-'+run,'name':'Test medicine','price':'500','unit':'tablet'})
req(api('medicines',med['id']),422,'PATCH',{'stock':999},admin)
path=api('medicines',med['id'])+'/adjust-stock'
key=str(uuid.uuid4());opening={'delta':5,'reason':'Dummy opening'}
assert req(path,200,'POST',opening,admin,key)[1]['data']['stock']==5
assert req(path,200,'POST',opening,admin,key)[1]['data']['replayed'] is True
assert view('medicines',med)['stock']==5
req(path,409,'POST',{'delta':6,'reason':'Dummy opening'},admin,key)
req(path,422,'POST',opening,admin)
req(api('medicines',med['id']),409,'DELETE',token=admin)
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    futures=[pool.submit(req,path,[200,409],'POST',{'delta':-5,'reason':'Dummy concurrent adjustment'},admin,str(uuid.uuid4())) for _ in range(2)]
    assert sorted(f.result()[0] for f in futures)==[200,409]
assert view('medicines',med)['stock']==0
# Strict delete never silently deactivates; old clients retain their existing fallback.
req(api('medicines',med['id'])+'?mode=permanent',409,'DELETE',token=admin)
assert view('medicines',med)['active'] is True
assert req(api('medicines',med['id']),200,'PATCH',{'active':False},admin)[1]['data']['active'] is False
assert req(api('medicines',med['id']),200,'PATCH',{'active':True},admin)[1]['data']['active'] is True
req(api('medicines',med['id']),403,'PATCH',{'active':False},tokens['pharmacy'])
assert req(api('medicines',med['id']),200,'DELETE',token=admin)[1]['data']['active'] is False
# Omitted code generates a new identifier under the master advisory lock.
for r,fields,pattern in [('medical-staff',{'profession':'nurse'},r'N[0-9]{6,}'),('clinics',{'registration_fee':'0'},r'POL-[0-9]{6,}'),('procedures',{'price':'0'},r'T[0-9]{6,}'),('medicines',{'price':'0','unit':'tablet'},r'OB[0-9]{6,}'),('lab-tests',{'price':'0'},r'L[0-9]{6,}')]:
    row=create(r,{'name':'Auto dummy '+run,**fields});assert re.fullmatch(pattern,row['code'])
    assert req(api(r,row['id']),200,'PATCH',{'name':'Auto dummy updated '+run},admin)[1]['data']['code']==row['code']
    req(api(r,row['id'])+'?mode=permanent',204,'DELETE',token=admin)
    req(api(r,row['id']),404,token=admin)
# On a fresh dummy DB, the manual code collides with the second generated procedure code.
legacy=create('procedures',{'code':'T000002','name':'Manual collision dummy','price':'0'}) if not req(api('procedures')+'?q=T000002',200,token=admin)[1]['data'] else None
with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
    rows=list(pool.map(lambda _:create('procedures',{'name':'Concurrent dummy '+run,'price':'0'}),range(2)))
assert len({row['code'] for row in rows})==2 and all(row['code']!='T000002' for row in rows)
for row in rows+([legacy] if legacy else []):req(api('procedures',row['id'])+'?mode=permanent',204,'DELETE',token=admin)
for token in tokens.values(): req('/auth/logout',204,'POST',token=token)
print(f'PASS: {checks} master API checks; audit and inactive dummy medicine remain intentionally.')
