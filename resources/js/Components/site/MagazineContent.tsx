import { useMemo } from 'react';

/**
 * Render "nội dung chi tiết" sản phẩm (HTML TipTap đã sanitize server) theo bố cục
 * magazine xen kẽ (Epic 1 feedback #4): ảnh trái–text phải, rồi đảo bên, nhiều ảnh
 * liên tiếp thành dải ảnh ngang. Admin chỉ cần soạn tuần tự (đoạn văn, ảnh, đoạn văn…),
 * không phải biết HTML — bố cục tự sắp.
 *
 * Desktop:                         Mobile: xếp dọc theo thứ tự soạn.
 * ┌────────┬────────┐
 * │ IMAGE  │ text   │
 * ├────────┼────────┤
 * │ text   │ IMAGE  │
 * ├──────┬─────┬────┤
 * │ IMG  │ IMG │ IMG│   ← 2+ ảnh liền nhau
 * └──────┴─────┴────┘
 */

type ImgItem = { src: string; alt: string };
type Block = { kind: 'text'; html: string } | { kind: 'images'; imgs: ImgItem[] };
type Row =
    | { kind: 'pair'; img: ImgItem; html: string; imageLeft: boolean }
    | { kind: 'strip'; imgs: ImgItem[] }
    | { kind: 'image'; img: ImgItem }
    | { kind: 'text'; html: string };

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

/** Ghép block thành hàng: ảnh đơn + text kề nhau = hàng 2 cột (đảo bên luân phiên). */
function buildRows(blocks: Block[]): Row[] {
    const rows: Row[] = [];
    let imageLeft = true;

    for (let i = 0; i < blocks.length; i++) {
        const b = blocks[i];
        const next = blocks[i + 1];

        if (b.kind === 'images' && b.imgs.length >= 2) {
            rows.push({ kind: 'strip', imgs: b.imgs });
        } else if (b.kind === 'images' && next?.kind === 'text') {
            rows.push({ kind: 'pair', img: b.imgs[0], html: next.html, imageLeft });
            imageLeft = !imageLeft;
            i++;
        } else if (b.kind === 'text' && next?.kind === 'images' && next.imgs.length === 1) {
            rows.push({ kind: 'pair', img: next.imgs[0], html: b.html, imageLeft });
            imageLeft = !imageLeft;
            i++;
        } else if (b.kind === 'images') {
            rows.push({ kind: 'image', img: b.imgs[0] });
        } else {
            rows.push({ kind: 'text', html: b.html });
        }
    }

    return rows;
}

export default function MagazineContent({ html }: { html: string }) {
    const rows = useMemo(() => buildRows(parseBlocks(html)), [html]);

    // Không có ảnh / parse ra 1 khối chữ → render prose thường, full width.
    if (rows.length === 0) {
        return <div className="editor-content" dangerouslySetInnerHTML={{ __html: html }} />;
    }

    return (
        <div className="space-y-10">
            {rows.map((row, i) => {
                if (row.kind === 'pair') {
                    return (
                        <div key={i} className="grid items-center gap-5 md:grid-cols-2 md:gap-10">
                            <img
                                src={row.img.src}
                                alt={row.img.alt}
                                loading="lazy"
                                className={`max-h-[440px] w-full rounded-[18px] object-cover ${row.imageLeft ? '' : 'md:order-2'}`}
                            />
                            {/* An toàn: HTML đã qua EditorHtml::clean (HTMLPurifier) phía server */}
                            <div className="editor-content [&>:first-child]:mt-0" dangerouslySetInnerHTML={{ __html: row.html }} />
                        </div>
                    );
                }
                if (row.kind === 'strip') {
                    return (
                        <div key={i} className="grid grid-cols-2 gap-4 md:grid-cols-3">
                            {row.imgs.map((im, j) => (
                                <img key={j} src={im.src} alt={im.alt} loading="lazy" className="h-[180px] w-full rounded-[16px] object-cover md:h-[240px]" />
                            ))}
                        </div>
                    );
                }
                if (row.kind === 'image') {
                    return <img key={i} src={row.img.src} alt={row.img.alt} loading="lazy" className="max-h-[480px] w-full rounded-[18px] object-cover" />;
                }
                return <div key={i} className="editor-content max-w-[860px] [&>:first-child]:mt-0" dangerouslySetInnerHTML={{ __html: row.html }} />;
            })}
        </div>
    );
}
