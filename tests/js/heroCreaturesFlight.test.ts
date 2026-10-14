import { insideRect } from '@/lib/butterflies';
import {
    COUNTS,
    createWorld,
    fireflyBrightness,
    type Layout,
    moodFromLuminance,
    setMood,
    step,
} from '@/lib/heroCreatures';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-hus0 — chim và đom đóm trên hero trang chủ. Test phần mô phỏng
 * (không vẽ): các luật UX phải giữ dù rng ra số nào. Bướm có test riêng
 * (butterflies.test.ts) vì bay khắp trang chứ không chỉ trong hero.
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

// Bố cục giống hero desktop thật: khối chữ bên trái, panel kính bên phải, dải điều khiển dưới.
const DESKTOP: Layout = {
    width: 1280,
    height: 720,
    avoid: [
        { x: 20, y: 150, w: 620, h: 380 },
        { x: 866, y: 200, w: 400, h: 314, glass: true },
        { x: 20, y: 650, w: 1240, h: 56, glass: true },
    ],
};
const MOBILE: Layout = { width: 390, height: 900, avoid: [] };

const FRAME = 1 / 60;

function run(
    seconds: number,
    each: (t: number) => void,
    world: ReturnType<typeof createWorld>,
    layout: Layout,
) {
    for (let t = 0; t < seconds; t += FRAME) {
        step(world, layout, FRAME, null);
        each(t);
    }
}

describe('heroCreatures — ảnh ngày hay đêm', () => {
    it('ngưỡng độ sáng tách đúng 7 ảnh album (đêm 0.19/0.27, hoàng hôn 0.36, ngày ≥ 0.49)', () => {
        expect(moodFromLuminance(0.193)).toBe('night');
        expect(moodFromLuminance(0.27)).toBe('night');
        expect(moodFromLuminance(0.361)).toBe('day');
        expect(moodFromLuminance(0.491)).toBe('day');
    });
});

describe('heroCreatures — đom đóm', () => {
    it('gần như không trôi vào khối chữ (< 2% thời gian), nhưng có bay sau panel kính', () => {
        const world = createWorld(DESKTOP, 'night', seeded(7));
        const text = DESKTOP.avoid.filter((r) => !r.glass);
        const glass = DESKTOP.avoid.filter((r) => r.glass);
        let inText = 0;
        let behindGlass = 0;
        let samples = 0;
        run(
            60,
            () => {
                for (const f of world.fireflies) {
                    samples++;
                    if (text.some((r) => insideRect(f, r))) inText++;
                    if (glass.some((r) => insideRect(f, r))) behindGlass++;
                }
            },
            world,
            DESKTOP,
        );
        expect(inText / samples).toBeLessThan(0.02);
        // Trên điện thoại panel chiếm gần hết hero — cấm cả panel thì đom đóm hết chỗ bay.
        expect(behindGlass).toBeGreaterThan(0);
    });

    it('nhịp nháy: sáng trong pha "on", tắt hẳn trong pha "off"', () => {
        const f = { on: 1, off: 2, t: 0.5 };
        expect(fireflyBrightness(f)).toBeCloseTo(1);
        expect(fireflyBrightness({ ...f, t: 2 })).toBe(0);
        expect(fireflyBrightness({ ...f, t: 3.5 })).toBeCloseTo(1);
    });

    it('điện thoại ít đom đóm hơn', () => {
        const world = createWorld(MOBILE, 'night', seeded(1));
        expect(world.fireflies).toHaveLength(COUNTS.fireflies.mobile);
        expect(COUNTS.fireflies.mobile).toBeLessThan(COUNTS.fireflies.desktop);
    });
});

describe('heroCreatures — đổi ảnh ngày/đêm', () => {
    it('ảnh đêm từ đầu: không có đàn chim nào, đom đóm sáng hẳn', () => {
        const world = createWorld(DESKTOP, 'night', seeded(1));
        let maxFlocks = 0;
        run(
            30,
            () => (maxFlocks = Math.max(maxFlocks, world.flocks.length)),
            world,
            DESKTOP,
        );
        expect(maxFlocks).toBe(0);
        expect(world.night).toBeGreaterThan(0.99);
    });

    it('ngày → đêm: đom đóm hiện dần chứ không bật cái rụp, và không có đàn chim mới', () => {
        const world = createWorld(DESKTOP, 'day', seeded(2));
        run(3, () => {}, world, DESKTOP);
        const flocksBefore = world.flocks.length;
        setMood(world, 'night');

        run(0.3, () => {}, world, DESKTOP);
        expect(world.night).toBeGreaterThan(0);
        expect(world.night).toBeLessThan(0.6);

        let spawned = 0;
        let prev = world.flocks.length;
        run(
            30,
            () => {
                if (world.flocks.length > prev) spawned++;
                prev = world.flocks.length;
            },
            world,
            DESKTOP,
        );
        expect(flocksBefore).toBeGreaterThan(0);
        expect(spawned).toBe(0);
        expect(world.night).toBeGreaterThan(0.99);
    });

    it('đêm → ngày: đom đóm tắt dần, đàn chim đầu tiên tới sớm', () => {
        const world = createWorld(DESKTOP, 'night', seeded(3));
        setMood(world, 'day');
        let firstFlockAt = Infinity;
        run(
            3,
            (t) => {
                if (world.flocks.length > 0)
                    firstFlockAt = Math.min(firstFlockAt, t);
            },
            world,
            DESKTOP,
        );
        expect(firstFlockAt).toBeLessThan(1.5);
        expect(world.night).toBeLessThan(0.05);
    });
});
