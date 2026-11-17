import {
    between,
    insideRect,
    type Point,
    type Rect,
    type Sky,
} from '@/lib/butterflies';

/**
 * Chim bay khắp các trang khách, mọi lúc (bopcamping-ff1y); đom đóm chỉ trên banner
 * đầu trang chủ (bopcamping-k5og). Bướm ở lib/butterflies; cả ba vẽ chung một canvas
 * — xem SiteCreatures.
 *
 * Chỉ có mô phỏng + vẽ, không đụng React — test được bằng rng cố định. Chim dùng toạ
 * độ khung nhìn (canvas cố định phủ màn hình). Đom đóm dùng toạ độ TRONG banner (gốc =
 * góc trên-trái banner), nên cuộn trang thì đom đóm trôi theo banner, khuất theo banner.
 *
 * Luật UX — đừng nới khi sửa:
 * - Chim chỉ bay ngang dải trời phía trên (dưới header), từng đàn nhỏ, tối đa 2 đàn.
 *   Đi ngang khối chữ / panel (`avoid`) thì gần như trong suốt — như bay phía sau,
 *   không đè lên chữ khách đang đọc.
 * - Đom đóm chỉ trong banner, không bao giờ vẽ trong vùng `avoid` (khối chữ, panel,
 *   dải điều khiển của banner); né con trỏ. Trang không có banner thì không có đom đóm.
 * - Vẽ màu vàng chanh có viền, không dùng chế độ hoà sáng ('lighter') — để vẫn thấy
 *   được ở mép banner sáng.
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
    /** Toạ độ trong banner. Rỗng cho tới khi gặp banner lần đầu. */
    fireflies: Firefly[];
    /** Cỡ banner lúc rải đom đóm — banner đổi cỡ thì co giãn vị trí theo. */
    field: { w: number; h: number } | null;
};

/** Khung bay của đom đóm: banner, toạ độ tính từ góc trên-trái của nó. */
type Field = { w: number; h: number; avoid: Rect[] };

const TAU = Math.PI * 2;
const SMALL_SCREEN = 640;

export const COUNTS = {
    /** Trong banner trang chủ (như bản thiết kế gốc bopcamping-hus0). */
    fireflies: { desktop: 16, mobile: 9 },
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

/** Chỗ trống trong banner, tránh vùng cấm. Hết chỗ (banner bị chữ phủ kín) → null. */
function freeSpot(world: World, field: Field): Point | null {
    for (let i = 0; i < 40; i++) {
        const p = {
            x: between(world.rng, 10, field.w - 10),
            y: between(world.rng, field.h * 0.12, field.h - 10),
        };
        if (!field.avoid.some((r) => insideRect(p, r, 14))) return p;
    }
    return null;
}

function makeFirefly(world: World, field: Field): Firefly {
    return {
        ...(freeSpot(world, field) ?? { x: field.w / 2, y: field.h * 0.8 }),
        vx: between(world.rng, -8, 8),
        vy: between(world.rng, -8, 8),
        t: between(world.rng, 0, 4),
        on: between(world.rng, 0.8, 1.6),
        off: between(world.rng, 0.8, 2.6),
        size: between(world.rng, 2.2, 3),
    };
}

/** Banner đang trên trang → khung bay của đom đóm (vùng cấm đổi sang toạ độ banner). */
export function bannerField(sky: Sky): Field | null {
    const b = sky.banner;
    if (!b || b.w <= 0 || b.h <= 0) return null;
    return {
        w: b.w,
        h: b.h,
        avoid: sky.avoid.map((r) => ({ ...r, x: r.x - b.x, y: r.y - b.y })),
    };
}

export function createWorld(sky: Sky, rng: () => number = Math.random): World {
    return {
        rng,
        small: sky.width < SMALL_SCREEN,
        flocks: [],
        // Đàn đầu tiên xuất hiện sớm để khách mở trang là thấy.
        nextFlock: between(rng, 0.6, 1.6),
        fireflies: [],
        field: null,
    };
}

/** Gặp banner lần đầu thì rải đom đóm; banner đổi cỡ thì co giãn vị trí theo. */
function ensureFireflies(world: World, field: Field) {
    if (!world.field) {
        const n = COUNTS.fireflies[world.small ? 'mobile' : 'desktop'];
        world.fireflies = Array.from({ length: n }, () =>
            makeFirefly(world, field),
        );
    } else if (world.field.w !== field.w || world.field.h !== field.h) {
        const kx = field.w / world.field.w;
        const ky = field.h / world.field.h;
        for (const f of world.fireflies) {
            f.x *= kx;
            f.y *= ky;
        }
    }
    world.field = { w: field.w, h: field.h };
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
    field: Field,
    dt: number,
    pointer: Point | null,
) {
    const { w: W, h: H, avoid } = field;
    for (const f of world.fireflies) {
        f.t += dt;
        f.vx += (world.rng() - 0.5) * 40 * dt;
        f.vy += (world.rng() - 0.5) * 40 * dt;
        if (f.y < H * 0.12) f.vy += 30 * dt;
        if (f.y > H - 10) f.vy -= 30 * dt;
        if (f.x < 10) f.vx += 30 * dt;
        if (f.x > W - 10) f.vx -= 30 * dt;
        for (const r of avoid) {
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
        // Ràng buộc cứng như bướm: bước nào lọt vào vùng cấm thì bỏ phần đi vào.
        let x = f.x + f.vx * dt;
        let y = f.y + f.vy * dt;
        for (const r of avoid) {
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

/** Vùng cấm mới đè lên chỗ đom đóm (banner đổi bố cục) thì cho con đó sang chỗ trống. */
function evictFromAvoid(world: World, field: Field) {
    for (const f of world.fireflies) {
        if (!field.avoid.some((r) => insideRect(f, r))) continue;
        const p = freeSpot(world, field);
        if (p) Object.assign(f, p);
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
    const field = bannerField(sky);
    // Trang không có banner: đom đóm đứng yên chờ (không vẽ), về lại trang chủ bay tiếp.
    if (!field || !sky.banner) return;
    ensureFireflies(world, field);
    evictFromAvoid(world, field);
    const local = pointer && {
        x: pointer.x - sky.banner.x,
        y: pointer.y - sky.banner.y,
    };
    stepFireflies(world, field, t, local);
}

/** Cửa sổ đổi cỡ: cập nhật điện thoại/máy tính (cỡ banner tự co giãn ở step). */
export function fitWorld(world: World, sky: Sky) {
    world.small = sky.width < SMALL_SCREEN;
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
    drawFireflies(ctx, world, sky);
}

/** Vẽ đom đóm trong phần banner đang hiện (dưới header), cắt gọn theo mép banner. */
function drawFireflies(ctx: CanvasRenderingContext2D, world: World, sky: Sky) {
    const field = bannerField(sky);
    const b = sky.banner;
    if (!field || !b || world.fireflies.length === 0) return;
    const top = Math.max(b.y, sky.top);
    const bottom = Math.min(b.y + b.h, sky.height);
    if (bottom <= top) return;
    ctx.save();
    ctx.beginPath();
    ctx.rect(b.x, top, b.w, bottom - top);
    ctx.clip();
    ctx.translate(b.x, b.y);
    for (const f of world.fireflies) {
        // Phòng hờ: banner bị chữ phủ kín, đom đóm không có chỗ trống → không vẽ đè chữ.
        if (field.avoid.some((r) => insideRect(f, r))) continue;
        drawFirefly(ctx, f);
    }
    ctx.restore();
}
