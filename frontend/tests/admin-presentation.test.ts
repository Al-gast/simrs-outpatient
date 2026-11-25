import {describe,it,expect} from 'vitest';
import {invoiceTotals,dailySeries,paymentSlices} from '../src/lib/reporting';
import {accountPatch,auditLabel,auditActions,auditOptions} from '../src/lib/admin-presentation';
describe('dashboard and administration',()=>{
 it('keeps current visit invoice totals separate from received-in-period cash',()=>{
  const groups=[{status:'paid',count:'1',total_amount:'350000',paid_amount:'350000',outstanding:'0'},{status:'unpaid',count:'1',total_amount:'25000',paid_amount:'0',outstanding:'25000'}];
  expect(invoiceTotals(groups)).toEqual({total:'375000',paid:'350000',outstanding:'25000'});
  expect(invoiceTotals([{status:'paid',count:'1',outstanding:'0'}])).toBeNull();
  expect(paymentSlices(groups).slices.map(s=>s.end)).toEqual([50,100]);
 });
 it('fills calendar gaps including leap day',()=>{expect(dailySeries({from:'2024-02-28',to:'2024-03-01'},[{day:'2024-02-29',value:'2'}]).map(r=>r.value)).toEqual(['0','2','0']);});
 it('sends full versioned account PATCH with only intended change',()=>{const row={name:'Dummy',role:'doctor' as const,staff_id:'1',active:true,version:2};expect(accountPatch(row,{kind:'reset',password:'Dummy-password'})).toEqual({name:'Dummy',role:'doctor',staff_id:'1',active:true,expected_version:2,password:'Dummy-password'});expect(accountPatch(row,{kind:'status',active:false})).toEqual({name:'Dummy',role:'doctor',staff_id:'1',active:false,expected_version:2});});
 it('translates known actions and preserves safe unknown codes',()=>{expect(auditLabel(auditActions,'record_payment')).toBe('Mencatat pembayaran');expect(auditLabel(auditActions,'constructor')).toBe('constructor');expect(auditOptions(auditActions,['legacy_action','<bad>']).some(x=>x.value==='legacy_action')).toBe(true);});
});
