import { Head } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';

type Step = { title: string; note: string; state: 'done' | 'current' | 'todo' };

const DEMO = {
    code: 'BOP-2048',
    phone: '0905123456',
    name: 'Nguyễn Minh',
    status: 'Đang thuê',
    start: '18/06',
    end: '21/06',
    payText: '525.000đ',
    items: 'Lều Coleman 4 người ×1, Đèn lều tích điện ×2',
    timeline: [
        { title: 'Đã đặt giữ chỗ', note: '18/06 · 09:12', state: 'done' },
        { title: 'Đã xác nhận', note: 'Tụi mình đã gọi xác nhận', state: 'done' },
        { title: 'Đang thuê', note: 'Đã giao đồ, chúc chuyến đi vui', state: 'current' },
        { title: 'Đã trả · hoàn cọc', note: 'Dự kiến 21/06', state: 'todo' },
    ] as Step[],
};

const inputCls = 'h-12 w-full rounded-[11px] border border-cardBorder bg-white px-3.5 text-[15px] text-ink outline-none focus:border-grass';
const labelCls = 'mb-1.5 block text-[11px] font-bold uppercase tracking-[0.05em] text-[#8a967a]';

export default function OrderLookup() {
    const [code, setCode] = useState('');
    const [phone, setPhone] = useState('');
    const [state, setState] = useState<'idle' | 'notfound' | 'found'>('idle');

    const doLookup = () => {
        if (code.trim().toUpperCase() === DEMO.code && phone.trim() === DEMO.phone) setState('found');
        else setState('notfound');
    };
    const valid = code.trim().length >= 3 && phone.trim().length >= 8;

    return (
        <>
            <Head title="Tra cứu đơn thuê" />
            <main className="mx-auto max-w-[640px] px-5 pb-12 pt-[38px]">
                <h1 className="mb-2 font-extrabold tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,32px)' }}>Tra cứu đơn thuê</h1>
                <p className="mb-6 text-moss">Nhập mã đơn và số điện thoại đã đặt để xem trạng thái.</p>

                <div className="mb-[22px] rounded-card border border-cardBorder bg-card p-5">
                    <div className="flex flex-col gap-3">
                        <div>
                            <label className={labelCls}>Mã đơn</label>
                            <input value={code} onChange={(e) => setCode(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doLookup()} placeholder="VD: BOP-2048" className={`${inputCls} font-mono tracking-[0.04em]`} />
                        </div>
                        <div>
                            <label className={labelCls}>Số điện thoại</label>
                            <input value={phone} onChange={(e) => setPhone(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && doLookup()} placeholder="Số điện thoại đặt đơn" inputMode="tel" className={inputCls} />
                        </div>
                        <button onClick={doLookup} disabled={!valid} className="h-12 rounded-control text-[15px] font-bold text-white transition disabled:cursor-not-allowed" style={{ background: valid ? '#557A2B' : '#c4cfae' }}>
                            Tra cứu đơn
                        </button>
                        <div className="text-center font-mono text-[11px] text-[#a3ad92]">Thử: BOP-2048 · 0905123456</div>
                    </div>
                </div>

                {state === 'notfound' && (
                    <div className="rounded-[14px] border bg-white p-[22px] text-center" style={{ borderColor: '#ecd3c4', color: '#9a5a3a' }}>
                        <div className="mb-1 font-bold">Không tìm thấy đơn</div>
                        <div className="text-[14px] text-[#8a967a]">Kiểm tra lại mã đơn và số điện thoại giúp tụi mình nhé.</div>
                    </div>
                )}

                {state === 'found' && (
                    <div className="rounded-card border border-cardBorder bg-card p-[22px]">
                        <div className="mb-[18px] flex flex-wrap items-start justify-between gap-2.5">
                            <div>
                                <div className="font-mono text-[20px] font-bold tracking-[0.06em] text-grass">{DEMO.code}</div>
                                <div className="mt-[3px] text-[14px] text-moss">{DEMO.name} · {DEMO.phone}</div>
                            </div>
                            <span className="rounded-pill px-3 py-1.5 text-[12px] font-bold" style={{ background: '#dcebc4', color: '#3a5a1f' }}>{DEMO.status}</span>
                        </div>
                        <div className="mb-5 flex flex-wrap gap-3.5 text-[14px]">
                            <Box k="Nhận" v={DEMO.start} />
                            <Box k="Trả" v={DEMO.end} />
                            <Box k="Tổng (COD)" v={DEMO.payText} accent />
                        </div>
                        <div className="mb-2.5 text-[13px] text-[#8a967a]">Thiết bị: {DEMO.items}</div>
                        <div className="border-t border-cardBorder pt-4">
                            {DEMO.timeline.map((tl, i) => {
                                const last = i === DEMO.timeline.length - 1;
                                const dotBg = tl.state === 'todo' ? '#d6ddc4' : '#557A2B';
                                return (
                                    <div key={i} className="flex items-start gap-3 pb-3.5">
                                        <div className="flex flex-none flex-col items-center">
                                            <span className="h-3 w-3 rounded-full" style={{ background: dotBg, boxShadow: tl.state === 'current' ? '0 0 0 4px rgba(85,122,43,.18)' : 'none' }} />
                                            {!last && <span className="mt-1 w-[2px] flex-1" style={{ minHeight: 22, background: tl.state === 'done' ? '#557A2B' : '#e3e8d6' }} />}
                                        </div>
                                        <div className="pt-px">
                                            <div className="text-[14px]" style={{ fontWeight: tl.state === 'current' ? 700 : 600, color: tl.state === 'todo' ? '#a3ad92' : '#18230F' }}>{tl.title}</div>
                                            <div className="text-[12px] text-[#a3ad92]">{tl.note}</div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}
            </main>
        </>
    );
}

function Box({ k, v, accent }: { k: string; v: string; accent?: boolean }) {
    return (
        <div className="rounded-[10px] px-3.5 py-2.5" style={{ background: '#f1f4ea' }}>
            <div className="text-[11px] text-[#8a967a]">{k}</div>
            <div className="font-mono font-bold" style={{ color: accent ? '#557A2B' : '#18230F' }}>{v}</div>
        </div>
    );
}

OrderLookup.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
