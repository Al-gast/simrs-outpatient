import {describe,it,expect} from 'vitest';
import {draft,validDraft,procedureTotal,canOpenClinical,canAmendDiagnosis} from '../src/lib/clinical';
describe('clinical policy',()=>{
 it('requires record fields and valid numeric signs',()=>{const r=draft(null);expect(validDraft(r)).toBe(false);Object.assign(r,{complaint:'Dummy',anamnesis:'Dummy',physical_exam:'Dummy',primary_diagnosis:'A',vital_signs:{systolic:120,diastolic:80,pulse:80,temperature:37,respiratory_rate:20,weight:60}});expect(validDraft(r)).toBe(true);r.secondary_diagnoses=['A'];expect(validDraft(r)).toBe(false);});
 it('keeps prices exact and restricts actions',()=>{expect(procedureTotal([{procedure_id:'1',name_snapshot:'Dummy',unit_price:'999999999999',quantity:100}])).toBe('99999999999900');expect(canOpenClinical('registration','in_exam')).toBe(false);expect(canOpenClinical('doctor','waiting')).toBe(false);expect(canAmendDiagnosis('completed')).toBe(true);});
});
