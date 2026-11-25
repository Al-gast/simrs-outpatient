import {describe,it,expect} from 'vitest';
import {clinicalSteps,nextClinicalStep,canFinishReview,orderedLines,paymentPercent,transactionLabel,invoiceSearch,ancillaryEmpty} from '../src/lib/workflow';
import {canAccess,roles} from '../src/lib/roles';
import type {BillingLine} from '../src/lib/billing';
describe('guided workflow',()=>{
 it('requires saved record and clean drafts to advance; only review permits finish',()=>{
  clinicalSteps.forEach((step,i)=>{expect(nextClinicalStep(step,true,false,false)).toBe(clinicalSteps[i+1]??null);expect(nextClinicalStep(step,false,false,false)).toBeNull();expect(nextClinicalStep(step,true,true,false)).toBeNull();expect(nextClinicalStep(step,true,false,true)).toBeNull();expect(canFinishReview(step,true,true,false,false,false)).toBe(step==='review');});
  expect(canFinishReview('review',true,true,false,false,true)).toBe(false);
 });
 it('keeps dedicated routes scoped to their role',()=>{for(const role of roles){expect(canAccess(role,'/doctor/7')).toBe(role==='doctor');expect(canAccess(role,'/billing/7')).toBe(role==='cashier');expect(canAccess(role,'/billing-monitor/7')).toBe(role==='admin');expect(canAccess(role,'/doctor/0')).toBe(false);}});
 it('preserves canonical invoice search and display-only identifiers',()=>{expect(transactionLabel('INV-2')).toBe('INV-000002');expect(invoiceSearch('INV-000002')).toBe('INV-2');expect(transactionLabel('PAY-9007199254740993')).toBe('PAY-9007199254740993');});
 it('orders invoice lines without mutating and handles exact payment percentage',()=>{const lines:BillingLine[]=['laboratory','registration','medicine','procedure'].map((category,i)=>({category:category as BillingLine['category'],source_id:String(i),description:'Dummy',quantity:1,unit_price:'1000'}));expect(orderedLines(lines).map(l=>l.category)).toEqual(['registration','procedure','medicine','laboratory']);expect(lines[0].category).toBe('laboratory');expect(paymentPercent('350000','150000','partial')).toBe(42.8);expect(paymentPercent('0','0','paid')).toBe(100);});
 it('does not tell completed visits to create orders',()=>{expect(ancillaryEmpty(true,true)).toContain('Tidak ada resep');expect(ancillaryEmpty(false,true)).toContain('Tidak ada permintaan');});
});
