#!/usr/bin/env python3
"""Run integration tests in a new disposable Compose project, never against a user's DB.
Requires Python 3.9+, Docker Compose v2, internet for first build, free default ports.
Logs only dummy test data, never copy .env, never delete an existing project.
"""
import argparse, datetime, json, os, pathlib, shutil, socket, subprocess, sys, tempfile, time, urllib.request, uuid

ROOT = pathlib.Path(__file__).resolve().parents[1]
STAGES = [
    ['python3','scripts/smoke.py'],
    ['python3','scripts/master-smoke.py'],
    ['python3','scripts/visit-smoke.py'],
    ['python3','scripts/clinical-smoke.py','--db-check'],
    ['python3','scripts/ancillary-smoke.py','--db-check'],
    ['python3','scripts/billing-smoke.py','--db-check'],
    ['python3','scripts/report-admin-smoke.py'],
    ['python3','scripts/security-smoke.py'],
    ['sh','scripts/verify-db-grants.sh'],
    ['sh','scripts/verify-invariants.sh'],
]


def free_ports():
    for port in (3000, 3001, 8080):
        with socket.socket(socket.AF_INET,socket.SOCK_STREAM) as probe:
            probe.settimeout(0.5)
            if probe.connect_ex(('127.0.0.1',port))==0:raise RuntimeError(f'Port {port} has a live listener. Stop the ordinary app with docker compose down (no -v).')
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
            sock.setsockopt(socket.SOL_SOCKET,socket.SO_REUSEADDR,1)
            try:
                sock.bind(('127.0.0.1', port))
            except OSError as e:
                raise RuntimeError(f'Port {port} is occupied. Stop the ordinary app with docker compose down (no -v), then retry.') from e


def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--keep',action='store_true',help='Keep the isolated project for browser verification. Prints exact cleanup command.')
    parser.add_argument('--outage',action='store_true',help='Also execute actual Node outage and recovery test.')
    parser.add_argument('--dry-run',action='store_true',help='Print planned stages only; no Docker or database writes.')
    args=parser.parse_args()
    if args.dry_run:
        print('Isolated copy -> new .env -> Compose build + migrations + seed -> readiness -> integration stages -> query plans -> isolated cleanup')
        for stage in STAGES:print(' '.join(stage))
        if args.outage:print('python3 scripts/ancillary-smoke.py --db-check --outage (then wait for recovery)')
        print('Migration rollback/reapply on fresh seeded dummy DB before integration tests')
        return 0
    # An external environment must never point child scripts at another Compose project or credentials.
    env={k:v for k,v in os.environ.items() if not k.startswith(('COMPOSE_', 'POSTGRES_', 'CORE_DB_', 'REPORT_DB_', 'DEMO_PASSWORD_', 'JWT_', 'FRONTEND_ORIGIN', 'PYTHONOPTIMIZE'))}
    # Explicit project context; preserve user's Docker context selection.
    subprocess.run(['docker','compose','version'],check=True,env=env)
    subprocess.run(['docker','info','--format','{{.ServerVersion}}'],check=True,env=env)
    free_ports()
    stamp=datetime.datetime.now().strftime('%Y%m%d-%H%M%S')+'-'+uuid.uuid4().hex[:8]
    name='simrs-qa-'+stamp.lower();env['COMPOSE_PROJECT_NAME']=name
    evidence=ROOT/'qa-results'/stamp;evidence.mkdir(parents=True,exist_ok=False)
    temp=pathlib.Path(tempfile.mkdtemp(prefix=name+'-'));work=temp/'simrs-outpatient'
    shutil.copytree(ROOT,work,ignore=shutil.ignore_patterns('.git','.env','.env.*','node_modules','vendor','dist','qa-results','__pycache__','.DS_Store'))
    # setup-env.sh does not depend on .env.example. Never copy real secrets.
    summary={'project':name,'workdir':str(work),'started_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'stages':[],'status':'FAIL'}
    started=False;status=1
    def run(label,cmd,input_path=None):
        print(f'\n== {label} ==',flush=True)
        start=time.monotonic();log=evidence/(label+'.log')
        with log.open('w') as fp:
            fp.write('Command: '+' '.join(cmd)+'\n');fp.flush()
            inp=input_path.open('r') if input_path else None
            try:
                child=subprocess.Popen(cmd,cwd=work,env=env,stdin=inp,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,errors='replace',bufsize=1)
                assert child.stdout is not None
                for line in child.stdout:
                    print(line,end='',flush=True);fp.write(line)
                rc=child.wait()
            finally:
                if inp:inp.close()
        summary['stages'].append({'stage':label,'exit_code':rc,'seconds':round(time.monotonic()-start,2),'log':log.name})
        if rc:raise RuntimeError(f'{label} failed (exit {rc}); see {log}')
    def wait_ready(timeout=180):
        urls=['http://localhost:8080/api/v1/health','http://localhost:3001/api/v1/health','http://localhost:3000/']
        deadline=time.monotonic()+timeout
        while time.monotonic()<deadline:
            try:
                for url in urls:
                    with urllib.request.urlopen(url,timeout=5) as response:
                        assert response.status==200
                        if '/health' in url:assert json.load(response)['data']['status']=='ready'
                return
            except (OSError,ValueError,KeyError,AssertionError):time.sleep(2)
        raise RuntimeError('Services not ready; inspect Compose logs.')
    try:
        run('01-env',['sh','scripts/setup-env.sh'])
        run('02-compose-config',['docker','compose','config','--quiet'])
        started=True
        run('03-build-start',['docker','compose','--parallel','1','build'])
        run('03a-start',['docker','compose','up','--no-build','-d'])
        wait_ready()
        run('04-service-status',['docker','compose','ps','-a'])
        run('05-migration-history',['docker','compose','exec','-T','db','psql','-U','simrs_owner','-d','simrs','-At','-v','ON_ERROR_STOP=1','-c','SELECT version FROM migration ORDER BY version'])
        # Check new migration down/up with existing seed data; no business traffic on this isolated stack.
        run('05a-migration-down',['docker','compose','run','--rm','--no-deps','migrate','php','yii','migrate/down','1','--interactive=0'])
        run('05b-migration-up',['docker','compose','run','--rm','--no-deps','migrate','php','yii','migrate/up','--interactive=0'])
        wait_ready()
        # Dockerfiles run npm build + Vitest; PHP policies are also executed inside the runtime image.
        run('06-php-policies',['docker','compose','exec','-T','yii','sh','-ec','for f in tests/*policy.php; do php "$f"; done'])
        for i,cmd in enumerate(STAGES,7):run(f'{i:02d}-'+pathlib.Path(cmd[1]).stem,cmd)
        if args.outage:
            run('20-outage',['python3','scripts/ancillary-smoke.py','--db-check','--outage'])
            wait_ready()
            run('21-invariants-after-outage',['sh','scripts/verify-invariants.sh'])
        run('22-query-plans',['docker','compose','exec','-T','db','psql','-U','simrs_owner','-d','simrs','-v','ON_ERROR_STOP=1'],work/'scripts/explain-reporting.sql')
        summary['status']='PASS';status=0
    except (Exception,KeyboardInterrupt) as exc:
        summary['error']=str(exc);print(f'FAIL: {exc}',file=sys.stderr)
        if started:
            try:run('90-service-logs',['docker','compose','logs','--no-color','--tail=100'])
            except Exception:pass
    finally:
        if started and not args.keep:
            try:run('99-cleanup',['docker','compose','down','-v','--remove-orphans'])
            except Exception as exc:
                summary['status']='FAIL';summary['cleanup_error']=str(exc);status=1
                print(f'Cleanup failed. Isolated project: {name}; directory: {work}',file=sys.stderr)
        if not args.keep and 'cleanup_error' not in summary:shutil.rmtree(temp)
        else:
            print(f'Isolated project directory: {work}\nDemo prefix: grep ^DEMO_PASSWORD_PREFIX= {work}/.env\nCleanup ONLY this QA project: cd "{work}" && docker compose -p {name} down -v')
        summary['finished_at']=datetime.datetime.now(datetime.timezone.utc).isoformat()
        (evidence/'summary.json').write_text(json.dumps(summary,indent=2)+'\n')
        print(f'{summary["status"]}: evidence in {evidence}')
    return status

if __name__=='__main__':
    try:sys.exit(main())
    except Exception as exc:print(f'FAIL: {exc}',file=sys.stderr);sys.exit(1)
