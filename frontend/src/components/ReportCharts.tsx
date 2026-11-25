import {Card} from 'antd';
import {chartRatio,dailySeries,formatNumber,paymentSlices,type Period,type PaymentGroup} from '../lib/reporting';
import {dateLabel} from '../lib/presentation';
const currency=(v:string)=>'Rp'+formatNumber(v);
export function TrendChart({period,rows,label,money=false}:{period:Period;rows:{day:string;value:string}[];label:string;money?:boolean}){
 const series=dailySeries(period,rows),max=series.reduce((a,r)=>BigInt(r.value)>a?BigInt(r.value):a,1n),format=money?currency:formatNumber;
 const points=series.map((r,i)=>({x:22+(series.length===1?178:i*356/(series.length-1)),y:132-chartRatio(r.value,max.toString())*110,...r}));
 const path=points.map((p,i)=>`${i?'L':'M'}${p.x} ${p.y}`).join(' ');
 const active=series.some(r=>r.value!=='0');
 return <Card title={label} className="trend-card"><p className="report-hint">{money?'Berdasarkan tanggal pembayaran (WIB)':'Berdasarkan tanggal kunjungan (WIB)'} · seluruh periode</p>{active?<><div className="chart-scale"><span>Puncak harian: <strong>{format(max.toString())}</strong></span><span>Dasar: {format('0')}</span></div><svg className="trend-svg" viewBox="0 0 400 154" role="img" aria-label={`${label}, ${dateLabel(period.from)} sampai ${dateLabel(period.to)}. Puncak harian ${format(max.toString())}. Data per tanggal tersedia di bawah.`}>{[22,59,95,132].map(y=><line key={y} x1="22" x2="378" y1={y} y2={y} stroke="#e2e8f0" strokeDasharray={y===132?'none':'4 4'}/>)}{points.length>1&&<path d={`${path} L${points[points.length-1].x} 132 L${points[0].x} 132 Z`} fill="#ccfbf1" opacity=".65"/>}<path d={path} fill="none" stroke="#0f766e" strokeWidth="2.5" strokeLinejoin="round" vectorEffect="non-scaling-stroke"/>{points.filter(p=>p.value!=='0').map(p=><circle key={p.day} cx={p.x} cy={p.y} r={points.length>60?2:3.5} fill="#0f766e"><title>{dateLabel(p.day)}: {format(p.value)}</title></circle>)}</svg><div className="chart-dates"><span>{dateLabel(period.from)}</span>{period.from!==period.to&&<span>{dateLabel(period.to)}</span>}</div></>:<div className="chart-empty">Tidak ada aktivitas pada periode ini.</div>}<details className="chart-data"><summary>Data per tanggal</summary><div className="chart-data-scroll"><table><thead><tr><th>Tanggal</th><th>Jumlah</th></tr></thead><tbody>{series.map(r=><tr key={r.day}><td>{dateLabel(r.day)}</td><td>{format(r.value)}</td></tr>)}</tbody></table></div></details></Card>;
}
const colors:Record<string,string>={paid:'#15803d',partial:'#d97706',unpaid:'#dc2626'};
export function PaymentDonut({groups}:{groups:PaymentGroup[]}){
 const {total,slices}=paymentSlices(groups);const gradient=total==='0'?'#e2e8f0':`conic-gradient(${slices.map(s=>`${colors[s.status]||'#64748b'} ${s.start}% ${s.end}%`).join(',')})`;
 return <div className="payment-donut-wrap"><div className="payment-donut" style={{background:gradient}} role="img" aria-label={`Total ${formatNumber(total)} invoice. Rincian status tersedia pada tabel.`}><div><strong>{formatNumber(total)}</strong><span>Invoice</span></div></div><p className="report-hint">Status terkini invoice dari kunjungan pada periode terpilih.</p></div>;
}
