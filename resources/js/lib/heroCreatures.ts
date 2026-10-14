import { between, insideRect, type Point, type Rect } from '@/lib/butterflies';

/**
 * Chim và đom đóm bay trên hero trang chủ (bopcamping-hus0). Bướm thì bay khắp
 * trang nên ở lớp riêng — xem lib/butterflies + SiteButterflies.
 *
 * File này chỉ có mô phỏng + vẽ lên canvas, không đụng React — để test được bằng
 * rng cố định. Toạ độ là px CSS trong khung hero (gốc = góc trên-trái hero).
 *
 * Luật UX — đừng nới khi sửa:
 * - Ảnh ngày: chim. Ảnh đêm: đom đóm. Đổi ảnh thì lớp cũ mờ dần, không biến mất cái rụp.
 * - Đom đóm không bay vào khối chữ; được bay sau khối kính (`glass`).
 * - Ít con: điện thoại còn ít hơn nữa (xem COUNTS).
 */

export type { Point, Rect };
export type Mood = 'day' | 'night';
/** `glass`: khối trong suốt/kính mờ (panel, dải điều khiển). Lớp hero nằm SAU nội
 *  dung nên đom đóm bay phía sau kính chỉ thành đốm sáng nhoè — được phép.
 *  Khối chữ thì không có nền, ánh sáng sau chữ làm khó đọc — luôn né. */
export type AvoidRect = Rect & { glass?: boolean };
export type Layout = {
    width: number;
    height: number;
    avoid: AvoidRect[];
};

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
type Flock = {
    x: number;
    y: number;
    dir: 1 | -1;
    speed: number;
    alpha: number;
    t: number;
    birds: Bird[];
};
type Firefly = {
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
    mood: Mood;
    mobile: boolean;
    flocks: Flock[];
    nextFlock: number;
    fireflies: Firefly[];
    /** 0 = ban ngày, 1 = ban đêm; chạy từ từ giữa hai đầu để đom đóm hiện/tắt êm. */
    night: number;
};

const TAU = Math.PI * 2;
const MOBILE_MAX_WIDTH = 640;
/** Dưới ngưỡng này (độ sáng trung bình 0..1) coi là ảnh đêm. Đo trên 7 ảnh album:
 *  đêm 0.19–0.27, hoàng hôn 0.36, ngày ≥ 0.49. */
export const NIGHT_LUMINANCE = 0.31;

export const COUNTS = {
    fireflies: { desktop: 16, mobile: 9 },
} as const;

export function moodFromLuminance(luminance: number): Mood {
    return luminance < NIGHT_LUMINANCE ? 'night' : 'day';
}

function makeFirefly(world: World, layout: Layout): Firefly {
    let p: Point = { x: 0, y: 0 };
    for (let i = 0; i < 40; i++) {
        p = {
            x: between(world.rng, 10, layout.width - 10),
            y: between(world.rng, layout.height * 0.3, layout.height * 0.95),
        };
        if (!layout.avoid.some((r) => !r.glass && insideRect(p, r, 10))) break;
    }
    return {
        ...p,
        vx: between(world.rng, -8, 8),
        vy: between(world.rng, -8, 8),
        t: between(world.rng, 0, 4),
        on: between(world.rng, 0.6, 1.4),
        off: between(world.rng, 1, 3.5),
        size: between(world.rng, 1.5, 2.4),
    };
}

export function createWorld(
    layout: Layout,
    mood: Mood,
    rng: () => number = Math.random,
): World {
    const mobile = layout.width < MOBILE_MAX_WIDTH;
    const world: World = {
        rng,
        mood,
        mobile,
        flocks: [],
        nextFlock: 1,
        fireflies: [],
        night: mood === 'night' ? 1 : 0,
    };
    const f = COUNTS.fireflies[mobile ? 'mobile' : 'desktop'];
    world.fireflies = Array.from({ length: f }, () =>
        makeFirefly(world, layout),
    );
    return world;
}

export function setMood(world: World, mood: Mood) {
    if (world.mood === mood) return;
    world.mood = mood;
    if (mood === 'day') world.nextFlock = between(world.rng, 0.6, 1.4);
}

/* ── Chim: từng đàn nhỏ bay ngang dải trời phía trên ── */

function spawnFlock(world: World, layout: Layout) {
    const { rng } = world;
    const dir: 1 | -1 = rng() < 0.5 ? 1 : -1;
    const n = 3 + Math.floor(rng() * (world.mobile ? 3 : 5));
    const depth = between(rng, 0.6, 1);
    const size = (world.mobile ? 7 : 10) * depth;
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
    world.flocks.push({
        x: dir > 0 ? -80 : layout.width + 80,
        y: layout.height * between(rng, 0.08, 0.28),
        dir,
        speed: (world.mobile ? 34 : 48) * (0.8 + depth * 0.5),
        alpha: 0.45 + depth * 0.4,
        t: 0,
        birds,
    });
}

function stepBirds(world: World, layout: Layout, dt: number) {
    if (world.mood === 'day') {
        world.nextFlock -= dt;
        if (world.nextFlock <= 0 && world.flocks.length < 2) {
            spawnFlock(world, layout);
            world.nextFlock = between(world.rng, 6, 11);
        }
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
        return f.dir > 0 ? tail < layout.width + 60 : tail > -60;
    });
}

/* ── Đom đóm: trôi chậm, nhấp nháy lệch nhịp, tụ ở nửa dưới ── */

export function fireflyBrightness(f: Pick<Firefly, 't' | 'on' | 'off'>) {
    const phase = f.t % (f.on + f.off);
    return phase < f.on ? Math.sin((Math.PI * phase) / f.on) : 0;
}

function stepFireflies(
    world: World,
    layout: Layout,
    dt: number,
    pointer: Point | null,
) {
    const { width: W, height: H } = layout;
    for (const f of world.fireflies) {
        f.t += dt;
        f.vx += (world.rng() - 0.5) * 40 * dt;
        f.vy += (world.rng() - 0.5) * 40 * dt;
        if (f.y < H * 0.25) f.vy += 20 * dt;
        if (f.y > H * 0.97) f.vy -= 20 * dt;
        if (f.x < 10) f.vx += 20 * dt;
        if (f.x > W - 10) f.vx -= 20 * dt;
        for (const r of layout.avoid) {
            if (r.glass || !insideRect(f, r, 12)) continue;
            const dx = f.x - (r.x + r.w / 2);
            const dy = f.y - (r.y + r.h / 2);
            const d = Math.hypot(dx, dy) || 1;
            f.vx += (dx / d) * 60 * dt;
            f.vy += (dy / d) * 60 * dt;
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
        f.x += f.vx * dt;
        f.y += f.vy * dt;
    }
}

export function step(
    world: World,
    layout: Layout,
    dt: number,
    pointer: Point | null,
) {
    const t = Math.min(0.05, Math.max(0, dt));
    stepBirds(world, layout, t);
    world.night +=
        ((world.mood === 'night' ? 1 : 0) - world.night) * Math.min(1, t * 1.5);
    if (world.night > 0.01) stepFireflies(world, layout, t, pointer);
}

/* ── Vẽ ── */

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

function drawFireflies(ctx: CanvasRenderingContext2D, world: World) {
    ctx.save();
    ctx.globalCompositeOperation = 'lighter';
    for (const f of world.fireflies) {
        const b = fireflyBrightness(f) * world.night;
        if (b < 0.02) continue;
        const r = f.size * (6 + 5 * b);
        const g = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, r);
        g.addColorStop(0, `rgba(250,255,205,${0.9 * b})`);
        g.addColorStop(0.25, `rgba(205,236,110,${0.45 * b})`);
        g.addColorStop(1, 'rgba(205,236,110,0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(f.x, f.y, r, 0, TAU);
        ctx.fill();
        ctx.fillStyle = `rgba(255,255,230,${b})`;
        ctx.beginPath();
        ctx.arc(f.x, f.y, f.size * 0.9, 0, TAU);
        ctx.fill();
    }
    ctx.restore();
}

export function draw(
    ctx: CanvasRenderingContext2D,
    world: World,
    layout: Layout,
) {
    ctx.clearRect(0, 0, layout.width, layout.height);
    // Đàn chim đang bay dở lúc đổi sang ảnh đêm thì mờ dần theo trời tối.
    const dusk = 1 - world.night;
    if (dusk > 0.01) {
        for (const f of world.flocks) {
            for (const b of f.birds)
                drawBird(ctx, f.x + b.ox, f.y + b.oy, b, f.alpha * dusk);
        }
    }
    if (world.night > 0.01) drawFireflies(ctx, world);
}
