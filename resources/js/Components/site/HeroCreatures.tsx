import {
    type AvoidRect,
    createWorld,
    draw,
    type Layout,
    type Mood,
    moodFromLuminance,
    type Point,
    type Rect,
    setMood,
    step,
    type World,
} from '@/lib/heroCreatures';
import { RefObject, useEffect, useRef, useState } from 'react';

/**
 * Lớp canvas chim / đom đóm trên hero trang chủ (bopcamping-hus0).
 * Ảnh ngày → chim; ảnh đêm → đom đóm. Logic bay nằm ở lib/heroCreatures.
 * Bướm bay khắp trang ở lớp riêng (SiteButterflies); nó đọc `data-mood` trên canvas
 * này để biết ảnh đang là đêm mà bay đi — đừng đổi tên thuộc tính đó.
 *
 * Đặt GIỮA lớp phủ tối và lớp chữ của hero: lỡ có bay ngang thì cũng ở sau chữ,
 * và đom đóm còn né hẳn khối chữ. Chỉ để trang trí: aria-hidden, không nhận chuột.
 *
 * Không chạy khi: prefers-reduced-motion, trình duyệt thiếu IntersectionObserver,
 * hero đã cuộn khỏi màn hình, hoặc tab bị ẩn.
 */

type Props = {
    /** Slide đang hiện. Không có `mood` thì tự đoán ngày/đêm từ độ sáng ảnh. */
    slide: { src: string; mood?: Mood };
    /** Ảnh của slide đã được tải (chưa tải thì đừng tải hộ chỉ để đoán ngày/đêm). */
    slideReady: boolean;
    /** Khối đom đóm không bay vào: chữ + nút, panel, điều khiển slide. */
    avoid: RefObject<HTMLElement>[];
    /** Trong số `avoid`, khối kính mờ / trong suốt — đom đóm được bay phía sau. */
    glass?: RefObject<HTMLElement>[];
};

const PAD = 14;
const REMEASURE_SECONDS = 1;

function motionAllowed() {
    return (
        typeof window !== 'undefined' &&
        typeof IntersectionObserver === 'function' &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function layoutOf(
    host: HTMLElement,
    avoid: RefObject<HTMLElement>[],
    glass: RefObject<HTMLElement>[],
): Layout {
    const h = host.getBoundingClientRect();
    const rel = (el: HTMLElement | null): Rect | null => {
        if (!el) return null;
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return null;
        return {
            x: r.left - h.left,
            y: r.top - h.top,
            w: r.width,
            h: r.height,
        };
    };
    const glassEls = new Set(glass.map((g) => g.current));
    const rects: AvoidRect[] = [];
    for (const ref of avoid) {
        const r = rel(ref.current);
        if (!r) continue;
        rects.push({
            x: r.x - PAD,
            y: r.y - PAD,
            w: r.w + 2 * PAD,
            h: r.h + 2 * PAD,
            glass: glassEls.has(ref.current),
        });
    }
    return { width: h.width, height: h.height, avoid: rects };
}

/** Đoán ngày/đêm theo độ sáng trung bình. Chỉ đọc ảnh cùng origin (ảnh khác origin
 *  như S3 phải xin CORS → tải lại ảnh chỉ để đoán thì không đáng; coi là ảnh ngày). */
function detectMood(src: string): Promise<Mood> {
    return new Promise((resolve) => {
        let url: URL;
        try {
            url = new URL(src, window.location.href);
        } catch {
            resolve('day');
            return;
        }
        if (url.origin !== window.location.origin) {
            resolve('day');
            return;
        }
        const img = new Image();
        img.onload = () => {
            try {
                const c = document.createElement('canvas');
                c.width = c.height = 24;
                const g = c.getContext('2d', { willReadFrequently: true });
                if (!g) {
                    resolve('day');
                    return;
                }
                g.drawImage(img, 0, 0, 24, 24);
                const d = g.getImageData(0, 0, 24, 24).data;
                let sum = 0;
                for (let i = 0; i < d.length; i += 4) {
                    sum +=
                        (0.2126 * d[i] +
                            0.7152 * d[i + 1] +
                            0.0722 * d[i + 2]) /
                        255;
                }
                resolve(moodFromLuminance(sum / (d.length / 4)));
            } catch {
                resolve('day');
            }
        };
        img.onerror = () => resolve('day');
        img.src = url.href;
    });
}

const detected = new Map<string, Mood>();

export default function HeroCreatures({
    slide,
    slideReady,
    avoid,
    glass = [],
}: Props) {
    const [enabled, setEnabled] = useState(motionAllowed);
    const [, rerender] = useState(0);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const worldRef = useRef<World | null>(null);
    // Mảng ref do cha truyền có thể đổi danh tính mỗi lần render (slide đổi mỗi ~5s);
    // giữ trong ref để vòng vẽ không bị dựng lại từ đầu mỗi lần đổi ảnh.
    const avoidRef = useRef(avoid);
    const glassRef = useRef(glass);
    avoidRef.current = avoid;
    glassRef.current = glass;

    const mood: Mood = slide.mood ?? detected.get(slide.src) ?? 'day';
    const moodRef = useRef(mood);

    // Khách đổi cài đặt "giảm chuyển động" giữa chừng thì tắt/bật theo.
    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = () => setEnabled(motionAllowed());
        mq.addEventListener?.('change', onChange);
        return () => mq.removeEventListener?.('change', onChange);
    }, []);

    useEffect(() => {
        if (!enabled || slide.mood || !slideReady || detected.has(slide.src)) {
            return;
        }
        let alive = true;
        detectMood(slide.src).then((m) => {
            detected.set(slide.src, m);
            if (alive) rerender((n) => n + 1);
        });
        return () => {
            alive = false;
        };
    }, [enabled, slide.src, slide.mood, slideReady]);

    useEffect(() => {
        moodRef.current = mood;
        if (worldRef.current) setMood(worldRef.current, mood);
    }, [mood]);

    useEffect(() => {
        if (!enabled) return;
        const canvas = canvasRef.current;
        const host = canvas?.parentElement;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !host || !ctx) return;

        let layout = layoutOf(host, avoidRef.current, glassRef.current);
        const world = createWorld(layout, moodRef.current);
        worldRef.current = world;

        let raf = 0;
        let running = false;
        let onScreen = false;
        let last = 0;
        let sinceMeasure = 0;
        let pointer: Point | null = null;

        const fit = () => {
            const cap = layout.width < 640 ? 1.5 : 2;
            const dpr = Math.min(cap, window.devicePixelRatio || 1);
            canvas.width = Math.round(layout.width * dpr);
            canvas.height = Math.round(layout.height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        };
        const remeasure = () => {
            const next = layoutOf(host, avoidRef.current, glassRef.current);
            const resized =
                next.width !== layout.width || next.height !== layout.height;
            layout = next;
            if (resized) fit();
        };
        fit();

        const frame = (now: number) => {
            const dt = (now - last) / 1000;
            last = now;
            // Chữ hero trượt vào lúc mới tải, ảnh slide đổi... — đo lại định kỳ cho chắc.
            sinceMeasure += dt;
            if (sinceMeasure > REMEASURE_SECONDS) {
                sinceMeasure = 0;
                remeasure();
            }
            step(world, layout, dt, pointer);
            draw(ctx, world, layout);
            if (running) raf = requestAnimationFrame(frame);
        };
        const sync = () => {
            const want = onScreen && !document.hidden;
            if (want && !running) {
                running = true;
                last = performance.now();
                raf = requestAnimationFrame(frame);
            } else if (!want && running) {
                running = false;
                cancelAnimationFrame(raf);
            }
        };

        const io = new IntersectionObserver(([entry]) => {
            onScreen = entry.isIntersecting;
            sync();
        });
        io.observe(host);
        const ro =
            typeof ResizeObserver === 'function'
                ? new ResizeObserver(remeasure)
                : null;
        ro?.observe(host);

        const track = (e: PointerEvent) => {
            const r = host.getBoundingClientRect();
            pointer = { x: e.clientX - r.left, y: e.clientY - r.top };
        };
        const untrack = () => {
            pointer = null;
        };
        host.addEventListener('pointermove', track, { passive: true });
        host.addEventListener('pointerdown', track, { passive: true });
        host.addEventListener('pointerleave', untrack);
        document.addEventListener('visibilitychange', sync);

        return () => {
            running = false;
            cancelAnimationFrame(raf);
            io.disconnect();
            ro?.disconnect();
            host.removeEventListener('pointermove', track);
            host.removeEventListener('pointerdown', track);
            host.removeEventListener('pointerleave', untrack);
            document.removeEventListener('visibilitychange', sync);
            worldRef.current = null;
        };
    }, [enabled]);

    if (!enabled) return null;
    return (
        <canvas
            ref={canvasRef}
            aria-hidden="true"
            data-mood={mood}
            className="pointer-events-none absolute inset-0 h-full w-full"
        />
    );
}
