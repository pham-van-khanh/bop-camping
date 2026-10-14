/**
 * Bướm bay khắp các trang khách (bopcamping-hus0). Chỉ có mô phỏng + vẽ, không đụng
 * React — để test được bằng rng cố định. Toạ độ là px CSS trong khung nhìn: canvas
 * cố định phủ cả màn hình, bướm bay theo người xem khi cuộn.
 *
 * Luật UX — đừng nới khi sửa:
 * - Không bao giờ vào vùng `avoid` (khối chữ đầu trang chủ, ô khách đang gõ):
 *   lái né + ràng buộc cứng chặn bước đi vào.
 * - Lượn tránh nút / link / ô nhập nhỏ (`soft`) — chỉ lái né, không cấm tuyệt đối,
 *   vì canvas không chặn chuột nên khách vẫn bấm xuyên qua được.
 * - Né con trỏ / chỗ chạm. Chỉ đậu trên mép trên các thẻ (perch); đang đậu mà thẻ cuộn
 *   khuất thì bay đi, không lơ lửng giữa trời.
 * - Ít con (COUNT). Bị bảo "đi chỗ khác" (away) thì bay ra mép rồi mới biến mất.
 */

export type Rect = { x: number; y: number; w: number; h: number };
export type Point = { x: number; y: number };
/** Mép trên của một khối bướm được đậu lên. Vùng `avoid` của chính khối đó (nếu có)
 *  phải bắt đầu đúng ở mép này, để chỗ bướm ngồi nằm ngoài vùng cấm. */
export type Perch = { id: string; y: number; x0: number; x1: number };
/** Khung bay: cả màn hình, trừ dải header phía trên (`top`). */
export type Sky = {
    width: number;
    height: number;
    top: number;
    /** Cấm tuyệt đối (khối chữ đầu trang, ô đang gõ). */
    avoid: Rect[];
    /** Nên tránh (nút, link, ô nhập nhỏ) — chỉ lái né. */
    soft?: Rect[];
    perches: Perch[];
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
    perchId: string;
    /** Vị trí trên mép đậu, 0 = mép trái, 1 = mép phải. */
    perchT: number;
    rest: number;
};
export type Swarm = {
    rng: () => number;
    small: boolean;
    away: boolean;
    flies: Butterfly[];
};

const TAU = Math.PI * 2;
const SMALL_SCREEN = 640;
export const COUNT = { desktop: 3, mobile: 2 } as const;

const WINGS: Wing[] = [
    { main: '#f2c230', hind: '#e0a412', edge: '#22281a' },
    { main: '#e8702a', hind: '#c4561a', edge: '#2b1406' },
    { main: '#fbfcf7', hind: '#e3e8d6', edge: '#2C3D22' },
];

export const between = (rng: () => number, a: number, b: number) =>
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
function avoidRects(b: Butterfly, rects: Rect[], dt: number, m = 26, rate = 9) {
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
        steer(b, away, rate, dt);
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

function freeSpot(swarm: Swarm, sky: Sky): Point {
    for (let i = 0; i < 40; i++) {
        const p = {
            x: between(swarm.rng, 30, sky.width - 30),
            y: between(swarm.rng, sky.top + 30, sky.height - 30),
        };
        if (!sky.avoid.some((r) => insideRect(p, r, 30))) return p;
    }
    return { x: sky.width - 40, y: sky.top + 40 };
}

function makeButterfly(
    swarm: Swarm,
    sky: Sky,
    i: number,
    entering: boolean,
): Butterfly {
    const fromLeft = swarm.rng() < 0.5;
    const span = sky.height - sky.top;
    const p = entering
        ? {
              x: fromLeft ? -20 : sky.width + 20,
              y: sky.top + span * between(swarm.rng, 0.15, 0.6),
          }
        : freeSpot(swarm, sky);
    return {
        x: p.x,
        y: p.y,
        a: entering ? (fromLeft ? 0 : Math.PI) : between(swarm.rng, 0, TAU),
        v: 45,
        turn: 0,
        turnT: 0,
        ph: between(swarm.rng, 0, TAU),
        size: swarm.small ? 9 : 12,
        wing: WINGS[i % WINGS.length],
        state: 'fly',
        timer: between(swarm.rng, 6, 12),
        seekT: 0,
        perchId: '',
        perchT: 0,
        rest: 0,
    };
}

export function createSwarm(
    sky: Sky,
    rng: () => number = Math.random,
    away = false,
): Swarm {
    const swarm: Swarm = {
        rng,
        small: sky.width < SMALL_SCREEN,
        away,
        flies: [],
    };
    if (!away) {
        const n = COUNT[swarm.small ? 'mobile' : 'desktop'];
        swarm.flies = Array.from({ length: n }, (_, i) =>
            makeButterfly(swarm, sky, i, false),
        );
    }
    return swarm;
}

/** away = true: cả đàn bay ra mép rồi biến mất. false: bay vào lại cho đủ số. */
export function setAway(swarm: Swarm, sky: Sky, away: boolean) {
    if (swarm.away === away) return;
    swarm.away = away;
    if (away) {
        for (const b of swarm.flies) b.state = 'leave';
        return;
    }
    // Con đang bay đi dở thì quay lại bay tiếp (ảnh đêm chỉ hiện ~5 giây — bằng đúng
    // thời gian bay ra mép; bỏ chúng đi là bướm biến mất cái rụp giữa màn hình).
    for (const f of swarm.flies) {
        if (f.state !== 'leave') continue;
        f.state = 'fly';
        f.timer = between(swarm.rng, 6, 12);
    }
    const want = COUNT[swarm.small ? 'mobile' : 'desktop'];
    for (let i = swarm.flies.length; i < want; i++) {
        swarm.flies.push(makeButterfly(swarm, sky, i, true));
    }
}

/** Cuộn trang: bướm đang bay trôi nhẹ ngược chiều cuộn (như ở gần hơn trang web). */
export function scrollSwarm(swarm: Swarm, dy: number) {
    for (const b of swarm.flies) {
        if (b.state !== 'rest') b.y -= dy * 0.3;
    }
}

/** Giữa hai lần đo, khối trên trang trôi theo cuộn — dời tạm thay vì đo lại cả trang. */
export function scrollRects(rects: Rect[], dy: number) {
    for (const r of rects) r.y -= dy;
}

const perchSpot = (b: Butterfly, p: Perch): Point => ({
    x: p.x0 + b.perchT * (p.x1 - p.x0),
    y: p.y - b.size * 0.45,
});

function takeOff(swarm: Swarm, b: Butterfly) {
    b.state = 'fly';
    b.a = -Math.PI / 2 + between(swarm.rng, -0.7, 0.7);
    b.v = 75;
    b.timer = between(swarm.rng, 8, 15);
}

export function stepSwarm(
    swarm: Swarm,
    sky: Sky,
    dt: number,
    pointer: Point | null,
) {
    const t = Math.min(0.05, Math.max(0, dt));
    const { width: W, height: H, top } = sky;
    for (const b of swarm.flies) {
        const near = pointer
            ? Math.hypot(pointer.x - b.x, pointer.y - b.y)
            : Infinity;
        const perch = b.perchId
            ? sky.perches.find((p) => p.id === b.perchId)
            : undefined;

        if (b.state === 'rest') {
            b.rest -= t;
            b.ph += 2.2 * t;
            if (!perch || b.rest <= 0 || near < 90) {
                takeOff(swarm, b);
            } else {
                // Đậu theo thẻ: khách cuộn trang thì bướm đi theo thẻ.
                const s = perchSpot(b, perch);
                b.x = s.x;
                b.y = s.y;
            }
            continue;
        }

        let vTarget = 45;
        let flapRate = 14;
        if (b.state === 'leave') {
            steer(b, b.x < W / 2 ? Math.PI : 0, 4, t);
            avoidRects(b, sky.avoid, t);
            vTarget = 70;
        } else if (b.state === 'seek') {
            if (!perch) {
                b.state = 'fly';
                b.timer = between(swarm.rng, 4, 8);
            } else {
                const s = perchSpot(b, perch);
                steer(b, Math.atan2(s.y - b.y, s.x - b.x), 5, t);
                // Đường tới chỗ đậu vẫn né mọi vùng cấm. Chỉ đoạn đáp cuối — đã ở gần và
                // ở PHÍA TRÊN mép đậu — mới thôi né, để đáp xuống mà không đi xuyên khối.
                const dist = Math.hypot(s.x - b.x, s.y - b.y);
                if (!(dist < 40 && b.y <= s.y + 2)) avoidRects(b, sky.avoid, t);
                vTarget = 55;
                b.seekT -= t;
                if (b.seekT <= 0) {
                    b.state = 'fly';
                    b.timer = between(swarm.rng, 5, 10);
                } else if (dist < 6) {
                    b.state = 'rest';
                    b.x = s.x;
                    b.y = s.y;
                    b.a = -Math.PI / 2;
                    b.rest = between(swarm.rng, 3, 6);
                    continue;
                }
            }
        } else {
            b.turnT -= t;
            if (b.turnT <= 0) {
                b.turn = between(swarm.rng, -1.8, 1.8);
                b.turnT = between(swarm.rng, 0.5, 1.3);
            }
            b.a += b.turn * t;
            if (b.x < 30 || b.x > W - 30 || b.y < top + 24 || b.y > H - 24) {
                steer(b, Math.atan2((top + H) / 2 - b.y, W / 2 - b.x), 4, t);
            }
            if (near < 120 && pointer) {
                steer(b, Math.atan2(b.y - pointer.y, b.x - pointer.x), 8, t);
                vTarget = 130;
                flapRate = 24;
            }
            // Né nút nhẹ tay; né vùng cấm chạy SAU CÙNG — chạy trốn chuột hay tránh nút
            // cũng không được lao vào chữ.
            if (sky.soft) avoidRects(b, sky.soft, t, 10, 6);
            avoidRects(b, sky.avoid, t);
            b.timer -= t;
            if (b.timer <= 0 && sky.perches.length > 0) {
                const p =
                    sky.perches[Math.floor(swarm.rng() * sky.perches.length)];
                b.perchId = p.id;
                b.perchT = swarm.rng();
                b.state = 'seek';
                b.seekT = 7;
            } else if (b.timer <= 0) {
                b.timer = between(swarm.rng, 6, 12);
            }
        }
        b.v += (vTarget - b.v) * Math.min(1, t * 2.5);
        b.ph += flapRate * t;
        moveOutsideRects(
            b,
            b.x + Math.cos(b.a) * b.v * t,
            b.y + Math.sin(b.a) * b.v * t,
            sky.avoid,
        );
    }
    swarm.flies = swarm.flies.filter(
        (b) => b.state !== 'leave' || (b.x > -40 && b.x < W + 40),
    );
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

export function drawSwarm(
    ctx: CanvasRenderingContext2D,
    swarm: Swarm,
    sky: Sky,
) {
    ctx.clearRect(0, 0, sky.width, sky.height);
    for (const b of swarm.flies) drawButterfly(ctx, b);
}
