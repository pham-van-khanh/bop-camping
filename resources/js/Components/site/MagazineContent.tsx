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
type Block = { kind: 'text'; html: string } | { kind: 'images'; imgs: ImgItem[] };
type Row = { kind: 'gallery'; imgs: ImgItem[] } | { kind: 'text'; html: string };

/** Tách HTML editor thành block text / ảnh theo thứ tự soạn. */
function parseBlocks(html: string): Block[] {
    const doc = new DOMParser().parseFromString(html, 'text/html');
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

    Array.from(doc.body.children).forEach((node) => {
        const nodeImgs = node.tagName === 'IMG' ? [node as HTMLImageElement] : Array.from(node.querySelectorAll('img'));
        // Node chỉ chứa ảnh (TipTap bọc ảnh trong <p> hoặc để rời) → block ảnh;
        // có chữ → block text (giữ nguyên HTML).
        if (nodeImgs.length > 0 && (node.textContent ?? '').trim() === '') {
            flushTexts();
            nodeImgs.forEach((i) => imgs.push({ src: i.getAttribute('src') ?? '', alt: i.getAttribute('alt') ?? '' }));
        } else {
            flushImgs();
            texts.push(node.outerHTML);
        }
    });
    flushTexts();
    flushImgs();

    return blocks;
}

/** Nhóm ảnh liền nhau = 1 gallery hàng đều cao; còn lại = chữ. */
function buildRows(blocks: Block[]): Row[] {
    return blocks.map((b): Row =>
        b.kind === 'images' ? { kind: 'gallery', imgs: b.imgs } : { kind: 'text', html: b.html },
    );
}

export default function MagazineContent({ html }: { html: string }) {
    const rows = useMemo(() => buildRows(parseBlocks(html)), [html]);

    // Không có ảnh / parse ra 1 khối chữ → render prose thường, full width.
    if (rows.length === 0) {
        return <div className="editor-content" dangerouslySetInnerHTML={{ __html: html }} />;
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
                        <div key={i} className="flex flex-wrap items-start justify-center gap-5">
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
                return <div key={i} className="editor-content max-w-[820px] text-left [&>:first-child]:mt-0" dangerouslySetInnerHTML={{ __html: row.html }} />;
            })}
        </div>
    );
}
