import {
    between,
    insideRect,
    type Point,
    type Rect,
    type Sky,
} from '@/lib/butterflies';

/**
 * Chim và đom đóm bay khắp các trang khách, mọi lúc (bopcamping-ff1y). Bướm ở
 * lib/butterflies; cả ba vẽ chung một canvas — xem SiteCreatures.
 *
 * Phỏng theo bản trên hero (bopcamping-hus0, chưa từng lên production) nhưng bỏ hẳn
 * chuyện ngày/đêm: chủ shop muốn cả ba loại bay ở mọi trang, mọi lúc.
 *
 * Chỉ có mô phỏng + vẽ, không đụng React — test được bằng rng cố định. Toạ độ là px
 * CSS trong khung nhìn (canvas cố định phủ màn hình), dùng chung `Sky` với bướm.
 *
 * Luật UX — đừng nới khi sửa:
 * - Chim chỉ bay ngang dải trời phía trên (dưới header), từng đàn nhỏ, tối đa 2 đàn.
 *   Đi ngang khối chữ / panel (`avoid`) thì gần như trong suốt — như bay phía sau,
 *   không đè lên chữ khách đang đọc.
 * - Đom đóm không bao giờ vào vùng `avoid` (khối chữ đầu trang, ô khách đang gõ);
 *   né con trỏ. Ít con (COUNTS), điện thoại ít hơn.
 * - Đom đóm phải nhìn thấy được trên nền be sáng của site, không chỉ trên ảnh tối:
 *   vẽ màu vàng chanh đậm, không dùng chế độ hoà sáng ('lighter') vốn biến mất trên nền sáng.
 */

export type { Point, Rect, Sky };

type Bird = {
    ox: number;
    oy: number;
    ph: number;
    freq: number;
    size: number;
    amp: number;
    flapping: boolean;
    t: number;
};
export type Flock = {
    x: number;
    y: number;
    dir: 1 | -1;
    speed: number;
    alpha: number;
    t: number;
    birds: Bird[];
};
export type Firefly = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    t: number;
    on: number;
    off: number;
    size: number;
};

export type World = {
    rng: () => number;
    small: boolean;
    flocks: Flock[];
    nextFlock: number;
    fireflies: Firefly[];
};

const TAU = Math.PI * 2;
const SMALL_SCREEN = 640;

export const COUNTS = {
    fireflies: { desktop: 10, mobile: 6 },
    /** Số đàn chim cùng lúc trên màn hình. */
    flocks: 2,
} as const;

/** Khoảng cách giữa hai đàn chim (giây). */
export const FLOCK_GAP: [number, number] = [6, 11];

/** Dải trời cho chim: phần trên của vùng bay, ngay dưới header. */
export function birdBand(sky: Sky): [number, number] {
    const h = sky.height - sky.top;
    return [sky.top + h * 0.06, sky.top + h * 0.32];
}

function freeSpot(world: World, sky: Sky): Point {
    let p: Point = { x: sky.width / 2, y: sky.height / 2 };
    for (let i = 0; i < 40; i++) {
        p = {
            x: between(world.rng, 20, sky.width - 20),
            y: between(world.rng, sky.top + 30, sky.height - 20),
        };
        if (!sky.avoid.some((r) => insideRect(p, r, 14))) break;
    }
    return p;
}

function makeFirefly(world: World, sky: Sky): Firefly {
    return {
        ...freeSpot(world, sky),
        vx: between(world.rng, -8, 8),
        vy: between(world.rng, -8, 8),
        t: between(world.rng, 0, 4),
        on: between(world.rng, 0.8, 1.6),
        off: between(world.rng, 0.8, 2.6),
        size: between(world.rng, 2.2, 3),
    };
}

export function createWorld(sky: Sky, rng: () => number = Math.random): World {
    const small = sky.width < SMALL_SCREEN;
    const world: World = {
        rng,
        small,
        flocks: [],
        // Đàn đầu tiên xuất hiện sớm để khách mở trang là thấy.
        nextFlock: between(rng, 0.6, 1.6),
        fireflies: [],
    };
    const n = COUNTS.fireflies[small ? 'mobile' : 'desktop'];
    world.fireflies = Array.from({ length: n }, () => makeFirefly(world, sky));
    return world;
}

/* ── Chim: từng đàn nhỏ bay ngang dải trời phía trên ── */

function spawnFlock(world: World, sky: Sky) {
    const { rng } = world;
    const dir: 1 | -1 = rng() < 0.5 ? 1 : -1;
    const n = 3 + Math.floor(rng() * (world.small ? 3 : 5));
    const depth = between(rng, 0.6, 1);
    const size = (world.small ? 7 : 10) * depth;
    const birds: Bird[] = [];
    for (let i = 0; i < n; i++) {
        const k = Math.ceil(i / 2) * (i % 2 ? 1 : -1);
        birds.push({
            ox: -dir * Math.abs(k) * size * 3.2 + between(rng, -4, 4),
            oy: k * size * 1.5 + between(rng, -3, 3),
            ph: between(rng, 0, TAU),
            freq: between(rng, 7, 9),
            size: size * between(rng, 0.85, 1.15),
            amp: 1,
            flapping: true,
            t: between(rng, 1, 3),
        });
    }
    const [y0, y1] = birdBand(sky);
    world.flocks.push({
        x: dir > 0 ? -80 : sky.width + 80,
        y: between(rng, y0, y1),
        dir,
        speed: (world.small ? 34 : 48) * (0.8 + depth * 0.5),
        alpha: 0.45 + depth * 0.4,
        t: 0,
        birds,
    });
}

function stepBirds(world: World, sky: Sky, dt: number) {
    world.nextFlock -= dt;
    if (world.nextFlock <= 0 && world.flocks.length < COUNTS.flocks) {
        spawnFlock(world, sky);
        world.nextFlock = between(world.rng, ...FLOCK_GAP);
    }
    world.flocks = world.flocks.filter((f) => {
        f.t += dt;
        f.x += f.dir * f.speed * dt;
        f.y += Math.sin(f.t * 0.6) * 6 * dt;
        for (const b of f.birds) {
            b.t -= dt;
            if (b.t <= 0) {
                b.flapping = !b.flapping;
                b.t = b.flapping
                    ? between(world.rng, 1.4, 3)
                    : between(world.rng, 0.7, 1.5);
            }
            b.amp += ((b.flapping ? 1 : 0.12) - b.amp) * Math.min(1, dt * 3);
            b.ph += b.freq * dt * (0.35 + 0.65 * b.amp);
        }
        const tail =
            f.x +
            f.birds.reduce(
                (m, b) => (f.dir > 0 ? Math.min(m, b.ox) : Math.max(m, b.ox)),
                0,
            );
        return f.dir > 0 ? tail < sky.width + 60 : tail > -60;
    });
}

/* ── Đom đóm: trôi chậm, nhấp nháy lệch nhịp ── */

export function fireflyBrightness(f: Pick<Firefly, 't' | 'on' | 'off'>) {
    const phase = f.t % (f.on + f.off);
    return phase < f.on ? Math.sin((Math.PI * phase) / f.on) : 0;
}

function stepFireflies(
    world: World,
    sky: Sky,
    dt: number,
    pointer: Point | null,
) {
    const { width: W, height: H, top } = sky;
    for (const f of world.fireflies) {
        f.t += dt;
        f.vx += (world.rng() - 0.5) * 40 * dt;
        f.vy += (world.rng() - 0.5) * 40 * dt;
        if (f.y < top + 20) f.vy += 30 * dt;
        if (f.y > H - 10) f.vy -= 30 * dt;
        if (f.x < 10) f.vx += 30 * dt;
        if (f.x > W - 10) f.vx -= 30 * dt;
        for (const r of sky.avoid) {
            if (!insideRect(f, r, 14)) continue;
            const dx = f.x - (r.x + r.w / 2);
            const dy = f.y - (r.y + r.h / 2);
            const d = Math.hypot(dx, dy) || 1;
            f.vx += (dx / d) * 80 * dt;
            f.vy += (dy / d) * 80 * dt;
        }
        if (pointer) {
            const dx = f.x - pointer.x;
            const dy = f.y - pointer.y;
            const d = Math.hypot(dx, dy);
            if (d < 90 && d > 0) {
                f.vx += (dx / d) * 40 * dt;
                f.vy += (dy / d) * 40 * dt;
            }
        }
        f.vx *= 1 - 0.6 * dt;
        f.vy *= 1 - 0.6 * dt;
        const sp = Math.hypot(f.vx, f.vy);
        if (sp > 22) {
            f.vx *= 22 / sp;
            f.vy *= 22 / sp;
        }
        const nx = f.x + f.vx * dt;
        const ny = f.y + f.vy * dt;
        // Ràng buộc cứng như bướm: bước nào lọt vào vùng cấm thì bỏ phần đi vào.
        let x = nx;
        let y = ny;
        for (const r of sky.avoid) {
            if (!insideRect({ x, y }, r) || insideRect(f, r)) continue;
            if (!insideRect({ x, y: f.y }, r)) y = f.y;
            else if (!insideRect({ x: f.x, y }, r)) x = f.x;
            else {
                x = f.x;
                y = f.y;
            }
        }
        f.x = x;
        f.y = y;
    }
}

/** Vùng cấm mới xuất hiện ngay chỗ đom đóm đang ở (khách vừa bấm vào ô nhập,
 *  trang vừa đổi) thì cho con đó sang chỗ trống — không để nó kẹt trong vùng cấm. */
function evictFromAvoid(world: World, sky: Sky) {
    for (const f of world.fireflies) {
        if (sky.avoid.some((r) => insideRect(f, r))) {
            Object.assign(f, freeSpot(world, sky));
        }
    }
}

export function step(
    world: World,
    sky: Sky,
    dt: number,
    pointer: Point | null,
) {
    const t = Math.min(0.05, Math.max(0, dt));
    stepBirds(world, sky, t);
    evictFromAvoid(world, sky);
    stepFireflies(world, sky, t, pointer);
}

/** Cửa sổ đổi cỡ: đom đóm nằm ngoài khung mới thì đưa về chỗ trống. */
export function fitWorld(world: World, sky: Sky) {
    world.small = sky.width < SMALL_SCREEN;
    for (const f of world.fireflies) {
        if (f.x < 0 || f.x > sky.width || f.y < sky.top || f.y > sky.height) {
            Object.assign(f, freeSpot(world, sky));
        }
    }
}

/* ── Vẽ (không xoá canvas — SiteCreatures xoá một lần cho cả ba loại) ── */

function drawBird(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    b: Bird,
    alpha: number,
) {
    const s = b.size;
    const up = Math.sin(b.ph) * b.amp;
    const tipY = -s * 0.6 * up + s * 0.1;
    const ctrlY = -s * 0.45 * up - s * 0.15;
    ctx.strokeStyle = `rgba(18,24,12,${alpha})`;
    ctx.fillStyle = ctx.strokeStyle;
    ctx.lineWidth = Math.max(1.2, s * 0.16);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(x - s, y + tipY);
    ctx.quadraticCurveTo(x - s * 0.45, y + ctrlY, x, y);
    ctx.quadraticCurveTo(x + s * 0.45, y + ctrlY, x + s, y + tipY);
    ctx.stroke();
    ctx.beginPath();
    ctx.ellipse(x, y + s * 0.04, s * 0.16, s * 0.08, 0, 0, TAU);
    ctx.fill();
}

function drawFirefly(ctx: CanvasRenderingContext2D, f: Firefly) {
    // Lúc "tắt" vẫn còn một đốm mờ: đom đóm không biến mất hẳn giữa trời.
    const b = 0.18 + 0.82 * fireflyBrightness(f);
    const r = f.size * (5 + 5 * b);
    const g = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, r);
    g.addColorStop(0, `rgba(226,240,80,${0.9 * b})`);
    g.addColorStop(0.35, `rgba(190,214,40,${0.45 * b})`);
    g.addColorStop(1, 'rgba(190,214,40,0)');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(f.x, f.y, r, 0, TAU);
    ctx.fill();
    // Thân nhỏ viền xanh rêu — nhìn ra được cả trên nền be sáng.
    ctx.fillStyle = `rgba(248,255,170,${0.55 + 0.45 * b})`;
    ctx.strokeStyle = `rgba(92,112,24,${0.35 + 0.35 * b})`;
    ctx.lineWidth = 0.8;
    ctx.beginPath();
    ctx.arc(f.x, f.y, f.size, 0, TAU);
    ctx.fill();
    ctx.stroke();
}

/** Độ mờ của chim đang đi ngang vùng `avoid` (khối chữ, panel). */
export const BIRD_BEHIND_ALPHA = 0.12;

export function birdAlpha(p: Point, sky: Sky, alpha: number) {
    return sky.avoid.some((r) => insideRect(p, r, 6))
        ? alpha * BIRD_BEHIND_ALPHA
        : alpha;
}

export function draw(ctx: CanvasRenderingContext2D, world: World, sky: Sky) {
    for (const f of world.flocks) {
        for (const b of f.birds) {
            const p = { x: f.x + b.ox, y: f.y + b.oy };
            drawBird(ctx, p.x, p.y, b, birdAlpha(p, sky, f.alpha));
        }
    }
    for (const f of world.fireflies) drawFirefly(ctx, f);
}
