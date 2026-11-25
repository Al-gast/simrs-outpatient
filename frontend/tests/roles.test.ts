import {describe,it,expect} from 'vitest';
import {roles,workspace,canAccess} from '../src/lib/roles';
import {resources,masterPath,canWriteMaster,catalog} from '../src/lib/master';
describe('route policy',()=>{it('only permits own workspace and profile',()=>{for(const r of roles){for(const t of roles)expect(canAccess(r,workspace[t].path)).toBe(r===t);expect(canAccess(r,'/profile')).toBe(true);expect(canAccess(r,'/unknown')).toBe(false);}});});

it('enforces master read and write matrix',()=>{for(const role of roles)for(const resource of resources){expect(canAccess(role,masterPath(resource))).toBe(catalog[resource].read.includes(role));expect(canWriteMaster(role,resource)).toBe(role==='admin'||(role==='registration'&&resource==='patients'));}});

import {canRegister,canReadHistory,canStartVisit,visitStatuses} from '../src/lib/visits';
it('enforces visit route and action policy',()=>{for(const r of roles){expect(canAccess(r,'/visits')).toBe(r==='admin');expect(canRegister(r)).toBe(r==='registration');expect(canReadHistory(r)).toBe(['registration','doctor'].includes(r));for(const s of visitStatuses)expect(canStartVisit(r,s)).toBe(r==='doctor'&&s==='waiting');}});
