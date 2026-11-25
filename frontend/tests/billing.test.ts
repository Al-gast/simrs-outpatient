import {describe,it,expect} from 'vitest';
import {validAmount,validPending} from '../src/lib/billing';
describe('QA payment validation',()=>{
 it('rejects input rather than silently changing a nominal amount',()=>{
  for(const value of ['1.5','1,5','10.000','Rp 10000','-5000','10000\n','1000000000000000000'])expect(validAmount(value,'999999999999999999')).toBe(false);
  expect(validAmount('10000','10000')).toBe(true);expect(validAmount('10001','10000')).toBe(false);
 });
 it('recovers only well-formed retry attempts',()=>{
  const p={key:'abcd0000-1234-4567-89ab-0123456789ab',body:{amount:'10000',method:'cash',expected_revision:1}};
  expect(validPending(p)).toBe(true);expect(validPending({...p,body:{...p.body,amount:'1.5'}})).toBe(false);
 });
});
