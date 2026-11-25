#!/usr/bin/env python3
"""Run after billing-smoke on a disposable dummy DB. Creates/deactivates accounts; never production."""
import datetime, json, os, pathlib, subprocess, urllib.error, urllib.parse, urllib.request, uuid
from zoneinfo import ZoneInfo
ROOT=pathlib.Path(__file__).resolve().parents[1]
ENV=dict(line.split('=',1) for line in (ROOT/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
PREFIX=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''));assert PREFIX
CORE='http://localhost:8080/api/v1';NODE='http://localhost:3001/api/v1'; count=0

def req(path,status=200,method='GET',body=None,token=None,base=CORE,headers_extra=None):
    global count
    headers={'Accept':'application/json','X-Request-ID':'report-'+uuid.uuid4().hex}
    if token:headers['Authorization']='Bearer '+token
    if body is not None:headers['Content-Type']='application/json'
    headers.update(headers_extra or {})
    request=urllib.request.Request(base+path,method=method,headers=headers,data=json.dumps(body).encode() if body is not None else None)
    try:r=urllib.request.urlopen(request,timeout=40)
    except urllib.error.HTTPError as e:r=e
    with r:
        p=json.loads(r.read());assert r.status==status,(path,r.status,p.get('error'));assert p['meta']['request_id']==headers['X-Request-ID'];count+=1;return p

def login(username,password):return req('/auth/login',method='POST',body={'username':username,'password':password})['data']['access_token']
def browser_login(username,password):return req('/auth/browser/login',method='POST',body={'username':username,'password':password},headers_extra={'Origin':'http://localhost:3000'})['data']['access_token']
def browser_headers(token):return {'Origin':'http://localhost:3000','Cookie':'simrs_browser_session='+token}
def sql(q):return subprocess.check_output(['docker','compose','exec','-T','db','psql','-U','simrs_owner','-d','simrs','-At','-v','ON_ERROR_STOP=1','-c',q],cwd=ROOT,text=True).strip()
roles=['admin','registration','doctor','pharmacy','laboratory','cashier'];tokens={r:login(r,PREFIX+'-'+r) for r in roles};admin=tokens['admin'];today=datetime.datetime.now(ZoneInfo('Asia/Jakarta')).date().isoformat();period=f'from={today}&to={today}'
for role,t in tokens.items():
    for path,base in [('/admin/users',CORE),('/admin/audit?'+period,CORE),('/reports/summary?'+period,NODE),('/reports/details?'+period+'&metric=visits',NODE)]:req(path,200 if role=='admin' else 403,token=t,base=base)
req('/reports/summary?'+period,401,base=NODE);req('/admin/users',401);req('/reports/summary?from=2026-02-30&to=2026-03-01',422,token=admin,base=NODE);req('/reports/details?'+period+'&metric=unknown',422,token=admin,base=NODE)
summary=req('/reports/summary?'+period,token=admin,base=NODE)['data']; assert summary['from']==today and summary['to']==today
# Independent direct queries on base tables, with no concurrent users writing during these comparisons.
date=f"DATE '{today}'"; start=f"({date}::timestamp AT TIME ZONE 'Asia/Jakarta')"; end=f"(({date}+1)::timestamp AT TIME ZONE 'Asia/Jakarta')"
checks={
 'visits':f'SELECT count(*) FROM visits WHERE visit_date={date}',
 'procedures':f'SELECT coalesce(sum(p.quantity),0) FROM visit_procedures p JOIN visits v ON v.id=p.visit_id WHERE v.visit_date={date}',
 'revenue':f'SELECT coalesce(sum(amount),0) FROM payments WHERE paid_at>={start} AND paid_at<{end}',
 'medicines':f"SELECT count(DISTINCT medicine_id) FROM stock_movements WHERE kind='dispense' AND created_at>={start} AND created_at<{end}",
 'payments':f'SELECT count(*) FROM invoices i JOIN visits v ON v.id=i.visit_id WHERE v.visit_date={date}',
}
actual={'visits':summary['visits']['visits'],'procedures':summary['procedures']['quantity'],'revenue':summary['revenue']['amount'],'medicines':summary['medicines']['types'],'payments':sum(int(r['count']) for r in summary['payments'])}
for k,q in checks.items():assert int(actual[k])==int(sql(q)),k
# Invoice cohort includes all payments for selected visits, even outside the date window.
for field,query in {
 'total_amount':f'SELECT coalesce(sum(i.total_amount),0) FROM invoices i JOIN visits v ON v.id=i.visit_id WHERE v.visit_date={date}',
 'paid_amount':f'SELECT coalesce(sum(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN visits v ON v.id=i.visit_id WHERE v.visit_date={date}',
}.items():assert sum(int(r[field]) for r in summary['payments'])==int(sql(query)),field
assert all(int(r['total_amount'])==int(r['paid_amount'])+int(r['outstanding']) for r in summary['payments'])
# Staff name projection is admin-only and not dependent on active status or first staff page.
for row in req('/admin/users?role=doctor&per_page=100',token=admin)['data']:
    assert row['staff_name']==sql('SELECT name FROM medical_staff WHERE id='+str(int(row['staff_id'])))
assert all(r['staff_name'] is None for r in req('/admin/users?role=cashier',token=admin)['data'])

for metric in ['visits','clinics','procedures','revenue','medicines','payments']:
    d=req('/reports/details?'+period+'&metric='+metric+'&per_page=1',token=admin,base=NODE)['data'];assert len(d['rows'])<=1 and d['metric']==metric and int(d['total'])>=len(d['rows']);assert all('patient_name' not in r and 'result_text' not in r for r in d['rows'])
if summary['clinics']:
    c=summary['clinics'][0];d=req('/reports/details?'+period+'&metric=clinics&clinic_id='+c['clinic_id'],token=admin,base=NODE)['data'];assert d['total']==c['count'] and all(r['clinic_id']==c['clinic_id'] for r in d['rows'])
username='qa-'+uuid.uuid4().hex[:12];password='Dummy-'+uuid.uuid4().hex;new_password='Reset-'+uuid.uuid4().hex
body={'username':username,'name':'Dummy QA Account','role':'registration','staff_id':None,'active':True,'password':password}
a=req('/admin/users',201,'POST',body,admin)['data'];assert 'password_hash' not in a and 'password' not in a;req('/admin/users',409,'POST',body,admin);req('/admin/users',403,'POST',body,tokens['registration']);old=browser_login(username,password);req('/auth/browser/restore',200,'POST',{},headers_extra=browser_headers(old))
path='/admin/users/'+a['id'];edit={'name':a['name'],'role':'cashier','staff_id':None,'active':True,'expected_version':a['version'],'password':new_password}
b=req(path,200,'PATCH',edit,admin)['data'];assert b['version']==a['version']+1 and b['role']=='cashier';req('/auth/me',401,token=old);req('/auth/browser/restore',401,'POST',{},headers_extra=browser_headers(old));req('/system/access/registration',401,token=old,base=NODE);req(path,409,'PATCH',edit,admin)
new=browser_login(username,new_password);req('/system/access/cashier',token=new);req('/system/access/registration',403,token=new)
edit.pop('password');edit.update(active=False,expected_version=b['version']);req(path,200,'PATCH',edit,admin);req('/auth/me',401,token=new);req('/auth/browser/restore',401,'POST',{},headers_extra=browser_headers(new));req('/auth/login',401,'POST',{'username':username,'password':new_password})
me=req('/admin/users?q=admin&role=admin',token=admin)['data'];self_row=next(r for r in me if r['username']=='admin');req('/admin/users/'+self_row['id'],409,'PATCH',{'name':self_row['name'],'role':'admin','staff_id':None,'active':False,'expected_version':self_row['version']},admin)
logs=req('/admin/audit?'+period+'&entity_type=users&per_page=100',token=admin)['data'];mine=[r for r in logs if r['entity_id']==a['id']];assert len(mine)==3 and {r['action'] for r in mine}=={'create_user','reset_user_password','update_user'};assert all(not {'before_data','after_data','reason','password_hash','password'}&set(r) for r in logs)
# Independently exercise the UI's separated password and status PATCH payloads.
current=req('/admin/users?q='+username,token=admin)['data'][0]
patch={k:current[k] for k in ['name','role','staff_id','active']};patch.update(expected_version=current['version'],active=True)
reactivated=req(path,200,'PATCH',patch,admin)['data'];assert reactivated['active'] and reactivated['version']==current['version']+1
reactivated_token=login(username,new_password)
patch.update(expected_version=reactivated['version'],password=password)
reset=req(path,200,'PATCH',patch,admin)['data'];assert reset['role']==reactivated['role'] and reset['staff_id']==reactivated['staff_id'] and reset['active']
req('/auth/me',401,token=reactivated_token);req('/auth/login',401,'POST',{'username':username,'password':new_password});login(username,password)
patch.pop('password');patch.update(expected_version=reset['version'],active=False);req(path,200,'PATCH',patch,admin)
actor_id=self_row['id'];filtered=req('/admin/audit?'+period+'&entity_type=users&action=reset_user_password&actor_id='+actor_id,token=admin)['data'];assert filtered and all(r['actor_id']==actor_id and r['action']=='reset_user_password' and r['entity_type']=='users' for r in filtered)
print(f'PASS: {count} HTTP checks plus direct SQL comparisons (dummy data)')
