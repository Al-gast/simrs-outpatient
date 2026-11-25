import {describe,it,expect} from 'vitest';
import {dateLabel,dateTimeLabel,editRupiah,groupRupiah,normalizeRupiah,statusColor,emptyLabel,signedStockDelta} from '../src/lib/presentation';
import {validAmount} from '../src/lib/billing';
describe('presentation without changing business payloads',()=>{
 it('formats large integer strings without rounding',()=>expect(groupRupiah('9007199254740993')).toBe('9.007.199.254.740.993'));
 it('handles typing and deleting on grouped values',()=>{expect(editRupiah('999','9999','insertText')).toBe('9999');expect(editRupiah('150000','150.00','deleteContentBackward')).toBe('15000');expect(editRupiah('1000','1.0005','insertText')).toBe('10005');});
 it('accepts only explicit grouping and keeps malformed values invalid',()=>{expect(normalizeRupiah('150.000')).toBe('150000');for(const value of ['1.5','1,5','-100','1.000,50','Rp 1000','01.000','abc'])expect(validAmount(normalizeRupiah(value),'350000')).toBe(false);expect(editRupiah('150000','1.5','insertFromPaste')).toBe('1.5');expect(validAmount(normalizeRupiah('350.001'),'350000')).toBe(false);});
 it('formats calendar dates and timezone-aware instants',()=>{expect(dateLabel('2026-02-29')).toBe('—');expect(dateLabel('2024-02-29')).not.toBe('—');expect(dateTimeLabel('2026-11-24 16:30:00+00')).toBe(dateTimeLabel('2026-11-24T23:30:00+07:00'));expect(dateTimeLabel('2026-11-24 16:30:00')).toBe('—');});
 it('keeps the stock direction explicit and rejects invalid quantities',()=>{expect(signedStockDelta(1,50)).toBe(50);expect(signedStockDelta(-1,30)).toBe(-30);for(const n of [null,0,-1,1.5,1000001])expect(signedStockDelta(-1,n)).toBeNull();});
 it('maps statuses and contextual empty states consistently',()=>{expect(statusColor('completed')).toBe(statusColor('paid'));expect(statusColor('pending')).toBe(statusColor('waiting'));expect(emptyLabel('resep',true)).toContain('filter');});
});
