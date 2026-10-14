import {
    createSwarm,
    drawSwarm,
    type Perch,
    type Point,
    type Rect,
    scrollRects,
    scrollSwarm,
    setAway,
    type Sky,
    stepSwarm,
} from '@/lib/butterflies';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Bướm bay khắp các trang khách (bopcamping-hus0). Logic bay ở lib/butterflies.
 *
 * Canvas cố định phủ màn hình, nằm TRÊN nội dung nhưng DƯỚI header (z-50), menu điện
 * thoại (z-40), nút nổi và hộp thoại. Không nhận chuột — khách bấm xuyên qua.
 * Gắn trong SiteLayout (layout bền của Inertia) nên chuyển trang bướm vẫn bay tiếp.
 *
 * - Né hẳn: khối có `data-butterfly-avoid` đang trên màn hình + ô khách đang gõ.
 * - Lượn tránh: nút, link, ô nhập nhỏ (CONTROL) — khách sắp bấm thì không có bướm đậu đó.
 * - Đậu: mép trên khối có `data-butterfly-perch` (thẻ sản phẩm, combo, panel đầu trang).
 * - Bay đi hết: trang giỏ hàng / thanh toán; hoặc ảnh đầu trang chủ đang là ảnh đêm
 *   (đọc `data-mood` trên canvas của HeroCreatures) và còn chiếm quá nửa màn hình.
 * - Không chạy khi prefers-reduced-motion; tạm dừng khi tab ẩn.
 */

/** Trang không có bướm: khách đang điền thông tin và đặt đơn thì đừng làm phân tâm. */
export const NO_BUTTERFLY_PAGES = new Set(['Cart', 'Admin']);

const PAD = 12;
const FIELD_PAD = 40;
const REMEASURE_SECONDS = 0.5;
const FIELD = 'input, textarea, select, [contenteditable="true"]';
const CONTROL = 'button, a[href], input, select, textarea, [role="button"]';
/** Khối bấm được mà to hơn ngần này (cả thẻ sản phẩm là một link) thì không tính là
 *  "nút" — tránh cả thẻ thì trang danh sách hết chỗ bay. */
const CONTROL_MAX_AREA = 60000;

const perchIds = new WeakMap<Element, string>();
let nextPerchId = 0;
function perchId(el: Element) {
    let id = perchIds.get(el);
    if (!id) {
        id = `perch-${++nextPerchId}`;
        perchIds.set(el, id);
    }
    return id;
}

function motionAllowed() {
    return (
        typeof window !== 'undefined' &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function measureSky(): Sky {
    const W = document.documentElement.clientWidth;
    const H = document.documentElement.clientHeight;
    const headerBottom =
        document.querySelector('header')?.getBoundingClientRect().bottom ?? 0;
    const top = Math.max(0, Math.min(H / 3, headerBottom));
    const visible = (r: DOMRect) =>
        r.width > 0 &&
        r.height > 0 &&
        r.bottom > top &&
        r.top < H &&
        r.right > 0 &&
        r.left < W;

    const avoid: Rect[] = [];
    document
        .querySelectorAll<HTMLElement>('[data-butterfly-avoid]')
        .forEach((el) => {
            const r = el.getBoundingClientRect();
            if (!visible(r)) return;
            // Khối cho đậu thì vùng cấm bắt đầu đúng mép trên — chỗ ngồi nằm ngoài vùng cấm.
            const t = el.hasAttribute('data-butterfly-perch') ? 0 : PAD;
            avoid.push({
                x: r.left - PAD,
                y: r.top - t,
                w: r.width + 2 * PAD,
                h: r.height + PAD + t,
            });
        });
    const active = document.activeElement;
    if (active instanceof HTMLElement && active.matches(FIELD)) {
        const r = active.getBoundingClientRect();
        if (visible(r)) {
            avoid.push({
                x: r.left - FIELD_PAD,
                y: r.top - FIELD_PAD,
                w: r.width + 2 * FIELD_PAD,
                h: r.height + 2 * FIELD_PAD,
            });
        }
    }

    const perches: Perch[] = [];
    document
        .querySelectorAll<HTMLElement>('[data-butterfly-perch]')
        .forEach((el) => {
            const r = el.getBoundingClientRect();
            // Chỉ đậu trên mép đang nhìn thấy, nằm dưới header.
            if (r.top < top + 20 || r.top > H - 20) return;
            const x0 = Math.max(r.left + 24, 8);
            const x1 = Math.min(r.right - 24, W - 8);
            if (x1 - x0 < 40) return;
            perches.push({ id: perchId(el), y: r.top, x0, x1 });
        });
    return { width: W, height: H, top, avoid, perches };
}

/** Nút / link / ô nhập nhỏ đang trên màn hình. Đọc cả trang nên chỉ đo mỗi nửa giây;
 *  giữa hai lần đo thì dời theo cuộn (scrollRects). */
function measureControls(sky: Sky): Rect[] {
    const out: Rect[] = [];
    document.querySelectorAll<HTMLElement>(CONTROL).forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) return;
        if (r.width * r.height > CONTROL_MAX_AREA) return;
        if (r.bottom < sky.top || r.top > sky.height) return;
        if (r.right < 0 || r.left > sky.width) return;
        out.push({ x: r.left, y: r.top, w: r.width, h: r.height });
    });
    return out;
}

/** Ảnh đầu trang chủ đang là ảnh đêm và còn chiếm quá nửa màn hình. */
function heroIsDark(H: number) {
    const hero = document.querySelector<HTMLElement>('[data-hero-slideshow]');
    if (!hero) return false;
    const mood =
        hero.querySelector<HTMLElement>('canvas[data-mood]')?.dataset.mood;
    if (mood !== 'night') return false;
    const r = hero.getBoundingClientRect();
    const shown = Math.max(0, Math.min(r.bottom, H) - Math.max(r.top, 0));
    return shown > H * 0.5;
}

export default function SiteButterflies() {
    const { component } = usePage();
    const [enabled, setEnabled] = useState(motionAllowed);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const pageRef = useRef(component);
    pageRef.current = component;

    // Khách đổi cài đặt "giảm chuyển động" giữa chừng thì tắt/bật theo.
    useEffect(() => {
        const mq = window.matchMedia('(prefers-reduced-motion: reduce)');
        const onChange = () => setEnabled(motionAllowed());
        mq.addEventListener?.('change', onChange);
        return () => mq.removeEventListener?.('change', onChange);
    }, []);

    useEffect(() => {
        if (!enabled) return;
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !ctx) return;

        let sky = measureSky();
        const shouldBeAway = () =>
            NO_BUTTERFLY_PAGES.has(pageRef.current) || heroIsDark(sky.height);
        const swarm = createSwarm(sky, Math.random, shouldBeAway());

        let raf = 0;
        let running = false;
        let last = 0;
        let sinceMeasure = 0;
        let dirty = false;
        let pointer: Point | null = null;
        let lastScrollY = window.scrollY;

        const fit = () => {
            const cap = sky.width < 640 ? 1.5 : 2;
            const dpr = Math.min(cap, window.devicePixelRatio || 1);
            canvas.width = Math.round(sky.width * dpr);
            canvas.height = Math.round(sky.height * dpr);
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        };
        let soft = measureControls(sky);
        sky = { ...sky, soft };
        const remeasure = (controlsToo: boolean) => {
            const next = measureSky();
            const resized =
                next.width !== sky.width || next.height !== sky.height;
            if (controlsToo) soft = measureControls(next);
            sky = { ...next, soft };
            if (resized) fit();
            setAway(swarm, sky, shouldBeAway());
        };
        fit();

        // Bay đi hết rồi thì ngừng vẽ; đồng hồ chậm bên dưới sẽ gọi dậy khi cần.
        const idle = () => swarm.away && swarm.flies.length === 0;
        const frame = (now: number) => {
            const dt = (now - last) / 1000;
            last = now;
            sinceMeasure += dt;
            if (sinceMeasure > REMEASURE_SECONDS) {
                dirty = false;
                sinceMeasure = 0;
                remeasure(true);
            } else if (dirty) {
                dirty = false;
                remeasure(false);
            }
            stepSwarm(swarm, sky, dt, pointer);
            drawSwarm(ctx, swarm, sky);
            if (idle()) {
                running = false;
                return;
            }
            if (running) raf = requestAnimationFrame(frame);
        };
        const wake = () => {
            if (running || document.hidden || idle()) return;
            running = true;
            last = performance.now();
            raf = requestAnimationFrame(frame);
        };
        const sleep = () => {
            running = false;
            cancelAnimationFrame(raf);
        };
        const onVisibility = () => (document.hidden ? sleep() : wake());
        const idleCheck = window.setInterval(() => {
            if (running) return;
            remeasure(true);
            wake();
        }, 1000);

        const track = (e: PointerEvent) => {
            pointer = { x: e.clientX, y: e.clientY };
        };
        // Ngón tay nhấc lên thì thôi; chuột thì chỉ khi rời hẳn cửa sổ.
        const lift = (e: PointerEvent) => {
            if (e.pointerType !== 'mouse') pointer = null;
        };
        const leave = (e: PointerEvent) => {
            if (!e.relatedTarget) pointer = null;
        };
        const onScroll = () => {
            const dy = window.scrollY - lastScrollY;
            lastScrollY = window.scrollY;
            scrollSwarm(swarm, dy);
            scrollRects(soft, dy);
            dirty = true;
        };
        const onResize = () => {
            dirty = true;
        };
        // Khách bấm vào ô nhập: bướm né ô đó ngay, không đợi lượt đo kế tiếp.
        const onFocus = () => {
            dirty = true;
        };

        window.addEventListener('pointermove', track, { passive: true });
        window.addEventListener('pointerdown', track, { passive: true });
        window.addEventListener('pointerup', lift, { passive: true });
        window.addEventListener('pointercancel', lift, { passive: true });
        document.addEventListener('pointerout', leave, { passive: true });
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onResize, { passive: true });
        document.addEventListener('focusin', onFocus);
        document.addEventListener('visibilitychange', onVisibility);
        wake();

        return () => {
            sleep();
            window.clearInterval(idleCheck);
            window.removeEventListener('pointermove', track);
            window.removeEventListener('pointerdown', track);
            window.removeEventListener('pointerup', lift);
            window.removeEventListener('pointercancel', lift);
            document.removeEventListener('pointerout', leave);
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onResize);
            document.removeEventListener('focusin', onFocus);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [enabled]);

    if (!enabled) return null;
    return (
        <canvas
            ref={canvasRef}
            aria-hidden="true"
            data-butterflies
            className="pointer-events-none fixed inset-0 z-30 h-full w-full"
        />
    );
}
