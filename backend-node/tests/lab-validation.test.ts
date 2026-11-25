import {describe,it,expect} from 'vitest';
import {resultBody,eventKey} from '../src/laboratory.js';
describe('Core-compatible lab input normalization',()=>{
 it('trims the PHP ASCII boundary consistently and preserves other whitespace',()=>{
  expect(resultBody.parse({result_text:' \t dummy \n'}).result_text).toBe('dummy');
  expect(resultBody.parse({result_text:'\u00a0dummy\u00a0'}).result_text).toBe('\u00a0dummy\u00a0');
 });
 it('rejects blank, NUL, excessive UTF-8 bytes, and unknown fields',()=>{
  for(const result_text of ['  ','a\0b','é'.repeat(1001)])expect(resultBody.safeParse({result_text}).success).toBe(false);
  expect(resultBody.safeParse({result_text:'dummy',status:'completed'}).success).toBe(false);
  expect(eventKey.parse('ABCD0000-1234-4567-89AB-0123456789AB')).toBe('abcd0000-1234-4567-89ab-0123456789ab');
 });
});
