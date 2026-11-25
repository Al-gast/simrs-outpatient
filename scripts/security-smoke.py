#!/usr/bin/env python3
"""API boundary negative cases, dummy DB only. No brute force or production access."""
import json, pathlib, urllib.error, urllib.request, uuid
from http.cookies import SimpleCookie
root=pathlib.Path(__file__).resolve().parents[1]
settings=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if '=' in line and not line.startswith('#'))
core='http://localhost:8080/api/v1';node='http://localhost:3001/api/v1'
checks=0
def req(base,path,status,method='GET',body=None,headers=None,raw=None,include_headers=False):
    global checks
    h={'Accept':'application/json','X-Request-ID':'security-'+uuid.uuid4().hex}
    if body is not None:raw=json.dumps(body).encode();h['Content-Type']='application/json'
    h.update(headers or {})
    try:r=urllib.request.urlopen(urllib.request.Request(base+path,method=method,data=raw,headers=h),timeout=20)
    except urllib.error.HTTPError as e:r=e
    with r:
        assert r.status==status,(path,r.status,status)
        assert r.headers.get('Cache-Control')=='no-store'
        assert r.headers.get('X-Request-ID')==h['X-Request-ID']
        content=r.read();p=json.loads(content) if content else None
        if status>=400:assert 'error' in p and 'trace' not in p and 'stack' not in p
        checks+=1;return (p,r.headers) if include_headers else p
for p in ('a'*73,'abc\0def'):
    req(core,'/auth/login',422,'POST',{'username':'admin','password':p})
req(core,'/auth/login',400,'POST',headers={'Content-Type':'application/json'},raw=b'{')
req(core,'/auth/login',415,'POST',headers={'Content-Type':'text/plain'},raw=b'{}')
req(core,'/auth/login',413,'POST',headers={'Content-Type':'application/json'},raw=b' '*16385)
req(core,'/auth/login',422,'POST',headers={'Content-Type':'application/json'},raw=b'[]')
for base in (core,node):
    req(base,'/system/access/admin',401,headers={'Authorization':'Bearer a.b.c'})
    req(base,'/system/access/admin',403,'OPTIONS',headers={'Origin':'https://invalid.example','Access-Control-Request-Method':'GET'})
    req(base,'/system/access/admin',204,'OPTIONS',headers={'Origin':'http://localhost:3000','Access-Control-Request-Method':'GET'})
admin=req(core,'/auth/login',200,'POST',{'username':'admin','password':settings['DEMO_PASSWORD_PREFIX']+'-admin'})['data']['access_token']
auth={'Authorization':'Bearer '+admin}
req(core,'/master/patients?q=%27%20OR%201%3D1--',200,headers=auth)
req(core,'/master/patients?page%5B%5D=1',422,headers=auth)
req(core,'/master/patients/9223372036854775808',404,headers=auth)
req(core,'/admin/users',422,'POST',{'username':'bad','name':'dummy','role':'registration','staff_id':None,'active':True,'password':'a'*73},auth)
req(node,'/reports/details?from=2026-01-01&to=2026-01-01&metric=visits&clinic_id=1%20OR%201=1',422,headers=auth)
# Browser session transport is opt-in; explicit exact Origin, scoped HttpOnly cookie.
origin={'Origin':'http://localhost:3000'}
_,h=req(core,'/auth/browser/login',204,'OPTIONS',headers={**origin,'Access-Control-Request-Method':'POST','Access-Control-Request-Headers':'content-type'},include_headers=True)
assert h.get('Access-Control-Allow-Credentials')=='true' and h.get('Access-Control-Allow-Origin')==origin['Origin']
req(core,'/auth/browser/restore',403,'POST',{})
req(core,'/auth/browser/restore',403,'POST',{},headers={'Origin':'https://invalid.example'})
req(core,'/auth/browser/restore',401,'POST',{},headers=origin)
result,h=req(core,'/auth/browser/login',200,'POST',{'username':'admin','password':settings['DEMO_PASSWORD_PREFIX']+'-admin'},headers=origin,include_headers=True)
jar=SimpleCookie();jar.load(h.get('Set-Cookie',''));cookie=jar['simrs_browser_session'];token=result['data']['access_token']
assert cookie.value==token and cookie['httponly'] and cookie['samesite'].lower()=='strict' and cookie['path']=='/api/v1/auth/browser/' and not cookie['domain'] and int(cookie['max-age'])==7200
assert h.get('Access-Control-Allow-Credentials')=='true'
credentials={**origin,'Cookie':'simrs_browser_session='+cookie.value}
restored,h=req(core,'/auth/browser/restore',200,'POST',{},headers=credentials,include_headers=True)
assert restored['data']['user']['role']=='admin' and restored['data']['access_token']==token and 0<restored['data']['expires_in']<=result['data']['expires_in'] and h.get('Set-Cookie') is None
# A cookie alone never authorizes a business API, even if manually attached.
req(core,'/auth/me',401,headers=credentials)
req(core,'/master/patients',401,headers=credentials)
req(node,'/system/access/admin',200,headers={'Authorization':'Bearer '+token})
req(core,'/auth/browser/logout',403,'POST',{},headers={**credentials,'Origin':'https://invalid.example'})
req(core,'/auth/browser/restore',200,'POST',{},headers=credentials)
_,h=req(core,'/auth/browser/logout',204,'POST',{},headers=credentials,include_headers=True)
assert 'Max-Age=0' in h.get('Set-Cookie','')
req(core,'/auth/browser/restore',401,'POST',{},headers=credentials)
req(core,'/auth/me',401,headers={'Authorization':'Bearer '+token})
req(node,'/system/access/admin',401,headers={'Authorization':'Bearer '+token})
# Stale bearer from another tab must not revoke or expire a newer cookie.
a=req(core,'/auth/browser/login',200,'POST',{'username':'admin','password':settings['DEMO_PASSWORD_PREFIX']+'-admin'},headers=origin)['data']['access_token']
b=req(core,'/auth/browser/login',200,'POST',{'username':'cashier','password':settings['DEMO_PASSWORD_PREFIX']+'-cashier'},headers=origin)['data']['access_token']
new_cookie={**origin,'Cookie':'simrs_browser_session='+b}
_,h=req(core,'/auth/browser/logout',204,'POST',{},headers={**new_cookie,'Authorization':'Bearer '+a},include_headers=True)
assert h.get('Set-Cookie') is None
req(core,'/auth/me',401,headers={'Authorization':'Bearer '+a})
assert req(core,'/auth/browser/restore',200,'POST',{},headers=new_cookie)['data']['access_token']==b
_,h=req(core,'/auth/browser/restore',401,'POST',{},headers={**origin,'Cookie':'simrs_browser_session='+a},include_headers=True)
assert h.get('Set-Cookie') is None
req(core,'/auth/browser/logout',204,'POST',{},headers=new_cookie)
req(core,'/auth/me',401,headers={'Authorization':'Bearer '+b})
print(f'PASS: {checks} security boundary HTTP checks including browser session contract (browser behavior requires manual UAT)')
