import { useMemo } from 'react';

/**
 * Render "nội dung chi tiết" sản phẩm (HTML TipTap đã sanitize server) theo bố cục
 * GALLERY: các ảnh liền nhau xếp LƯỚI 2 ẢNH / HÀNG (mobile 1 ảnh/hàng), có khoảng cách; ảnh hiện ĐẦY ĐỦ
 * (không cắt, không bo góc/viền). Chữ căn TRÁI, xen giữa các nhóm ảnh. Admin chỉ cần
 * soạn tuần tự (ảnh, đoạn văn…) — bố cục tự sắp.
 *
 * ┌─────────┬─────────┐
 * │  IMG    │  IMG    │   ← 2 ảnh / hàng, cách nhau 1 khoảng, hiện trọn ảnh
 * ├─────────┴─────────┤
 * │ text (căn trái)    │
 * └───────────────────┘
 */

type ImgItem = { src: string; alt: string };
type Block =
    | { kind: 'text'; html: string }
    | { kind: 'images'; imgs: ImgItem[] };
type Row =
    | { kind: 'gallery'; imgs: ImgItem[] }
    | { kind: 'text'; html: string };

const VOID_TAGS = new Set([
    'area',
    'base',
    'br',
    'col',
    'embed',
    'hr',
    'img',
    'input',
    'link',
    'meta',
    'source',
    'track',
    'wbr',
]);

/** Thẻ mở/đóng; giá trị thuộc tính trong ngoặc kép được phép chứa '>'. */
const TAG_RE =
    /<!--[\s\S]*?-->|<(\/?)([a-zA-Z][\w-]*)(?:[^>"']|"[^"]*"|'[^']*')*?(\/?)>/g;

const ENTITIES: Record<string, string> = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: '\u00a0',
};

function decodeEntities(s: string): string {
    return s.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (m, e: string) => {
        if (e[0] === '#') {
            const code =
                e[1] === 'x' || e[1] === 'X'
                    ? parseInt(e.slice(2), 16)
                    : parseInt(e.slice(1), 10);
            return Number.isNaN(code) ? m : String.fromCodePoint(code);
        }
        return ENTITIES[e.toLowerCase()] ?? m;
    });
}

function attr(tag: string, name: string): string {
    const m = tag.match(
        new RegExp(
            `\\s${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`,
            'i',
        ),
    );
    return m ? decodeEntities(m[1] ?? m[2] ?? m[3] ?? '') : '';
}

/**
 * Các phần tử cấp cao nhất (≈ `body.children`), trả nguyên chuỗi HTML của từng cái.
 *
 * Tự tách bằng chuỗi thay vì `DOMParser` vì component còn chạy ở SSR (Node, không
 * có DOM — bopcamping-zvrx). Dùng CHUNG một hàm ở cả server lẫn trình duyệt nên hai
 * bên ra cùng bố cục, hydrate không lệch. Đầu vào luôn là HTML đã qua HTMLPurifier
 * (EditorHtml::clean) — thẻ đóng mở đủ, không có script/style.
 */
function topLevelElements(html: string): string[] {
    const out: string[] = [];
    let depth = 0;
    let start = 0;
    for (const m of html.matchAll(TAG_RE)) {
        const [whole, closing, rawName, selfClosing] = m;
        if (rawName === undefined) continue; // comment
        const name = rawName.toLowerCase();
        const at = m.index ?? 0;
        if (closing) {
            if (depth === 0) continue; // thẻ đóng lạc
            depth -= 1;
            if (depth === 0) out.push(html.slice(start, at + whole.length));
            continue;
        }
        if (depth === 0) start = at;
        if (VOID_TAGS.has(name) || selfClosing) {
            if (depth === 0) out.push(whole);
            continue;
        }
        depth += 1;
    }
    if (depth > 0) out.push(html.slice(start)); // thiếu thẻ đóng: giữ phần còn lại
    return out;
}

/** Tách HTML editor thành block text / ảnh theo thứ tự soạn. */
export function parseBlocks(html: string): Block[] {
    const blocks: Block[] = [];
    let texts: string[] = [];
    let imgs: ImgItem[] = [];
    const flushTexts = () => {
        if (texts.length) {
            blocks.push({ kind: 'text', html: texts.join('') });
            texts = [];
        }
    };
    const flushImgs = () => {
        if (imgs.length) {
            blocks.push({ kind: 'images', imgs });
            imgs = [];
        }
    };

    topLevelElements(html).forEach((node) => {
        const nodeImgs =
            node.match(/<img\b(?:[^>"']|"[^"]*"|'[^']*')*>/gi) ?? [];
        const text = decodeEntities(node.replace(TAG_RE, '')).trim();
        // Node chỉ chứa ảnh (TipTap bọc ảnh trong <p> hoặc để rời) → block ảnh;
        // có chữ → block text (giữ nguyên HTML).
        if (nodeImgs.length > 0 && text === '') {
            flushTexts();
            nodeImgs.forEach((i) =>
                imgs.push({ src: attr(i, 'src'), alt: attr(i, 'alt') }),
            );
        } else {
            flushImgs();
            texts.push(node);
        }
    });
    flushTexts();
    flushImgs();

    return blocks;
}

/** Nhóm ảnh liền nhau = 1 gallery hàng đều cao; còn lại = chữ. */
function buildRows(blocks: Block[]): Row[] {
    return blocks.map(
        (b): Row =>
            b.kind === 'images'
                ? { kind: 'gallery', imgs: b.imgs }
                : { kind: 'text', html: b.html },
    );
}

export default function MagazineContent({ html }: { html: string }) {
    const rows = useMemo(() => buildRows(parseBlocks(html)), [html]);

    // Không có ảnh / parse ra 1 khối chữ → render prose thường, full width.
    if (rows.length === 0) {
        return (
            <div
                className="editor-content"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        );
    }

    return (
        <div className="space-y-8">
            {rows.map((row, i) => {
                if (row.kind === 'gallery') {
                    // Lưới 2 ảnh / hàng, có khoảng cách; ảnh hiện ĐẦY ĐỦ (không cắt),
                    // không bo góc/không viền.
                    return (
                        // flex-wrap + justify-center: 2 ảnh/hàng (desktop), 1/hàng (mobile);
                        // ảnh lẻ ở hàng cuối TỰ CĂN GIỮA thay vì lệch trái để trống nửa phải.
                        <div
                            key={i}
                            className="flex flex-wrap items-start justify-center gap-5"
                        >
                            {row.imgs.map((im, j) => (
                                <img
                                    key={j}
                                    src={im.src}
                                    alt={im.alt}
                                    loading="lazy"
                                    className="block h-auto w-full sm:w-[calc(50%-10px)]"
                                />
                            ))}
                        </div>
                    );
                }
                // An toàn: HTML đã qua EditorHtml::clean (HTMLPurifier) phía server
                return (
                    <div
                        key={i}
                        className="editor-content max-w-[820px] text-left [&>:first-child]:mt-0"
                        dangerouslySetInnerHTML={{ __html: row.html }}
                    />
                );
            })}
        </div>
    );
}
