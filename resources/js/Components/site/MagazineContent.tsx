import { useMemo } from 'react';

/**
 * Render "nội dung chi tiết" sản phẩm (HTML TipTap đã sanitize server) theo bố cục
 * ƯU TIÊN ẢNH: mỗi ảnh đơn hiển thị FULL-WIDTH, trọn vẹn (không cắt, giữ tỉ lệ gốc);
 * 2+ ảnh liền nhau xếp thành dải 2 cột; chữ để full-width xen giữa. Admin chỉ cần
 * soạn tuần tự (đoạn văn, ảnh, đoạn văn…), không phải biết HTML — bố cục tự sắp.
 *
 * Desktop:                         Mobile: xếp dọc theo thứ tự soạn.
 * ┌─────────────────┐
 * │      IMAGE       │   ← ảnh đơn full-width, hiện trọn ảnh
 * ├─────────────────┤
 * │      text        │
 * ├────────┬────────┤
 * │  IMG   │  IMG   │   ← 2+ ảnh liền nhau
 * └────────┴────────┘
 */

type ImgItem = { src: string; alt: string };
type Block = { kind: 'text'; html: string } | { kind: 'images'; imgs: ImgItem[] };
type Row =
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

/** Ưu tiên ảnh: ảnh đơn = hàng full-width riêng; 2+ ảnh liền nhau = dải; còn lại = chữ. */
function buildRows(blocks: Block[]): Row[] {
    return blocks.map((b): Row => {
        if (b.kind === 'images' && b.imgs.length >= 2) {
            return { kind: 'strip', imgs: b.imgs };
        }
        if (b.kind === 'images') {
            return { kind: 'image', img: b.imgs[0] };
        }
        return { kind: 'text', html: b.html };
    });
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
                if (row.kind === 'strip') {
                    return (
                        <div key={i} className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            {row.imgs.map((im, j) => (
                                <img key={j} src={im.src} alt={im.alt} loading="lazy" className="block h-auto w-full rounded-[16px]" />
                            ))}
                        </div>
                    );
                }
                if (row.kind === 'image') {
                    // Ảnh đơn: full-width, hiện TRỌN ảnh (không cắt), giữ tỉ lệ gốc.
                    return <img key={i} src={row.img.src} alt={row.img.alt} loading="lazy" className="block h-auto w-full rounded-[18px]" />;
                }
                // An toàn: HTML đã qua EditorHtml::clean (HTMLPurifier) phía server
                return <div key={i} className="editor-content mx-auto max-w-[820px] [&>:first-child]:mt-0" dangerouslySetInnerHTML={{ __html: row.html }} />;
            })}
        </div>
    );
}
