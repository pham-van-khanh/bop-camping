import { EditorContent, useEditor, type Editor } from '@tiptap/react';
import Image from '@tiptap/extension-image';
import StarterKit from '@tiptap/starter-kit';
import { useRef, useState } from 'react';

/**
 * Trình soạn thảo rich text dùng chung ở admin (nội dung setup sản phẩm, trang tĩnh...).
 * Xuất HTML — server LUÔN sanitize lại bằng EditorHtml::clean trước khi lưu.
 * Ảnh chèn vào nội dung upload qua POST /admin/editor/images (disk media).
 */
export default function RichTextEditor({
    value,
    onChange,
    minHeight = 320,
}: {
    value: string;
    onChange: (html: string) => void;
    minHeight?: number;
}) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [uploading, setUploading] = useState(false);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                link: { openOnClick: false },
            }),
            Image,
        ],
        content: value,
        onUpdate: ({ editor: e }) => onChange(e.getHTML()),
        editorProps: {
            attributes: {
                class: 'editor-content outline-none px-4 py-3',
                style: `min-height:${minHeight}px`,
            },
        },
    });

    if (!editor) return null;

    const uploadImage = async (file: File) => {
        setUploading(true);
        try {
            const form = new FormData();
            form.append('image', file);
            const { data } = await window.axios.post<{ url: string }>('/admin/editor/images', form);
            editor.chain().focus().setImage({ src: data.url, alt: file.name.replace(/\.\w+$/, '') }).run();
        } catch {
            alert('Tải ảnh thất bại — chỉ nhận jpg/png/webp, tối đa 4MB.');
        } finally {
            setUploading(false);
        }
    };

    const setLink = () => {
        const prev = (editor.getAttributes('link').href as string | undefined) ?? '';
        const url = window.prompt('Địa chỉ liên kết (https://…)', prev);
        if (url === null) return;
        if (url === '') {
            editor.chain().focus().unsetLink().run();
            return;
        }
        editor.chain().focus().extendMarkRange('link').setLink({ href: url, target: '_blank' }).run();
    };

    return (
        <div className="overflow-hidden rounded-[12px] border border-cardBorder bg-white transition focus-within:border-grass">
            <div className="flex flex-wrap items-center gap-1 border-b border-[#eef2e3] bg-[#f8faf4] px-2 py-1.5">
                <ToolButton active={editor.isActive('heading', { level: 2 })} label="Tiêu đề lớn" onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}>H2</ToolButton>
                <ToolButton active={editor.isActive('heading', { level: 3 })} label="Tiêu đề vừa" onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}>H3</ToolButton>
                <Divider />
                <ToolButton active={editor.isActive('bold')} label="Đậm" onClick={() => editor.chain().focus().toggleBold().run()}><b>B</b></ToolButton>
                <ToolButton active={editor.isActive('italic')} label="Nghiêng" onClick={() => editor.chain().focus().toggleItalic().run()}><i>I</i></ToolButton>
                <ToolButton active={editor.isActive('strike')} label="Gạch ngang" onClick={() => editor.chain().focus().toggleStrike().run()}><s>S</s></ToolButton>
                <Divider />
                <ToolButton active={editor.isActive('bulletList')} label="Danh sách chấm" onClick={() => editor.chain().focus().toggleBulletList().run()}>•≡</ToolButton>
                <ToolButton active={editor.isActive('orderedList')} label="Danh sách số" onClick={() => editor.chain().focus().toggleOrderedList().run()}>1≡</ToolButton>
                <ToolButton active={editor.isActive('blockquote')} label="Trích dẫn" onClick={() => editor.chain().focus().toggleBlockquote().run()}>❝</ToolButton>
                <Divider />
                <ToolButton active={editor.isActive('link')} label="Liên kết" onClick={setLink}>🔗</ToolButton>
                <ToolButton active={false} label="Chèn ảnh" onClick={() => fileRef.current?.click()}>
                    {uploading ? '…' : '🖼'}
                </ToolButton>
                <Divider />
                <ToolButton active={false} label="Hoàn tác" onClick={() => editor.chain().focus().undo().run()}>↺</ToolButton>
                <ToolButton active={false} label="Làm lại" onClick={() => editor.chain().focus().redo().run()}>↻</ToolButton>
            </div>

            <EditorContent editor={editor} />

            <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(e) => {
                    const f = e.target.files?.[0];
                    if (f) void uploadImage(f);
                    e.target.value = '';
                }}
            />
        </div>
    );
}

function ToolButton({
    active,
    label,
    onClick,
    children,
}: {
    active: boolean;
    label: string;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            onMouseDown={(e) => e.preventDefault() /* giữ focus trong editor */}
            onClick={onClick}
            className={`grid h-8 min-w-8 place-items-center rounded-[8px] px-1.5 text-[13px] transition ${
                active ? 'bg-grass text-white' : 'text-pine hover:bg-[#eef2e3]'
            }`}
        >
            {children}
        </button>
    );
}

function Divider() {
    return <span className="mx-1 h-5 w-px bg-[#e3e8d6]" />;
}
