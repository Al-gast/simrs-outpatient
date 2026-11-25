#!/usr/bin/env python3
"""Static route/collection consistency and artifact syntax, not a Postman run."""
import ast, json, pathlib, re, subprocess
root=pathlib.Path(__file__).resolve().parents[1]
core=(root/'backend-yii/config/web.php').read_text()
core_routes=[]
for method,path in re.findall(r"'([A-Z]+) api/v1/([^']+)'\s*=>",core):
    pattern=re.sub(r'<[^:>]+:([^>]+)>',r'(?:\1)',path)
    core_routes.append((method,re.compile('/'+pattern+'$')))
node_routes=[]
for p in (root/'backend-node/src').glob('*.ts'):
    for method,path in re.findall(r"app\.(get|post|put|patch|delete)\('/api/v1/([^']+)'",p.read_text()):
        node_routes.append((method.upper(),re.compile('/'+re.sub(r':\w+',r'[^/]+',path)+'$')))
# Substitute only known ID placeholders with a positive numeric example. Query params are not route matching.
count=0;js=0
for p in sorted((root/'postman').glob('*collection.json')):
    d=json.loads(p.read_text()); assert d['info']['schema'].endswith('/v2.1.0/collection.json'),p
    def check_events(obj):
        global js
        for e in obj.get('event',[]):
            source=e.get('script',{}).get('exec',[])
            if source:
                source='\n'.join(source) if isinstance(source,list) else source
                # Postman sandbox scripts may use top-level return, so parse in function context.
                result=subprocess.run(['node','--check'],input='(function(){\n'+source+'\n});',text=True,capture_output=True)
                assert result.returncode==0,(p.name,result.stderr);js+=1
    def walk(items):
        global count
        for item in items:
            check_events(item)
            if 'item' in item:walk(item['item']);continue
            r=item['request'];u=r['url'];u=u if isinstance(u,str) else u['raw'];base,path=re.match(r'^\{\{(core_url|node_url)\}\}(/[^?]*)',u).groups()
            path=re.sub(r'\{\{[\w]+\}\}','1',path)
            assert any(m==r['method'] and pat.fullmatch(path) for m,pat in (core_routes if base=='core_url' else node_routes)),(p.name,r['method'],u)
            count+=1
    check_events(d);walk(d['item'])
    print('PASS:',p.name)
for p in root.rglob('*.py'):ast.parse(p.read_text(),filename=str(p))
for p in root.rglob('*.json'):json.loads(p.read_text())
print(f'PASS: {count} Postman request method/path mappings, {js} JavaScript event syntax checks; no HTTP execution')
print('PASS: Python AST and JSON parsing')
