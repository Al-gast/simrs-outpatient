#!/usr/bin/env python3
"""Run against a running Compose project. Uses dummy accounts only; no clinical writes."""
import json, urllib.request, urllib.error, pathlib, os, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
ROLES=('admin','registration','doctor','pharmacy','laboratory','cashier')
CORE='http://localhost:8080/api/v1'; NODE='http://localhost:3001/api/v1'
ENV={}
for line in (ROOT/'.env').read_text().splitlines():
    if '=' in line and not line.startswith('#'):
        k,v=line.split('=',1); ENV[k]=v
prefix=os.environ.get('DEMO_PASSWORD_PREFIX',ENV.get('DEMO_PASSWORD_PREFIX',''))
if not prefix: raise SystemExit('Missing DEMO_PASSWORD_PREFIX')
checks=0
def request(base,path,status,method='GET',body=None,token=None,extra=None):
    global checks
    rid='smoke-'+uuid.uuid4().hex
    headers={'Accept':'application/json','X-Request-ID':rid}
    if token: headers['Authorization']='Bearer '+token
    if body is not None: headers['Content-Type']='application/json'
    if extra: headers.update(extra)
    data=json.dumps(body).encode() if body is not None else None
    req=urllib.request.Request(base+path,data=data,headers=headers,method=method)
    try: response=urllib.request.urlopen(req,timeout=10)
    except urllib.error.HTTPError as exc: response=exc
    with response:
        actual=response.status; raw=response.read(); returned_id=response.headers.get('X-Request-ID')
        if actual != status: raise AssertionError(f'{base}{path}: expected {status}, got {actual}')
        if returned_id != rid: raise AssertionError(f'{path}: request ID mismatch')
        payload=json.loads(raw) if raw else None
        if status not in (204,):
            assert payload['meta']['request_id']==rid, path
            assert ('error' if status>=400 else 'data') in payload, path
        checks+=1
        return payload
for base in (CORE,NODE):
    request(base,'/health',200)
    request(base,'/system/access/admin',401)
request(CORE,'/auth/login',401,'POST',{'username':'not-a-demo-account','password':'bad-password'})
request(CORE,'/auth/login',422,'POST',{'username':'admin','password':123})
request(CORE,'/auth/login',422,'POST',{'username':'admin','password':'bad','role':'admin'})
for role in ROLES:
    login=request(CORE,'/auth/login',200,'POST',{'username':role,'password':prefix+'-'+role})['data']
    assert login['user']['role']==role and 'password_hash' not in login['user']
    token=login['access_token']
    me=request(CORE,'/auth/me',200,token=token)['data']; assert me['role']==role
    for base in (CORE,NODE):
        for target in ROLES:
            request(base,'/system/access/'+target,200 if target==role else 403,token=token,extra={'X-Role':'admin'})
    request(CORE,'/auth/logout',204,'POST',token=token)
    request(CORE,'/auth/me',401,token=token)
    request(NODE,'/system/access/'+role,401,token=token)
    print(f'PASS: {role} login, role isolation in both services, logout/revocation')
print(f'PASS: {checks} HTTP checks')
