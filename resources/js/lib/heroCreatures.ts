/**
 * Chim, bướm, đom đóm bay trên hero trang chủ (bopcamping-hus0).
 *
 * File này chỉ có mô phỏng + vẽ lên canvas, không đụng React — để test được bằng
 * rng cố định. Toạ độ là px CSS trong khung hero (gốc = góc trên-trái hero).
 *
 * Luật UX — đừng nới khi sửa:
 * - Bướm không bay vào các vùng `avoid` (chữ, nút, panel, điều khiển slide).
 * - Đom đóm không bay vào khối chữ; được bay sau khối kính (`glass`).
 * - Ảnh ngày: chim + bướm. Ảnh đêm: đom đóm. Đổi ảnh thì lớp cũ rút đi từ từ
 *   (bướm bay ra mép, đom đóm mờ dần), không biến mất cái rụp.
 * - Ít con: điện thoại còn ít hơn nữa (xem COUNTS).
 */

export type Mood = 'day' | 'night';
export type Rect = { x: number; y: number; w: number; h: number };
/** `glass`: khối trong suốt/kính mờ (panel, dải điều khiển). Lớp animation nằm SAU
 *  nội dung nên đom đóm bay phía sau kính chỉ thành đốm sáng nhoè — được phép.
 *  Khối chữ thì không có nền, ánh sáng sau chữ làm khó đọc — luôn né. */
export type AvoidRect = Rect & { glass?: boolean };
export type Point = { x: number; y: number };
/** Mép trên của một khối mà bướm được đậu lên (vd panel bên phải hero).
 *  Vùng `avoid` của khối đó phải bắt đầu đúng ở mép này (không nới lên trên),
 *  để chỗ bướm ngồi nằm ngoài vùng cấm. */
export type Perch = { y: number; x0: number; x1: number };
export type Layout = {
    width: number;
    height: number;
    avoid: AvoidRect[];
    perches: Perch[];
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
type Wing = { main: string; hind: string; edge: string };
export type ButterflyState = 'fly' | 'seek' | 'rest' | 'leave';
export type Butterfly = {
    x: number;
    y: number;
    a: number;
    v: number;
    turn: number;
    turnT: number;
    ph: number;
    size: number;
    wing: Wing;
    state: ButterflyState;
    timer: number;
    seekT: number;
    px: number;
    py: number;
    rest: number;
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
    flies: Butterfly[];
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
    butterflies: { desktop: 2, mobile: 1 },
    fireflies: { desktop: 16, mobile: 9 },
} as const;

const WINGS: Wing[] = [
    { main: '#f2c230', hind: '#e0a412', edge: '#22281a' },
    { main: '#e8702a', hind: '#c4561a', edge: '#2b1406' },
    { main: '#fbfcf7', hind: '#e3e8d6', edge: '#2C3D22' },
];

export function moodFromLuminance(luminance: number): Mood {
    return luminance < NIGHT_LUMINANCE ? 'night' : 'day';
}

const isMobile = (layout: Layout) => layout.width < MOBILE_MAX_WIDTH;
const between = (rng: () => number, a: number, b: number) =>
    a + rng() * (b - a);

export function insideRect(p: Point, r: Rect, margin = 0): boolean {
    return (
        p.x > r.x - margin &&
        p.x < r.x + r.w + margin &&
        p.y > r.y - margin &&
        p.y < r.y + r.h + margin
    );
}

function steer(b: Butterfly, target: number, rate: number, dt: number) {
    const d = Math.atan2(Math.sin(target - b.a), Math.cos(target - b.a));
    b.a += Math.max(-rate * dt, Math.min(rate * dt, d));
}

/** Hướng ra khỏi khối gần nhất theo trục ngắn — bướm quay đầu trước khi chạm chữ. */
function avoidRects(b: Butterfly, rects: Rect[], dt: number) {
    const m = 26;
    for (const r of rects) {
        if (!insideRect(b, r, m)) continue;
        const ex = (b.x - (r.x + r.w / 2)) / (r.w / 2 + m);
        const ey = (b.y - (r.y + r.h / 2)) / (r.h / 2 + m);
        const away =
            Math.abs(ex) > Math.abs(ey)
                ? ex > 0
                    ? 0
                    : Math.PI
                : ey > 0
                  ? Math.PI / 2
                  : -Math.PI / 2;
        steer(b, away, 9, dt);
    }
}

/**
 * Ràng buộc cứng sau khi lái: bước nào làm bướm lọt vào vùng cấm thì bỏ phần đi vào,
 * bướm trượt dọc theo mép. Chỉ lái thì không đủ — lúc chạy trốn con trỏ với tốc độ
 * cao, lực né chuột và lực né khối có thể triệt tiêu nhau (bắt được bằng test nhiều seed).
 */
function moveOutsideRects(b: Butterfly, nx: number, ny: number, rects: Rect[]) {
    let x = nx;
    let y = ny;
    for (const r of rects) {
        if (!insideRect({ x, y }, r) || insideRect(b, r)) continue;
        if (!insideRect({ x, y: b.y }, r)) y = b.y;
        else if (!insideRect({ x: b.x, y }, r)) x = b.x;
        else {
            x = b.x;
            y = b.y;
        }
    }
    b.x = x;
    b.y = y;
}

function freeSpot(world: World, layout: Layout): Point {
    for (let i = 0; i < 40; i++) {
        const p = {
            x: between(world.rng, 30, layout.width - 30),
            y: between(world.rng, 30, layout.height * 0.7),
        };
        if (!layout.avoid.some((r) => insideRect(p, r, 30))) return p;
    }
    return { x: layout.width - 40, y: 40 };
}

function makeButterfly(
    world: World,
    layout: Layout,
    i: number,
    entering: boolean,
): Butterfly {
    const fromLeft = world.rng() < 0.5;
    const p = entering
        ? {
              x: fromLeft ? -20 : layout.width + 20,
              y: between(world.rng, layout.height * 0.15, layout.height * 0.55),
          }
        : freeSpot(world, layout);
    return {
        x: p.x,
        y: p.y,
        a: entering ? (fromLeft ? 0 : Math.PI) : between(world.rng, 0, TAU),
        v: 45,
        turn: 0,
        turnT: 0,
        ph: between(world.rng, 0, TAU),
        size: world.mobile ? 9 : 12,
        wing: WINGS[i % WINGS.length],
        state: 'fly',
        timer: between(world.rng, 6, 12),
        seekT: 0,
        px: 0,
        py: 0,
        rest: 0,
    };
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
    const mobile = isMobile(layout);
    const world: World = {
        rng,
        mood,
        mobile,
        flocks: [],
        nextFlock: 1,
        flies: [],
        fireflies: [],
        night: mood === 'night' ? 1 : 0,
    };
    if (mood === 'day') {
        const n = COUNTS.butterflies[mobile ? 'mobile' : 'desktop'];
        world.flies = Array.from({ length: n }, (_, i) =>
            makeButterfly(world, layout, i, false),
        );
    }
    const f = COUNTS.fireflies[mobile ? 'mobile' : 'desktop'];
    world.fireflies = Array.from({ length: f }, () =>
        makeFirefly(world, layout),
    );
    return world;
}

export function setMood(world: World, layout: Layout, mood: Mood) {
    if (world.mood === mood) return;
    world.mood = mood;
    if (mood === 'night') {
        for (const b of world.flies) b.state = 'leave';
        return;
    }
    const want = COUNTS.butterflies[world.mobile ? 'mobile' : 'desktop'];
    world.flies = world.flies.filter((b) => b.state !== 'leave');
    for (let i = world.flies.length; i < want; i++) {
        world.flies.push(makeButterfly(world, layout, i, true));
    }
    world.nextFlock = between(world.rng, 0.6, 1.4);
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

/* ── Bướm: lượn lờ, né chữ, né con trỏ, thỉnh thoảng đậu nghỉ ── */

function stepButterflies(
    world: World,
    layout: Layout,
    dt: number,
    pointer: Point | null,
) {
    const { width: W, height: H } = layout;
    for (const b of world.flies) {
        const near = pointer
            ? Math.hypot(pointer.x - b.x, pointer.y - b.y)
            : Infinity;
        if (b.state === 'rest') {
            b.rest -= dt;
            b.ph += 2.2 * dt;
            if (b.rest <= 0 || near < 90) {
                b.state = 'fly';
                b.a = -Math.PI / 2 + between(world.rng, -0.7, 0.7);
                b.v = 75;
                b.timer = between(world.rng, 8, 15);
            }
            continue;
        }
        let vTarget = 45;
        let flapRate = 14;
        if (b.state === 'leave') {
            steer(b, b.x < W / 2 ? Math.PI : 0, 4, dt);
            avoidRects(b, layout.avoid, dt);
            vTarget = 70;
        } else if (b.state === 'seek') {
            steer(b, Math.atan2(b.py - b.y, b.px - b.x), 5, dt);
            // Đường tới chỗ đậu vẫn né mọi vùng cấm. Chỉ đoạn đáp cuối — đã ở gần và
            // ở PHÍA TRÊN mép đậu — mới thôi né, để đáp xuống được mà không đi xuyên khối.
            const dist = Math.hypot(b.px - b.x, b.py - b.y);
            if (!(dist < 40 && b.y <= b.py + 2))
                avoidRects(b, layout.avoid, dt);
            vTarget = 55;
            b.seekT -= dt;
            if (b.seekT <= 0) {
                b.state = 'fly';
                b.timer = between(world.rng, 5, 10);
            } else if (dist < 6) {
                b.state = 'rest';
                b.x = b.px;
                b.y = b.py;
                b.a = -Math.PI / 2;
                b.rest = between(world.rng, 3, 6);
                continue;
            }
        } else {
            b.turnT -= dt;
            if (b.turnT <= 0) {
                b.turn = between(world.rng, -1.8, 1.8);
                b.turnT = between(world.rng, 0.5, 1.3);
            }
            b.a += b.turn * dt;
            if (b.x < 30 || b.x > W - 30 || b.y < 24 || b.y > H - 24) {
                steer(b, Math.atan2(H * 0.4 - b.y, W / 2 - b.x), 4, dt);
            }
            if (near < 120 && pointer) {
                steer(b, Math.atan2(b.y - pointer.y, b.x - pointer.x), 8, dt);
                vTarget = 130;
                flapRate = 24;
            }
            // Né chữ chạy SAU né con trỏ: chạy trốn chuột cũng không được lao vào chữ.
            avoidRects(b, layout.avoid, dt);
            b.timer -= dt;
            if (b.timer <= 0 && layout.perches.length > 0) {
                const p =
                    layout.perches[
                        Math.floor(world.rng() * layout.perches.length)
                    ];
                b.px = between(world.rng, p.x0, p.x1);
                b.py = p.y - b.size * 0.45;
                b.state = 'seek';
                b.seekT = 7;
            } else if (b.timer <= 0) {
                b.timer = between(world.rng, 6, 12);
            }
        }
        b.v += (vTarget - b.v) * Math.min(1, dt * 2.5);
        b.ph += flapRate * dt;
        moveOutsideRects(
            b,
            b.x + Math.cos(b.a) * b.v * dt,
            b.y + Math.sin(b.a) * b.v * dt,
            layout.avoid,
        );
    }
    world.flies = world.flies.filter(
        (b) => b.state !== 'leave' || (b.x > -40 && b.x < W + 40),
    );
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
    stepButterflies(world, layout, t, pointer);
    world.night +=
        ((world.mood === 'night' ? 1 : 0) - world.night) * Math.min(1, t * 1.5);
    if (world.night > 0.01) stepFireflies(world, layout, t, pointer);
}

/* ── Vẽ ── */

let wingPaths: { fore: Path2D; hind: Path2D } | null = null;
function wings() {
    // Cánh phải theo đơn vị 1 = cỡ con bướm; thân nằm trên trục y, đầu hướng lên (-y).
    wingPaths ??= {
        fore: new Path2D(
            'M0.05 -0.22 C0.32 -0.98 1.18 -1.02 1.06 -0.46 C1.0 -0.16 0.5 0.0 0.05 0.04 Z',
        ),
        hind: new Path2D(
            'M0.05 0.0 C0.62 -0.02 0.9 0.36 0.62 0.68 C0.42 0.9 0.12 0.62 0.04 0.3 Z',
        ),
    };
    return wingPaths;
}

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

function drawButterfly(ctx: CanvasRenderingContext2D, b: Butterfly) {
    const { fore, hind } = wings();
    const resting = b.state === 'rest';
    const open = resting
        ? 0.25 + 0.75 * (0.5 + 0.5 * Math.sin(b.ph))
        : 0.15 + 0.85 * Math.abs(Math.cos(b.ph));
    const s = b.size;
    ctx.save();
    ctx.translate(b.x, b.y + (resting ? 0 : Math.sin(b.ph) * s * 0.18));
    ctx.rotate(b.a + Math.PI / 2);
    for (const side of [-1, 1]) {
        ctx.save();
        ctx.scale(side * open * s, s);
        ctx.lineWidth = 1 / s;
        ctx.strokeStyle = b.wing.edge;
        ctx.fillStyle = b.wing.hind;
        ctx.fill(hind);
        ctx.stroke(hind);
        ctx.fillStyle = b.wing.main;
        ctx.fill(fore);
        ctx.save();
        ctx.clip(fore);
        ctx.fillStyle = b.wing.edge;
        ctx.beginPath();
        ctx.ellipse(1.02, -0.66, 0.36, 0.3, -0.4, 0, TAU);
        ctx.fill();
        ctx.fillStyle = '#fbfcf7';
        ctx.beginPath();
        ctx.arc(0.86, -0.7, 0.055, 0, TAU);
        ctx.arc(0.98, -0.55, 0.045, 0, TAU);
        ctx.fill();
        ctx.restore();
        ctx.stroke(fore);
        ctx.restore();
    }
    ctx.fillStyle = b.wing.edge;
    ctx.beginPath();
    ctx.ellipse(0, 0, s * 0.09, s * 0.42, 0, 0, TAU);
    ctx.fill();
    ctx.strokeStyle = b.wing.edge;
    ctx.lineWidth = Math.max(1, s * 0.06);
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(0, -s * 0.38);
    ctx.quadraticCurveTo(-s * 0.12, -s * 0.7, -s * 0.26, -s * 0.82);
    ctx.moveTo(0, -s * 0.38);
    ctx.quadraticCurveTo(s * 0.12, -s * 0.7, s * 0.26, -s * 0.82);
    ctx.stroke();
    ctx.restore();
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
    for (const b of world.flies) drawButterfly(ctx, b);
    if (world.night > 0.01) drawFireflies(ctx, world);
}
