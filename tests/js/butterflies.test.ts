import {
    COUNT,
    createSwarm,
    insideRect,
    scrollSwarm,
    setAway,
    type Sky,
    stepSwarm,
} from '@/lib/butterflies';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-hus0 — bướm bay khắp các trang khách. Test phần mô phỏng (không vẽ):
 * các luật UX phải giữ dù rng ra số nào.
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

// Màn hình desktop đang ở đầu trang chủ: header 64px, khối chữ bên trái, panel bên phải
// (vừa là vùng cấm vừa là chỗ đậu → vùng cấm bắt đầu đúng ở mép trên y = 214),
// thêm một thẻ sản phẩm lấp ló phía dưới để đậu.
const DESKTOP: Sky = {
    width: 1280,
    height: 800,
    top: 64,
    avoid: [
        { x: 8, y: 138, w: 644, h: 404 },
        { x: 854, y: 214, w: 424, h: 312 },
    ],
    perches: [
        { id: 'panel', y: 214, x0: 890, x1: 1242 },
        { id: 'card', y: 620, x0: 60, x1: 300 },
    ],
};
const PHONE: Sky = { width: 390, height: 844, top: 64, avoid: [], perches: [] };

const FRAME = 1 / 60;

describe('butterflies — không che chữ, không chui dưới header', () => {
    // 30 seed: 5 seed từng lọt lưới một đường bay (chạy trốn chuột lao vào mép panel).
    it.each(Array.from({ length: 30 }, (_, i) => i + 1))(
        'seed %i: 60 giây bay, kể cả khi con trỏ rượt, không lần nào lọt vào vùng cấm',
        (seed) => {
            const swarm = createSwarm(DESKTOP, seeded(seed));
            let hits = 0;
            let samples = 0;
            for (let t = 0; t < 60; t += FRAME) {
                // Con trỏ quét vòng quanh màn hình — ép bướm chạy trốn theo đủ hướng.
                stepSwarm(swarm, DESKTOP, FRAME, {
                    x: 640 + Math.cos(t * 0.7) * 520,
                    y: 420 + Math.sin(t * 1.1) * 320,
                });
                for (const b of swarm.flies) {
                    if (b.state === 'rest') continue;
                    samples++;
                    if (DESKTOP.avoid.some((r) => insideRect(b, r))) hits++;
                }
            }
            expect(samples).toBeGreaterThan(0);
            expect(hits).toBe(0);
        },
    );

    it('hầu như luôn bay dưới header (< 3% thời gian lấn lên)', () => {
        let above = 0;
        let samples = 0;
        for (let seed = 1; seed <= 10; seed++) {
            const swarm = createSwarm(DESKTOP, seeded(seed));
            for (let t = 0; t < 60; t += FRAME) {
                stepSwarm(swarm, DESKTOP, FRAME, null);
                for (const b of swarm.flies) {
                    samples++;
                    if (b.y < DESKTOP.top) above++;
                }
            }
        }
        expect(above / samples).toBeLessThan(0.03);
    });

    it('có đậu nghỉ (không bay mãi không ngừng)', () => {
        const swarm = createSwarm(DESKTOP, seeded(4));
        let resting = 0;
        for (let t = 0; t < 60; t += FRAME) {
            stepSwarm(swarm, DESKTOP, FRAME, null);
            resting += swarm.flies.filter((b) => b.state === 'rest').length;
        }
        expect(resting).toBeGreaterThan(0);
    });
});

describe('butterflies — lượn tránh nút bấm', () => {
    // Một dải nút / chip dày đặc giữa màn hình (kiểu hàng chip lọc + nút "Xem thêm").
    const buttons = Array.from({ length: 24 }, (_, i) => ({
        x: 40 + (i % 8) * 150,
        y: 300 + Math.floor(i / 8) * 120,
        w: 110,
        h: 40,
    }));
    const overButtons = (sky: Sky) => {
        let over = 0;
        let samples = 0;
        for (let seed = 1; seed <= 10; seed++) {
            const swarm = createSwarm(sky, seeded(seed));
            for (let t = 0; t < 60; t += FRAME) {
                stepSwarm(swarm, sky, FRAME, null);
                for (const b of swarm.flies) {
                    samples++;
                    if (buttons.some((r) => insideRect(b, r))) over++;
                }
            }
        }
        return over / samples;
    };
    const open: Sky = { ...PHONE, width: 1280, height: 800, perches: [] };

    it('có tránh nút thì bướm ở trên nút ít hơn hẳn (dưới một nửa so với không tránh)', () => {
        const without = overButtons(open);
        const withSoft = overButtons({ ...open, soft: buttons });
        expect(without).toBeGreaterThan(0);
        expect(withSoft).toBeLessThan(without * 0.5);
    });
});

describe('butterflies — đậu trên thẻ khi khách cuộn trang', () => {
    function restingOnCard() {
        const swarm = createSwarm(DESKTOP, seeded(1));
        const b = swarm.flies[0];
        Object.assign(b, {
            state: 'rest',
            perchId: 'card',
            perchT: 0.5,
            rest: 5,
        });
        return { swarm, b };
    }

    it('thẻ cuộn lên thì bướm đi theo thẻ', () => {
        const { swarm, b } = restingOnCard();
        const scrolled: Sky = {
            ...DESKTOP,
            perches: [{ id: 'card', y: 500, x0: 60, x1: 300 }],
        };
        stepSwarm(swarm, scrolled, FRAME, null);
        expect(b.state).toBe('rest');
        expect(b.x).toBeCloseTo(180);
        expect(b.y).toBeCloseTo(500 - b.size * 0.45);
    });

    it('thẻ cuộn khuất thì bướm bay đi, không lơ lửng giữa trời', () => {
        const { swarm, b } = restingOnCard();
        stepSwarm(swarm, { ...DESKTOP, perches: [] }, FRAME, null);
        expect(b.state).toBe('fly');
    });

    it('cuộn trang: bướm đang bay trôi nhẹ ngược chiều, bướm đang đậu thì không', () => {
        const { swarm, b } = restingOnCard();
        const flying = swarm.flies[1];
        const y0 = flying.y;
        const rest0 = b.y;
        scrollSwarm(swarm, 100);
        expect(flying.y).toBeCloseTo(y0 - 30);
        expect(b.y).toBe(rest0);
    });
});

describe('butterflies — bay đi (trang thanh toán, ảnh đêm) rồi quay lại', () => {
    it('away: bay ra mép rồi mới biến mất; hết away thì bay vào lại đủ số', () => {
        const swarm = createSwarm(DESKTOP, seeded(2));
        expect(swarm.flies).toHaveLength(COUNT.desktop);

        setAway(swarm, DESKTOP, true);
        expect(swarm.flies.every((b) => b.state === 'leave')).toBe(true);
        stepSwarm(swarm, DESKTOP, 0.3, null);
        expect(swarm.flies.length).toBeGreaterThan(0);

        for (let t = 0; t < 30; t += FRAME) {
            stepSwarm(swarm, DESKTOP, FRAME, null);
        }
        expect(swarm.flies).toHaveLength(0);

        setAway(swarm, DESKTOP, false);
        expect(swarm.flies).toHaveLength(COUNT.desktop);
        // Bay vào từ mép màn hình chứ không hiện ra giữa trang.
        expect(swarm.flies.every((b) => b.x < 0 || b.x > DESKTOP.width)).toBe(
            true,
        );
    });

    it('đang bay đi dở mà hết away (ảnh đêm chỉ hiện ~5 giây) thì quay lại bay tiếp, không biến mất', () => {
        const swarm = createSwarm(DESKTOP, seeded(5));
        setAway(swarm, DESKTOP, true);
        stepSwarm(swarm, DESKTOP, 0.3, null);
        const leaving = [...swarm.flies];
        expect(leaving.length).toBe(COUNT.desktop);

        setAway(swarm, DESKTOP, false);
        expect(swarm.flies).toHaveLength(COUNT.desktop);
        // Đúng những con cũ, ở nguyên chỗ cũ — không phải con mới mọc ra ở mép.
        for (const b of leaving) expect(swarm.flies).toContain(b);
        expect(swarm.flies.every((b) => b.state === 'fly')).toBe(true);
    });

    it('mở thẳng trang không có bướm thì không có con nào', () => {
        const swarm = createSwarm(DESKTOP, seeded(3), true);
        expect(swarm.flies).toHaveLength(0);
    });

    it('điện thoại ít bướm hơn và bướm nhỏ hơn', () => {
        const phone = createSwarm(PHONE, seeded(1));
        const desk = createSwarm(DESKTOP, seeded(1));
        expect(phone.flies).toHaveLength(COUNT.mobile);
        expect(COUNT.mobile).toBeLessThan(COUNT.desktop);
        expect(phone.flies[0].size).toBeLessThan(desk.flies[0].size);
    });
});
