import MagazineContent, {
    parseBlocks,
} from '@/Components/site/MagazineContent';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-zvrx — MagazineContent phải chạy được khi render phía server (Node,
 * không có DOMParser) và cho ĐÚNG kết quả như bản cũ dùng DOMParser.
 *
 * So sánh vi sai: `reference` là nguyên thuật toán cũ chạy trên DOM của jsdom.
 */
function reference(html: string) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const blocks: (
        | { kind: 'text'; html: string }
        | { kind: 'images'; imgs: { src: string; alt: string }[] }
    )[] = [];
    let texts: string[] = [];
    let imgs: { src: string; alt: string }[] = [];
    const flushTexts = () => {
        if (texts.length) blocks.push({ kind: 'text', html: texts.join('') });
        texts = [];
    };
    const flushImgs = () => {
        if (imgs.length) blocks.push({ kind: 'images', imgs });
        imgs = [];
    };
    Array.from(doc.body.children).forEach((node) => {
        const nodeImgs =
            node.tagName === 'IMG'
                ? [node as HTMLImageElement]
                : Array.from(node.querySelectorAll('img'));
        if (nodeImgs.length > 0 && (node.textContent ?? '').trim() === '') {
            flushTexts();
            nodeImgs.forEach((i) =>
                imgs.push({
                    src: i.getAttribute('src') ?? '',
                    alt: i.getAttribute('alt') ?? '',
                }),
            );
        } else {
            flushImgs();
            texts.push(node.outerHTML);
        }
    });
    flushTexts();
    flushImgs();
    return blocks;
}

/** So phần cấu trúc; HTML chữ chuẩn hoá qua DOM vì DOMParser tự viết lại thuộc tính. */
function normalize(blocks: ReturnType<typeof parseBlocks>) {
    return blocks.map((b) => {
        if (b.kind === 'images') return b;
        const div = document.createElement('div');
        div.innerHTML = b.html;
        return { kind: 'text', html: div.innerHTML };
    });
}

const SAMPLES: Record<string, string> = {
    'chỉ chữ': '<h2>Lều 4 người</h2><p>Dựng trong <strong>5 phút</strong>.</p>',
    'ảnh rời + ảnh trong p':
        '<p>Mở đầu</p><img src="/a.webp" alt="Lều"><p><img src="/b.webp" alt="Bạt"></p><p>Kết</p>',
    'hai ảnh trong một p, có &nbsp;':
        '<p><img src="/a.webp" alt="1">&nbsp;<img src="/b.webp" alt="2"></p>',
    'ảnh kèm chữ = khối chữ':
        '<p>Ảnh: <img src="/c.webp" alt="c"> đẹp</p><p><img src="/d.webp"></p>',
    'entity trong thuộc tính + chữ':
        '<p><img src="/x.webp?w=1&amp;h=2" alt="A &quot;B&quot; &amp; C"></p><p>Tom &amp; Jerry &lt;3</p>',
    'lồng sâu + danh sách':
        '<ul><li><p>Một</p></li><li><p>Hai <em>nghiêng</em></p></li></ul><blockquote><p>Trích</p></blockquote>',
    'br, hr, comment, chữ rời cấp đỉnh':
        'chữ lạc<p>Dòng 1<br>Dòng 2</p><!-- ghi chú --><hr><p>Sau</p>',
    'thuộc tính chứa >':
        '<p title="a > b">Có dấu lớn</p><img src="/e.webp" alt="x>y">',
    rỗng: '',
};

describe('MagazineContent — tách khối không cần DOM', () => {
    it.each(Object.entries(SAMPLES))(
        'khớp thuật toán DOMParser cũ: %s',
        (_, html) => {
            expect(normalize(parseBlocks(html))).toEqual(
                normalize(reference(html)),
            );
        },
    );

    it('render được khi không có DOMParser (như SSR trên Node)', async () => {
        const { renderToString } = await import('react-dom/server');
        const saved = globalThis.DOMParser;
        // @ts-expect-error — mô phỏng môi trường Node
        delete globalThis.DOMParser;
        try {
            const out = renderToString(
                <MagazineContent html={SAMPLES['ảnh rời + ảnh trong p']} />,
            );
            expect(out).toContain('Mở đầu');
            expect(out).toContain('src="/a.webp"');
            expect(out).toContain('src="/b.webp"');
            expect(out).toContain('Kết');
        } finally {
            globalThis.DOMParser = saved;
        }
    });
});
