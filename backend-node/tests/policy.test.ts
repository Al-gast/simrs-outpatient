import {describe,expect,it} from 'vitest';
import {roles,permits,validRequestId,validBearer} from '../src/policy.js';
describe('least privilege and request validation',()=>{
  it('allows only matching role across all six roles',()=>{for(const actor of roles)for(const target of roles)expect(permits(actor,[target])).toBe(actor===target);});
  it('rejects unknown role and case changes',()=>{expect(permits('ADMIN',['admin'])).toBe(false);expect(permits(null,['admin'])).toBe(false);});
  it('validates request IDs and bearer shape',()=>{expect(validRequestId('request-12')).toBe(true);expect(validRequestId('bad\nheader')).toBe(false);expect(validBearer('Bearer a.b.c')).toBe(true);expect(validBearer('Bearer a.b')).toBe(false);});
});
