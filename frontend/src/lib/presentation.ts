/** Presentation only: never change API identifiers, dates or money payloads. */
export function dateLabel(value: string): string {
 if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '—';
 const d=new Date(`${value}T00:00:00Z`);
 if(!Number.isFinite(d.valueOf())||d.toISOString().slice(0,10)!==value)return '—';
 return d.toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric',timeZone:'UTC'});
}
export function dateTimeLabel(value:string):string {
 // PostgreSQL ISO timestamps may contain a space and +00 rather than T and +00:00.
 const iso=value.replace(' ','T').replace(/([+-]\d{2})$/,'$1:00');
 if(!/(?:Z|[+-]\d{2}:\d{2})$/.test(iso))return '—';
 const d=new Date(iso);return Number.isFinite(d.valueOf())?`${d.toLocaleString('id-ID',{timeZone:'Asia/Jakarta',day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit',hourCycle:'h23'})} WIB`:'—';
}
export function groupRupiah(value:string):string {
 return /^(0|[1-9]\d*)$/.test(value)?value.replace(/\B(?=(\d{3})+(?!\d))/g,'.'):value;
}
/** Only canonical integers or unambiguous Indonesian grouping may become a payload. */
export function normalizeRupiah(value:string):string {
 return /^[1-9]\d{0,2}(\.\d{3})+$/.test(value)?value.replace(/\./g,''):value;
}
/** Existing separators belong to the UI; newly typed non-digits are never silently removed. */
export function editRupiah(previous:string,next:string,inputType:string):string {
 if(!/^(0|[1-9]\d*)$/.test(previous)||!['insertText','deleteContentBackward','deleteContentForward','deleteByCut'].includes(inputType))return normalizeRupiah(next);
 const shown=groupRupiah(previous);
 let a=0,b=0;
 while(a<shown.length&&a<next.length&&shown[a]===next[a])a++;
 while(b<shown.length-a&&b<next.length-a&&shown[shown.length-1-b]===next[next.length-1-b])b++;
 const inserted=next.slice(a,next.length-b);
 if(!/^\d*$/.test(inserted))return normalizeRupiah(next);
 const left=shown.slice(0,a).replace(/\./g,''),right=shown.slice(shown.length-b).replace(/\./g,'');
 // Backspace/delete on a generated separator removes the adjacent digit instead of trapping the cursor.
 if(inserted===''&&shown.slice(a,shown.length-b)==='.'){
  if(inputType==='deleteContentBackward')return left.slice(0,-1)+right;
  if(inputType==='deleteContentForward')return left+right.slice(1);
 }
 return left+inserted+right;
}
export const statusColor=(status:string):string=>({waiting:'gold',in_exam:'blue',services_pending:'purple',ready_for_billing:'cyan',completed:'green',pending:'gold',done:'green',dispensed:'green',paid:'green',partial:'orange',unpaid:'red',unissued:'default',active:'green',inactive:'default'} as Record<string,string>)[status]??'default';
export function emptyLabel(noun:string,filtered:boolean):string{return filtered?`Tidak ada ${noun} yang sesuai dengan filter.`:`Belum ada ${noun}.`;}
/** Convert positive UI quantity to the existing signed integer stock adjustment API value. */
export function signedStockDelta(direction:1|-1,quantity:number|null):number|null {
 return quantity!==null&&Number.isInteger(quantity)&&quantity>=1&&quantity<=1000000?direction*quantity:null;
}
