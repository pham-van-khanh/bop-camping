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

describe('skyCreatures — đom đóm', () => {
    it('không bao giờ ở trong vùng cấm (khối chữ, ô đang gõ), dù nhiều seed', () => {
        for (const seed of [1, 7, 13, 21]) {
            const world = createWorld(DESKTOP, seeded(seed));
            run(
                40,
                () => {
                    for (const f of world.fireflies) {
                        expect(
                            DESKTOP.avoid.some((r) => insideRect(f, r)),
                        ).toBe(false);
                    }
                },
                world,
                DESKTOP,
            );
        }
    });

    it('vùng cấm mới mọc ngay chỗ đom đóm (khách bấm vào ô nhập) thì nó ra ngay', () => {
        const world = createWorld(DESKTOP, seeded(5));
        const f = world.fireflies[0];
        const box = { x: f.x - 50, y: f.y - 50, w: 100, h: 100 };
        const sky = { ...DESKTOP, avoid: [...DESKTOP.avoid, box] };
        step(world, sky, FRAME, null);
        expect(insideRect(f, box)).toBe(false);
    });

    it('ở trong màn hình và dưới header', () => {
        const world = createWorld(DESKTOP, seeded(11));
        run(
            60,
            () => {
                for (const f of world.fireflies) {
                    expect(f.x).toBeGreaterThan(-10);
                    expect(f.x).toBeLessThan(DESKTOP.width + 10);
                    expect(f.y).toBeGreaterThan(DESKTOP.top - 10);
                    expect(f.y).toBeLessThan(DESKTOP.height + 10);
                }
            },
            world,
            DESKTOP,
        );
    });

    it('thu nhỏ cửa sổ: con nằm ngoài khung mới được đưa vào trong', () => {
        const world = createWorld(DESKTOP, seeded(3));
        fitWorld(world, MOBILE);
        for (const f of world.fireflies) {
            expect(f.x).toBeLessThanOrEqual(MOBILE.width);
            expect(f.y).toBeLessThanOrEqual(MOBILE.height);
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
        expect(createWorld(MOBILE, seeded(1)).fireflies).toHaveLength(
            COUNTS.fireflies.mobile,
        );
        expect(createWorld(DESKTOP, seeded(1)).fireflies).toHaveLength(
            COUNTS.fireflies.desktop,
        );
        expect(COUNTS.fireflies.mobile).toBeLessThan(COUNTS.fireflies.desktop);
    });
});
