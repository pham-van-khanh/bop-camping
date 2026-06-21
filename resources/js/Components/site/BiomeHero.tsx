// BiomeHero — "Cùng một khu trại, đổi địa điểm."
// Port 1:1 từ design_handoff_bop_camping/reference/BiomeHero.jsx (cảnh 2D CSS/SVG).
// Một khu trại (lều + lửa + khói, luôn hiện) trong khi phông nền cross-morph qua
// ĐỒNG CỎ → RỪNG → NÚI → BIỂN theo vòng lặp. Tôn trọng prefers-reduced-motion.
import { CSSProperties, Fragment, useEffect, useRef, useState } from 'react';

const SW = 1600, SH = 900;
const DUR = 30;        // giây cho trọn vòng (4 biôm)
const SEG = DUR / 4;   // giây mỗi biôm
const TF = 0.3;        // tỉ lệ mỗi đoạn dùng để chuyển cảnh
const HORIZON = 560;

const smoothstep = (t: number) => t * t * (3 - 2 * t);

function scenePos(t: number) {
    const phase = (t / SEG) % 4;
    const base = Math.floor(phase);
    const frac = phase - base;
    const hold = 1 - TF;
    return frac < hold ? base : base + smoothstep((frac - hold) / TF);
}
function biomeOpacity(p: number, i: number) {
    const base = Math.floor(p) % 4;
    const next = (base + 1) % 4;
    const blend = p - Math.floor(p);
    if (i === base && i === next) return 1;
    if (i === base) return 1 - blend;
    if (i === next) return blend;
    return 0;
}

const prefersReduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function useLoopTime() {
    const reduced = useRef(prefersReduced());
    const [t, setT] = useState(reduced.current ? 1 : 0);
    useEffect(() => {
        if (reduced.current) return;
        let raf = 0;
        const start = performance.now();
        const step = (now: number) => { setT(((now - start) / 1000) % DUR); raf = requestAnimationFrame(step); };
        raf = requestAnimationFrame(step);
        return () => cancelAnimationFrame(raf);
    }, []);
    return t;
}

const layer = (op: number, z: number, extra?: CSSProperties): CSSProperties => ({
    position: 'absolute', left: 0, top: 0, width: SW, height: SH, opacity: op, zIndex: z, pointerEvents: 'none', ...extra,
});

// ── BIÔM 1 — ĐỒNG CỎ ──────────────────────────────────────────────────────────
function Meadow({ t, wind = 1 }: { t: number; wind?: number }) {
    const breeze = (Math.sin(t * 0.7) * 0.5 + Math.sin(t * 1.9 + 1) * 0.25) * wind;
    const treeSway = (Math.sin(t * 0.6) * 1.4 + Math.sin(t * 1.7) * 0.5) * wind;

    const flowers = [];
    for (let i = 0; i < 18; i++) {
        const x = 70 + ((i * 173) % (SW - 120));
        const baseY = HORIZON + 150 + ((i * 67) % 220);
        const stem = 26 + (i % 4) * 8;
        const ph = i * 1.3;
        const tip = breeze * (10 + stem * 0.3) + Math.sin(t * 1.6 + ph) * 4;
        const headX = x + tip, headY = baseY - stem;
        const c = ['#f6d35b', '#ffffff', '#e6739f', '#f0913a', '#c98ce0'][i % 5];
        const r = 5 + (i % 3);
        flowers.push(
            <g key={i}>
                <path d={`M${x},${baseY} Q${x + tip * 0.4},${baseY - stem * 0.55} ${headX},${headY}`} fill="none" stroke="#6f9540" strokeWidth="2.4" strokeLinecap="round" />
                {[0, 1, 2, 3, 4].map((k) => <ellipse key={k} cx={headX} cy={headY} rx={r * 0.7} ry={r * 1.5} fill={c} opacity="0.92" transform={`rotate(${k * 72} ${headX} ${headY})`} />)}
                <circle cx={headX} cy={headY} r={r * 0.7} fill="#f6c64a" />
            </g>
        );
    }

    const blades = [];
    for (let i = 0; i < 70; i++) {
        const x = (i * 23.2) % (SW + 20) - 10;
        const baseY = 902;
        const h = 70 + ((i * 37) % 90);
        const ph = i * 0.7;
        const lean = breeze * (h * 0.5) + Math.sin(t * 1.5 + ph) * (8 + h * 0.08) * wind;
        const tipX = x + lean, tipY = baseY - h;
        const midX = x + lean * 0.4, midY = baseY - h * 0.55;
        const col = ['#5d8235', '#6f9540', '#4f7a2c', '#7fa64b'][i % 4];
        blades.push(<path key={i} d={`M${x - 4},${baseY} Q${midX},${midY} ${tipX},${tipY} Q${midX + 3},${midY} ${x + 4},${baseY} Z`} fill={col} opacity="0.92" />);
    }

    return (
        <Fragment>
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,#3f9fe0 0%,#71bbe9 30%,#a9d8ef 52%,#eaf3d6 66%)' }} />
            <div style={{ position: 'absolute', left: 1180, top: 120, width: 150, height: 150, borderRadius: '50%', background: 'radial-gradient(circle,#fff6d8 0%,#ffe9a8 55%,rgba(255,233,168,0) 72%)', boxShadow: `0 0 ${120 + Math.sin(t * 1.1) * 18}px 50px rgba(255,231,160,.5)` }} />
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                <path d={`M0,${HORIZON} Q400,${HORIZON - 60} 820,${HORIZON - 10} T1600,${HORIZON - 40} L1600,900 L0,900 Z`} fill="#bcd97e" />
                <path d={`M0,${HORIZON + 70} Q500,${HORIZON + 10} 1000,${HORIZON + 60} T1600,${HORIZON + 30} L1600,900 L0,900 Z`} fill="#9bc35d" />
                <path d={`M0,${HORIZON + 170} Q420,${HORIZON + 110} 900,${HORIZON + 160} T1600,${HORIZON + 150} L1600,900 L0,900 Z`} fill="#7fa64b" />
                <path d={`M0,${HORIZON + 300} Q600,${HORIZON + 250} 1200,${HORIZON + 300} T1600,${HORIZON + 290} L1600,900 L0,900 Z`} fill="#5d8235" />
                <g transform={`translate(255,440) rotate(${treeSway} 0 140)`}>
                    <rect x="-9" y="70" width="18" height="70" fill="#6b4a2b" />
                    <circle cx="0" cy="46" r="62" fill="#5d8235" />
                    <circle cx="-44" cy="70" r="44" fill="#6f9540" />
                    <circle cx="42" cy="66" r="46" fill="#6f9540" />
                    <circle cx="6" cy="20" r="40" fill="#6f9540" />
                </g>
                <g>
                    <circle cx="1330" cy="800" r="54" fill="#5d8235" />
                    <circle cx="1390" cy="812" r="44" fill="#6f9540" />
                    <circle cx="1285" cy="820" r="38" fill="#6f9540" />
                    <circle cx="120" cy="838" r="46" fill="#557A2B" />
                    <circle cx="180" cy="848" r="36" fill="#6f9540" />
                </g>
                {flowers}
                {blades}
            </svg>
            {Array.from({ length: 10 }).map((_, i) => {
                const x = (i * 167 + t * (14 + i * 3)) % (SW + 60) - 30;
                const y = HORIZON + 60 + ((i * 53) % 240) + Math.sin(t * 0.9 + i) * 22;
                return <div key={i} style={{ position: 'absolute', left: x, top: y, width: 5, height: 5, borderRadius: '50%', background: '#fff7d6', opacity: 0.5 + 0.3 * Math.sin(t * 2 + i), boxShadow: '0 0 6px 2px rgba(255,246,200,.6)' }} />;
            })}
            {[{ x: 720, y: 470, c: '#f6a93b', sp: 0.9 }, { x: 980, y: 540, c: '#e6739f', sp: 1.2 }].map((b, i) => {
                const fx = b.x + Math.sin(t * b.sp + i) * 90;
                const fy = b.y + Math.sin(t * b.sp * 2 + i) * 30;
                const w = 7 + Math.sin(t * 9 + i) * 5;
                const tilt = Math.sin(t * b.sp + i) * 16;
                return (
                    <svg key={i} width="28" height="22" viewBox="0 0 28 22" style={{ position: 'absolute', left: fx, top: fy, transform: `rotate(${tilt}deg)` }}>
                        <ellipse cx={14 - w * 0.5} cy="11" rx={w} ry="9.5" fill={b.c} opacity="0.92" />
                        <ellipse cx={14 + w * 0.5} cy="11" rx={w} ry="9.5" fill={b.c} opacity="0.92" />
                        <rect x="13" y="4" width="2" height="13" rx="1" fill="#3a2a1a" />
                    </svg>
                );
            })}
        </Fragment>
    );
}

// ── BIÔM 2 — RỪNG ─────────────────────────────────────────────────────────────
function Forest({ t }: { t: number }) {
    const pine = (x: number, baseY: number, h: number, col: string) => {
        const w = h * 0.6;
        const tri = (yt: number, ww: number, hh: number) => `${x},${yt} ${x - ww / 2},${yt + hh} ${x + ww / 2},${yt + hh}`;
        return (
            <g key={x + '-' + baseY}>
                <rect x={x - h * 0.035} y={baseY - h * 0.1} width={h * 0.07} height={h * 0.13} fill="#3a2a1a" />
                <polygon points={tri(baseY - h, w * 0.55, h * 0.4)} fill={col} />
                <polygon points={tri(baseY - h * 0.72, w * 0.8, h * 0.42)} fill={col} />
                <polygon points={tri(baseY - h * 0.42, w, h * 0.46)} fill={col} />
            </g>
        );
    };
    const back = [], front = [];
    for (let i = 0; i < 18; i++) back.push(pine(60 + i * 92, 660, 230 + ((i * 53) % 90), '#33502f'));
    for (let i = 0; i < 13; i++) front.push(pine(20 + i * 130, 820, 320 + ((i * 71) % 130), '#22381f'));
    const shaft = (x: number, w: number, op: number) => (
        <div key={x} style={{ position: 'absolute', left: x, top: 0, width: w, height: 720, background: 'linear-gradient(180deg,rgba(255,250,210,.0),rgba(255,247,200,.5),rgba(255,247,200,0))', transform: 'skewX(-12deg)', filter: 'blur(3px)', opacity: op }} />
    );
    return (
        <Fragment>
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,#7fb8d8 0%,#aecfcf 30%,#cfe0cf 48%,#9fbf9c 72%)' }} />
            {shaft(420, 80, 0.7)}{shaft(700, 120, 0.55)}{shaft(1050, 70, 0.6)}
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                {back}
                <rect x="0" y="640" width={SW} height="260" fill="#3f5a37" />
                {front}
                <rect x="0" y="800" width={SW} height="100" fill="#2c4524" />
                <g>
                    {[150, 1180, 1420].map((fx, i) => (
                        <g key={i} transform={`translate(${fx},860)`}>
                            {[-1, 0, 1].map((k) => <path key={k} d={`M0,0 Q${k * 26 - 10},-60 ${k * 40},-110`} stroke="#3a5a32" strokeWidth="6" fill="none" strokeLinecap="round" />)}
                        </g>
                    ))}
                    <g transform="translate(1080,872)"><ellipse cx="0" cy="-14" rx="20" ry="12" fill="#c0533f" /><rect x="-5" y="-14" width="10" height="16" rx="3" fill="#efe3d0" /><circle cx="-7" cy="-16" r="2.5" fill="#f4ece0" /><circle cx="6" cy="-13" r="2" fill="#f4ece0" /></g>
                </g>
            </svg>
            {Array.from({ length: 14 }).map((_, i) => {
                const bx = (i * 113) % SW, by = 360 + ((i * 71) % 360);
                const fx = bx + Math.sin(t * (0.5 + (i % 4) * 0.2) + i) * 40;
                const fy = by + Math.cos(t * 0.4 + i) * 24;
                const tw = 0.3 + 0.7 * Math.abs(Math.sin(t * 2 + i));
                return <div key={i} style={{ position: 'absolute', left: fx, top: fy, width: 6, height: 6, borderRadius: '50%', background: '#fff3b0', opacity: tw * 0.8, boxShadow: '0 0 8px 2px rgba(255,240,160,.7)' }} />;
            })}
            <div style={{ position: 'absolute', left: 0, top: 560, width: SW, height: 150, background: 'linear-gradient(180deg,rgba(255,255,255,.55),rgba(255,255,255,0))', filter: 'blur(14px)', transform: `translateX(${Math.sin(t * 0.5) * 26}px)` }} />
        </Fragment>
    );
}

// ── BIÔM 3 — NÚI ──────────────────────────────────────────────────────────────
function Mountain({ t }: { t: number }) {
    return (
        <Fragment>
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,#4f9fd8 0%,#8fb8e6 34%,#bcd6ee 56%,#e3eef0 66%)' }} />
            <div style={{ position: 'absolute', left: 250, top: 110, width: 110, height: 110, borderRadius: '50%', background: 'radial-gradient(circle,#fffdf2,#f1eede)', boxShadow: '0 0 50px 10px rgba(255,255,255,.5)' }} />
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                <polygon points="0,520 300,260 540,460 820,210 1080,470 1360,250 1600,500 1600,560 0,560" fill="#8aa6c4" opacity="0.8" />
                <polygon points="0,600 360,300 600,560 900,240 1180,560 1460,330 1600,600 1600,900 0,900" fill="#6f86a6" />
                <polygon points="820,300 900,240 980,300 935,335 900,318 865,335" fill="#f3f7fb" />
                <polygon points="290,360 360,300 430,360 392,392 360,376 328,392" fill="#f3f7fb" />
                <polygon points="1390,388 1460,330 1530,388 1492,418 1460,402 1428,418" fill="#f3f7fb" />
                <polygon points="0,720 400,560 760,700 1120,540 1500,690 1600,650 1600,900 0,900" fill="#3f5a47" />
                <rect x="0" y="800" width={SW} height="100" fill="#314a3a" />
                <g>
                    <polygon points="1180,900 1300,790 1430,850 1500,900" fill="#4a5f54" />
                    <polygon points="1300,790 1338,812 1360,800" fill="#dfe9ec" opacity="0.7" />
                    {[90, 170, 250].map((px, i) => <g key={i} transform={`translate(${px},842)`}><rect x="-4" y="-6" width="8" height="22" fill="#2a3a28" /><polygon points="0,-58 -22,-6 22,-6" fill="#27402c" /><polygon points="0,-40 -17,2 17,2" fill="#2f4a32" /></g>)}
                </g>
            </svg>
            {(() => {
                const ex = ((t * 90) % (SW + 300)) - 150, ey = 230 + Math.sin(t * 0.6) * 50;
                const w = 16 + Math.sin(t * 3) * 8;
                return <svg width="60" height="34" viewBox="0 0 60 34" style={{ position: 'absolute', left: ex, top: ey }}><path d={`M4 ${17 + (17 - w)} Q18 ${17 - w} 30 18 Q42 ${17 - w} 56 ${17 + (17 - w)}`} fill="none" stroke="#2c3d33" strokeWidth="3.4" strokeLinecap="round" /></svg>;
            })()}
        </Fragment>
    );
}

// ── BIÔM 4 — BIỂN ─────────────────────────────────────────────────────────────
function Sea({ t }: { t: number }) {
    const glints = [];
    for (let i = 0; i < 9; i++) {
        const y = HORIZON + 20 + i * 18;
        const a = 0.1 + 0.12 * Math.sin(t * (1.3 + i * 0.25) + i);
        glints.push(<div key={i} style={{ position: 'absolute', left: 0, top: y, width: SW, height: 3, background: `rgba(255,255,255,${a})`, transform: `translateX(${Math.sin(t * 0.7 + i) * 18}px)`, filter: 'blur(.6px)' }} />);
    }
    return (
        <Fragment>
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,#4aa6dd 0%,#86cbe8 32%,#bfe1f2 48%,#fff4d8 58%)' }} />
            <div style={{ position: 'absolute', left: 1120, top: 150, width: 130, height: 130, borderRadius: '50%', background: 'radial-gradient(circle,#fff7e0,#ffe6a6 60%,rgba(255,230,166,0) 74%)', boxShadow: '0 0 110px 44px rgba(255,228,150,.5)' }} />
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                <path d={`M0,${HORIZON} Q120,${HORIZON - 46} 300,${HORIZON - 24} L360,${HORIZON} Z`} fill="#6f9270" opacity="0.85" />
            </svg>
            <div style={{ position: 'absolute', left: 0, top: HORIZON, width: SW, height: 230, background: 'linear-gradient(180deg,#3f93c4 0%,#2f7ba8 60%,#bfe0d8 100%)' }} />
            <div style={{ position: 'absolute', left: 1120 - 60, top: HORIZON, width: 120, height: 220, background: 'linear-gradient(180deg,rgba(255,240,190,.7),rgba(255,240,190,0))', filter: 'blur(5px)' }} />
            {glints}
            {(() => {
                const bx = 400 + Math.sin(t * 0.25) * 120, by = HORIZON + 96 + Math.sin(t * 1.1) * 5, tilt = Math.sin(t * 1.1) * 2;
                return (
                    <svg width="110" height="120" viewBox="0 0 110 120" style={{ position: 'absolute', left: bx, top: by - 96, transform: `rotate(${tilt}deg)` }}>
                        <line x1="55" y1="14" x2="55" y2="86" stroke="#5a4636" strokeWidth="3" />
                        <path d="M55 16 L55 78 L18 78 Z" fill="#fbfdff" />
                        <path d="M58 22 L92 76 L58 76 Z" fill="#e8eef3" />
                        <path d="M22 86 Q55 104 90 86 L82 96 Q55 108 30 96 Z" fill="#b5532f" />
                    </svg>
                );
            })()}
            {[{ x: 760, y: 200, s: 1 }, { x: 880, y: 250, s: 0.8 }, { x: 700, y: 280, s: 0.7 }].map((g, i) => {
                const w = 7 + Math.sin(t * 5 + i) * 6;
                return <svg key={i} width={34 * g.s} height={20 * g.s} viewBox="0 0 34 20" style={{ position: 'absolute', left: g.x, top: g.y }}><path d={`M2 ${10 + (10 - w)} Q9 ${10 - w} 17 11 Q25 ${10 - w} 32 ${10 + (10 - w)}`} fill="none" stroke="rgba(70,80,90,.65)" strokeWidth="2.2" strokeLinecap="round" /></svg>;
            })}
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                <path d={`M0,${HORIZON + 215} Q500,${HORIZON + 185} 1000,${HORIZON + 220} T1600,${HORIZON + 205} L1600,900 L0,900 Z`} fill="#ecdcab" />
                <path d={`M0,${HORIZON + 215} Q500,${HORIZON + 185} 1000,${HORIZON + 220} T1600,${HORIZON + 205} L1600,${HORIZON + 240} Q1000,${HORIZON + 250} 500,${HORIZON + 232} T0,${HORIZON + 250} Z`} fill="rgba(255,255,255,.6)" />
                <g transform={`translate(1340,${HORIZON + 320})`}>
                    {[0, 1, 2, 3, 4].map((k) => <ellipse key={k} cx="0" cy="0" rx="6" ry="20" fill="#e08a4a" transform={`rotate(${k * 72})`} />)}
                    <circle cx="0" cy="0" r="7" fill="#eaa066" />
                </g>
                <path d={`M150,${HORIZON + 300} q14,-18 28,0 q-14,8 -28,0`} fill="#f0e3cf" />
            </svg>
        </Fragment>
    );
}

// ── trời chung (mây + chim), luôn hiện ────────────────────────────────────────
function Clouds({ t, wind = 1 }: { t: number; wind?: number }) {
    const puff = (dx: number, dy: number, w: number, h: number) => <div style={{ position: 'absolute', left: dx, top: dy, width: w, height: h, borderRadius: '50%', background: 'rgba(255,255,255,.9)' }} />;
    const cloud = (base: number, sp: number, y: number, sc: number, op: number) => {
        sp = sp * (0.5 + 0.7 * wind);
        const x = ((base + t * sp) % (SW + 500)) - 250;
        return <div style={{ position: 'absolute', left: x, top: y, transform: `scale(${sc})`, opacity: op, filter: 'blur(1.4px)' }}>{puff(0, 26, 110, 64)}{puff(64, 6, 120, 88)}{puff(138, 24, 108, 70)}</div>;
    };
    return <div style={layer(0.92, 6)}>{cloud(150, 7, 90, 1.05, 0.9)}{cloud(820, 5, 180, 0.75, 0.7)}{cloud(1300, 9, 60, 1.3, 0.6)}</div>;
}
function Birds({ t }: { t: number }) {
    const x0 = ((t * 70) % (SW + 300)) - 150;
    const flap = t * 7;
    const bird = (dx: number, dy: number, s: number, ph: number) => {
        const w = 7 + Math.sin(flap + ph) * 8;
        return <svg width={30 * s} height={18 * s} viewBox="0 0 30 18" style={{ position: 'absolute', left: x0 + dx, top: 150 + dy + Math.sin(t * 1.1) * 18 }}><path d={`M2 ${9 + (9 - w)} Q8 ${9 - w} 15 10 Q22 ${9 - w} 28 ${9 + (9 - w)}`} fill="none" stroke="rgba(40,46,40,.55)" strokeWidth="2" strokeLinecap="round" /></svg>;
    };
    return <div style={layer(1, 7)}>{bird(0, 0, 1, 0)}{bird(-54, 30, 0.85, 0.5)}{bird(-50, -32, 0.85, 0.5)}{bird(-104, 60, 0.7, 1)}</div>;
}

// ── khu trại cố định: bóng đất + lều + lửa + khói ─────────────────────────────
function Camp({ t, wind = 1 }: { t: number; wind?: number }) {
    const fx = 980, fy = 812;
    const flick = 0.85 + 0.15 * Math.sin(t * 9) + 0.06 * Math.sin(t * 17 + 1);
    const flame = (w: number, h: number, col: string, ph: number, sp: number) => {
        const fl = 1 + 0.16 * Math.sin(t * sp + ph);
        const sway = Math.sin(t * sp * 0.6 + ph) * (3 + 5 * wind);
        return <div style={{ position: 'absolute', left: fx - w / 2 + sway, top: fy - h * fl, width: w, height: h * fl, background: `linear-gradient(180deg,rgba(255,150,40,0) 0%,${col} 100%)`, borderRadius: '50% 50% 46% 46% / 72% 72% 30% 30%', filter: 'blur(0.6px)' }} />;
    };
    const smoke = [];
    for (let i = 0; i < 6; i++) {
        const cyc = (t * 0.3 + i / 6) % 1;
        const sc = 0.4 + cyc * 1.7;
        smoke.push(<div key={i} style={{ position: 'absolute', left: fx + Math.sin(cyc * 3.2 + i) * (16 + 34 * wind) + cyc * 80 * wind - 38 * sc, top: fy - 30 - cyc * 250 - 38 * sc, width: 76 * sc, height: 76 * sc, borderRadius: '50%', background: `rgba(210,210,212,${Math.sin(cyc * Math.PI) * 0.3})`, filter: 'blur(10px)' }} />);
    }
    const tx = 560, ty = 858;
    return (
        <div style={layer(1, 9)}>
            <div style={{ position: 'absolute', left: 360, top: 838, width: 900, height: 120, borderRadius: '50%', background: 'radial-gradient(ellipse,rgba(40,50,30,.35) 0%,rgba(40,50,30,0) 70%)' }} />
            <div style={{ position: 'absolute', left: fx - 230, top: fy - 170, width: 460, height: 300, borderRadius: '50%', background: `radial-gradient(ellipse,rgba(255,165,70,${0.34 * flick}) 0%,rgba(255,140,60,0) 66%)`, mixBlendMode: 'screen' }} />
            {smoke}
            <svg width={SW} height={SH} viewBox={`0 0 ${SW} ${SH}`} preserveAspectRatio="none" style={{ position: 'absolute', inset: 0 }}>
                <polygon points={`${tx},${ty - 178} ${tx + 222},${ty} ${tx + 116},${ty}`} fill="#b8632f" />
                <polygon points={`${tx},${ty - 178} ${tx - 222},${ty} ${tx - 54},${ty}`} fill="#d7762f" />
                <polygon points={`${tx},${ty - 178} ${tx - 54},${ty} ${tx + 116},${ty}`} fill="#6f3019" />
                <polygon points={`${tx},${ty - 174} ${tx - 22},${ty - 3} ${tx + 22},${ty - 3}`} fill="#241208" />
                <line x1={tx} y1={ty - 178} x2={tx} y2={ty - 206} stroke="#5a4636" strokeWidth="4" />
                <circle cx={tx} cy={ty - 208} r="6" fill="#e8cf86" />
                <rect x={fx - 46} y={fy - 4} width="92" height="17" rx="8" fill="#3a2417" transform={`rotate(-12 ${fx} ${fy})`} />
                <rect x={fx - 46} y={fy - 4} width="92" height="17" rx="8" fill="#4a3120" transform={`rotate(13 ${fx} ${fy})`} />
                <ellipse cx={fx} cy={fy} rx="30" ry="9" fill="#ffb24d" />
                <g transform={`translate(${fx + 165},${fy - 18})`}>
                    <path d="M0,-58 L8,4 M44,-58 L36,4" stroke="#3a4a30" strokeWidth="5" strokeLinecap="round" />
                    <path d="M-4,-58 L48,-58 L40,-26 L4,-26 Z" fill="#557A2B" />
                    <path d="M4,-26 L40,-26 L44,4 L0,4 Z" fill="#6f9540" />
                    <path d="M-2,4 L14,-26 M46,4 L30,-26" stroke="#3a4a30" strokeWidth="5" strokeLinecap="round" />
                </g>
                <g transform={`translate(${fx - 150},${fy - 6})`}>
                    <ellipse cx="0" cy="0" rx="26" ry="10" fill="#5a3c24" />
                    <rect x="-26" y="0" width="52" height="22" fill="#4a3120" />
                    <ellipse cx="0" cy="22" rx="26" ry="10" fill="#3a2417" />
                    <ellipse cx="0" cy="0" rx="20" ry="7" fill="#6b4a2b" />
                </g>
                <line x1={tx + 360} y1={ty - 6} x2={tx + 360} y2={ty - 150} stroke="#5a4636" strokeWidth="5" />
                <path d={`M${tx} ${ty - 200} Q${tx + 190} ${ty - 120} ${tx + 360} ${ty - 150}`} fill="none" stroke="#3a3328" strokeWidth="2" />
            </svg>
            {Array.from({ length: 9 }).map((_, i) => {
                const u = (i + 1) / 10;
                const lx = tx + 360 * u;
                const yy = (ty - 200) * (1 - u) + (ty - 150) * u + Math.sin(u * Math.PI) * 60;
                const tw = 0.6 + 0.4 * Math.sin(t * 3 + i);
                return <div key={i} style={{ position: 'absolute', left: lx - 4, top: yy - 4, width: 8, height: 8, borderRadius: '50%', background: '#ffe49b', opacity: tw, boxShadow: '0 0 7px 2px rgba(255,220,130,.8)' }} />;
            })}
            {flame(78, 104, '#ff7a26', 0, 8)}
            {flame(52, 132, '#ff9d34', 1.6, 11)}
            {flame(30, 150, '#ffd24a', 3.0, 13)}
            {flame(14, 104, '#fff0b0', 4.3, 16)}
        </div>
    );
}

export default function BiomeHero({ wind = 1.3 }: { wind?: number }) {
    const t = useLoopTime();
    const p = scenePos(t);
    const ops = [0, 1, 2, 3].map((i) => biomeOpacity(p, i));

    const rootStyle: CSSProperties = {
        position: 'relative', width: '100%', aspectRatio: '16 / 9', overflow: 'hidden', borderRadius: 20,
        background: '#bcd97e', containerType: 'inline-size',
        boxShadow: '0 30px 60px -24px rgba(44,61,34,.5), inset 0 0 0 1px rgba(255,255,255,.25)',
    } as CSSProperties;

    return (
        <div style={rootStyle}>
            <div style={{ position: 'absolute', top: 0, left: 0, width: SW, height: SH, transformOrigin: 'top left', transform: 'scale(calc(100cqw / 1600px))' }}>
                <div style={layer(ops[0], 1)}><Meadow t={t} wind={wind} /></div>
                <div style={layer(ops[1], 1)}><Forest t={t} /></div>
                <div style={layer(ops[2], 1)}><Mountain t={t} /></div>
                <div style={layer(ops[3], 1)}><Sea t={t} /></div>
                <Clouds t={t} wind={wind} />
                <Birds t={t} />
                <Camp t={t} wind={wind} />
                <div style={layer(1, 10, { boxShadow: 'inset 0 0 150px 30px rgba(20,30,14,.16)' })} />
            </div>
        </div>
    );
}
