import { insideRect, type Sky } from '@/lib/butterflies';
import {
    BIRD_BEHIND_ALPHA,
    birdAlpha,
    birdBand,
    COUNTS,
    createWorld,
    fireflyBrightness,
    fitWorld,
    step,
} from '@/lib/skyCreatures';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-ff1y — chim và đom đóm bay khắp trang khách, mọi lúc (không còn phân
 * ngày/đêm). Test phần mô phỏng: các luật UX phải giữ dù rng ra số nào.
 */

/** rng cố định để test lặp lại được (mulberry32). */
function seeded(seed: number) {
    let a = seed;
    return () => {
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

// Khung nhìn desktop: header cao 72px, khối chữ đầu trang + một ô nhập đang gõ.
const DESKTOP: Sky = {
    width: 1280,
    height: 800,
    top: 72,
    avoid: [
        { x: 20, y: 150, w: 620, h: 380 },
        { x: 900, y: 600, w: 300, h: 120 },
    ],
    perches: [],
};
const MOBILE: Sky = {
    width: 390,
    height: 840,
    top: 60,
    avoid: [],
    perches: [],
};

const FRAME = 1 / 60;

function run(
    seconds: number,
    each: (t: number) => void,
    world: ReturnType<typeof createWorld>,
    sky: Sky,
) {
    for (let t = 0; t < seconds; t += FRAME) {
        step(world, sky, FRAME, null);
        each(t);
    }
}

describe('skyCreatures — chim', () => {
    it('đàn đầu tiên tới sớm và luôn có chim quay lại (không còn chờ "ảnh ngày")', () => {
        for (const seed of [1, 2, 3, 4, 5]) {
            const world = createWorld(DESKTOP, seeded(seed));
            let first = Infinity;
            let seen = 0;
            run(
                60,
                (t) => {
                    if (world.flocks.length > 0) {
                        first = Math.min(first, t);
                        seen++;
                    }
                },
                world,
                DESKTOP,
            );
            expect(first).toBeLessThan(2);
            // Trong 1 phút, phần lớn thời gian trên trời có chim.
            expect(seen / (60 / FRAME)).toBeGreaterThan(0.5);
        }
    });

    it('chỉ bay trong dải trời phía trên, dưới header; không quá số đàn cho phép', () => {
        const world = createWorld(DESKTOP, seeded(9));
        const [y0, y1] = birdBand(DESKTOP);
        let maxFlocks = 0;
        run(
            90,
            () => {
                maxFlocks = Math.max(maxFlocks, world.flocks.length);
                for (const f of world.flocks) {
                    expect(f.y).toBeGreaterThan(y0 - 20);
                    expect(f.y).toBeLessThan(y1 + 20);
                    expect(f.y).toBeGreaterThan(DESKTOP.top);
                }
            },
            world,
            DESKTOP,
        );
        expect(maxFlocks).toBeLessThanOrEqual(COUNTS.flocks);
    });

    it('đi ngang khối chữ / panel thì gần như trong suốt, ra ngoài thì rõ lại', () => {
        const inText = { x: 300, y: 300 };
        const outside = { x: 1000, y: 120 };
        expect(birdAlpha(inText, DESKTOP, 0.8)).toBeCloseTo(
            0.8 * BIRD_BEHIND_ALPHA,
        );
        expect(birdAlpha(outside, DESKTOP, 0.8)).toBe(0.8);
    });

    it('đàn bay ra khỏi màn hình thì được dọn đi (không tích luỹ)', () => {
        const world = createWorld(MOBILE, seeded(4));
        run(300, () => {}, world, MOBILE);
        expect(world.flocks.length).toBeLessThanOrEqual(COUNTS.flocks);
    });
});

describe('skyCreatures — đom đóm chỉ trên banner trang chủ', () => {
    // Banner trang chủ chiếm 720px dưới header; khối chữ và panel nằm trong banner.
    const HOME: Sky = {
        ...DESKTOP,
        banner: { x: 0, y: 72, w: 1280, h: 720 },
    };
    const local = (sky: Sky) =>
        sky.avoid.map((r) => ({
            ...r,
            x: r.x - sky.banner!.x,
            y: r.y - sky.banner!.y,
        }));

    it('trang không có banner: không có con đom đóm nào', () => {
        const world = createWorld(DESKTOP, seeded(1));
        run(10, () => {}, world, DESKTOP);
        expect(world.fireflies).toHaveLength(0);
    });

    it('có banner: đủ số con, luôn ở trong banner và không bao giờ trong vùng cấm', () => {
        for (const seed of [1, 7, 13, 21]) {
            const world = createWorld(HOME, seeded(seed));
            const avoid = local(HOME);
            const { w, h } = HOME.banner!;
            let outside = 0;
            let inAvoid = 0;
            run(
                30,
                () => {
                    for (const f of world.fireflies) {
                        if (f.x < -5 || f.x > w + 5 || f.y < -5 || f.y > h + 5)
                            outside++;
                        if (avoid.some((r) => insideRect(f, r))) inAvoid++;
                    }
                },
                world,
                HOME,
            );
            expect(world.fireflies).toHaveLength(COUNTS.fireflies.desktop);
            expect(outside).toBe(0);
            expect(inAvoid).toBe(0);
        }
    });

    it('cuộn trang: đom đóm trôi theo banner (toạ độ trong banner không nhảy)', () => {
        const world = createWorld(HOME, seeded(4));
        step(world, HOME, FRAME, null);
        const before = world.fireflies.map((f) => ({ x: f.x, y: f.y }));
        const scrolled: Sky = {
            ...HOME,
            banner: { ...HOME.banner!, y: HOME.banner!.y - 400 },
            avoid: HOME.avoid.map((r) => ({ ...r, y: r.y - 400 })),
        };
        step(world, scrolled, FRAME, null);
        world.fireflies.forEach((f, i) => {
            expect(Math.abs(f.x - before[i].x)).toBeLessThan(2);
            expect(Math.abs(f.y - before[i].y)).toBeLessThan(2);
        });
    });

    it('rời trang chủ rồi quay lại: đom đóm vẫn còn, không rải lại từ đầu', () => {
        const world = createWorld(HOME, seeded(6));
        run(2, () => {}, world, HOME);
        const kept = world.fireflies;
        run(2, () => {}, world, DESKTOP);
        run(1, () => {}, world, HOME);
        expect(world.fireflies).toBe(kept);
    });

    it('banner đổi cỡ (xoay máy, thu cửa sổ): vị trí co giãn theo', () => {
        const world = createWorld(HOME, seeded(3));
        step(world, HOME, FRAME, null);
        const small: Sky = {
            ...MOBILE,
            banner: { x: 0, y: 60, w: 390, h: 600 },
        };
        fitWorld(world, small);
        step(world, small, FRAME, null);
        for (const f of world.fireflies) {
            expect(f.x).toBeLessThanOrEqual(390 + 5);
            expect(f.y).toBeLessThanOrEqual(600 + 5);
        }
        expect(world.small).toBe(true);
    });

    it('nhịp nháy: sáng trong pha "on", tắt trong pha "off"', () => {
        const f = { on: 1, off: 2, t: 0.5 };
        expect(fireflyBrightness(f)).toBeCloseTo(1);
        expect(fireflyBrightness({ ...f, t: 2 })).toBe(0);
        expect(fireflyBrightness({ ...f, t: 3.5 })).toBeCloseTo(1);
    });

    it('điện thoại ít đom đóm hơn', () => {
        const phone = createWorld(MOBILE, seeded(1));
        step(
            phone,
            { ...MOBILE, banner: { x: 0, y: 60, w: 390, h: 600 } },
            FRAME,
            null,
        );
        expect(phone.fireflies).toHaveLength(COUNTS.fireflies.mobile);
        expect(COUNTS.fireflies.mobile).toBeLessThan(COUNTS.fireflies.desktop);
    });
});
