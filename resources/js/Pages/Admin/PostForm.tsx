import RichTextEditor from '@/Components/admin/RichTextEditor';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useState } from 'react';

type Status = 'draft' | 'published';
type Option = { id: number; name: string; active: boolean };

type EditablePost = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string | null;
    cover_url: string | null;
    status: Status;
    published_at: string | null;
    seo_title: string | null;
    seo_description: string | null;
    author_name: string | null;
    product_ids: number[];
    combo_ids: number[];
    is_public: boolean;
};

type FormData = {
    title: string;
    slug: string;
    excerpt: string;
    content: string;
    cover: File | null;
    remove_cover: boolean;
    status: Status;
    published_at: string;
    seo_title: string;
    seo_description: string;
    author_name: string;
    product_ids: number[];
    combo_ids: number[];
};

const inputCls =
    'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

function Field({
    label,
    hint,
    error,
    children,
    htmlFor,
}: {
    label: string;
    hint?: ReactNode;
    error?: string;
    children: ReactNode;
    htmlFor?: string;
}) {
    return (
        <div>
            <label
                htmlFor={htmlFor}
                className="mb-1.5 block text-[13px] font-semibold text-pine"
            >
                {label}
                {hint && (
                    <span className="ml-1 font-normal text-moss">{hint}</span>
                )}
            </label>
            {children}
            {error && (
                <p className="mt-1 text-[12px] text-[#b3493a]">{error}</p>
            )}
        </div>
    );
}

/** Đếm ký tự + cảnh báo khi lệch khoảng nên dùng cho SERP. */
function Counter({
    value,
    min,
    max,
}: {
    value: string;
    min?: number;
    max: number;
}) {
    const n = value.length;
    const bad = n > max || (min !== undefined && n > 0 && n < min);
    return (
        <span
            className={`font-mono text-[11.5px] ${bad ? 'text-campfire' : 'text-moss'}`}
        >
            {n}/{min ? `${min}–` : ''}
            {max}
        </span>
    );
}

/** Danh sách chọn nhiều có ô lọc — sản phẩm/combo liên quan. */
function MultiPicker({
    label,
    options,
    value,
    onChange,
    error,
}: {
    label: string;
    options: Option[];
    value: number[];
    onChange: (ids: number[]) => void;
    error?: string;
}) {
    const [q, setQ] = useState('');
    const shown = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return needle
            ? options.filter((o) => o.name.toLowerCase().includes(needle))
            : options;
    }, [q, options]);
    const toggle = (id: number) =>
        onChange(
            value.includes(id) ? value.filter((v) => v !== id) : [...value, id],
        );

    return (
        <Field label={label} hint={`(đã chọn ${value.length})`} error={error}>
            <input
                type="search"
                value={q}
                onChange={(e) => setQ(e.target.value)}
                placeholder="Lọc theo tên…"
                aria-label={`Lọc ${label.toLowerCase()}`}
                className={`${inputCls} mb-2`}
            />
            <div className="max-h-[220px] overflow-y-auto rounded-[10px] border border-cardBorder bg-white p-1.5">
                {shown.length === 0 && (
                    <p className="px-2 py-3 text-[12.5px] text-moss">
                        Không có mục nào.
                    </p>
                )}
                {shown.map((o) => (
                    <label
                        key={o.id}
                        className="flex cursor-pointer items-center gap-2 rounded-[8px] px-2 py-1.5 text-[13px] text-pine hover:bg-[#f1f4ea]"
                    >
                        <input
                            type="checkbox"
                            checked={value.includes(o.id)}
                            onChange={() => toggle(o.id)}
                            className="rounded border-cardBorder text-grass focus:ring-grass"
                        />
                        <span className="flex-1">{o.name}</span>
                        {!o.active && (
                            <span className="text-[11px] text-moss">
                                (đang ẩn — không hiện cho khách)
                            </span>
                        )}
                    </label>
                ))}
            </div>
        </Field>
    );
}

/**
 * Admin "Cẩm nang" (bopcamping-ennp): tạo / sửa một bài viết.
 * Nội dung soạn bằng RichTextEditor (TipTap) — server sanitize lại trước khi lưu.
 */
export default function AdminPostForm({
    post,
    products,
    combos,
    statuses,
}: {
    post: EditablePost | null;
    products: Option[];
    combos: Option[];
    statuses: Record<Status, string>;
}) {
    const { flash } = usePage<PageProps>().props;
    const [toastMsg, setToastMsg] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const form = useForm<FormData>({
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        excerpt: post?.excerpt ?? '',
        content: post?.content ?? '',
        cover: null,
        remove_cover: false,
        status: post?.status ?? 'draft',
        published_at: post?.published_at ?? '',
        seo_title: post?.seo_title ?? '',
        seo_description: post?.seo_description ?? '',
        author_name: post?.author_name ?? '',
        product_ids: post?.product_ids ?? [],
        combo_ids: post?.combo_ids ?? [],
    });
    const { data, setData, errors } = form;

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (post) {
            // PHP không nạp $_FILES cho PUT → POST + _method=put để upload ảnh bìa.
            form.transform((d) => ({
                ...d,
                remove_cover: d.remove_cover ? 1 : 0,
                _method: 'put',
            }));
            form.post(route('admin.posts.update', post.id), {
                forceFormData: true,
                preserveScroll: true,
            });
        } else {
            form.transform((d) => ({
                ...d,
                remove_cover: d.remove_cover ? 1 : 0,
            }));
            form.post(route('admin.posts.store'), { forceFormData: true });
        }
    };

    const errorList = Object.values(errors);

    return (
        <div className="p-6">
            <Head title={post ? `Sửa bài — ${post.title}` : 'Viết bài mới'} />
            <div className="mx-auto max-w-[1120px]">
                <div className="mb-1 text-[12.5px] text-moss">
                    <Link
                        href={route('admin.posts')}
                        className="font-semibold text-grass hover:underline"
                    >
                        Cẩm nang
                    </Link>
                    {' / '}
                    <span>{post ? 'Sửa bài' : 'Bài mới'}</span>
                </div>
                <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <h1 className="text-[22px] font-extrabold tracking-tight text-pine">
                        {post ? 'Sửa bài viết' : 'Viết bài mới'}
                    </h1>
                    {post?.is_public && (
                        <a
                            href={`/cam-nang/${post.slug}`}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                        >
                            Xem trang khách ↗
                        </a>
                    )}
                </div>

                {errorList.length > 0 && (
                    <div
                        role="alert"
                        className="mb-4 rounded-[10px] border border-[#e8c4bd] bg-[#fbeeeb] px-4 py-3 text-[13px] text-[#8a3a2d]"
                    >
                        Chưa lưu được — kiểm tra lại các ô báo lỗi bên dưới.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
                >
                    {/* Cột chính: nội dung */}
                    <div className="space-y-4">
                        <Field
                            label="Tiêu đề *"
                            htmlFor="post-title"
                            error={errors.title}
                            hint="(H1 của bài — đừng gõ thêm tên shop)"
                        >
                            <input
                                id="post-title"
                                type="text"
                                value={data.title}
                                onChange={(e) =>
                                    setData('title', e.target.value)
                                }
                                className={inputCls}
                            />
                        </Field>

                        <Field
                            label="Đường dẫn"
                            htmlFor="post-slug"
                            error={errors.slug}
                            hint="(bỏ trống = tự sinh từ tiêu đề)"
                        >
                            <div className="flex items-center rounded-[10px] border border-cardBorder focus-within:border-grass">
                                <span className="whitespace-nowrap pl-3.5 font-mono text-[12.5px] text-moss">
                                    /cam-nang/
                                </span>
                                <input
                                    id="post-slug"
                                    type="text"
                                    value={data.slug}
                                    onChange={(e) =>
                                        setData(
                                            'slug',
                                            e.target.value.toLowerCase(),
                                        )
                                    }
                                    placeholder="checklist-do-cam-trai"
                                    className="w-full border-0 bg-transparent px-1 py-2.5 font-mono text-[13px] outline-none focus:ring-0"
                                />
                            </div>
                        </Field>

                        <Field
                            label="Tóm tắt"
                            htmlFor="post-excerpt"
                            error={errors.excerpt}
                            hint="(hiện dưới tiêu đề + trên thẻ danh sách)"
                        >
                            <textarea
                                id="post-excerpt"
                                rows={3}
                                value={data.excerpt}
                                onChange={(e) =>
                                    setData('excerpt', e.target.value)
                                }
                                className={inputCls}
                            />
                            <div className="mt-1 text-right">
                                <Counter value={data.excerpt} max={500} />
                            </div>
                        </Field>

                        <Field
                            label="Nội dung"
                            error={errors.content}
                            hint="(dùng Tiêu đề 2/3 để chia mục — tiêu đề bài đã là H1)"
                        >
                            <RichTextEditor
                                value={data.content}
                                onChange={(html) => setData('content', html)}
                                minHeight={480}
                            />
                        </Field>
                    </div>

                    {/* Cột phụ: đăng bài, ảnh bìa, SEO, liên kết */}
                    <div className="space-y-4">
                        <section className="space-y-3 rounded-[14px] border border-cardBorder bg-white p-4">
                            <h2 className="text-[14px] font-extrabold text-pine">
                                Đăng bài
                            </h2>
                            <Field
                                label="Trạng thái"
                                htmlFor="post-status"
                                error={errors.status}
                            >
                                <select
                                    id="post-status"
                                    value={data.status}
                                    onChange={(e) =>
                                        setData(
                                            'status',
                                            e.target.value as Status,
                                        )
                                    }
                                    className={inputCls}
                                >
                                    {(
                                        Object.entries(statuses) as [
                                            Status,
                                            string,
                                        ][]
                                    ).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Ngày giờ đăng"
                                htmlFor="post-published-at"
                                error={errors.published_at}
                                hint="(trống = đăng ngay; ngày tương lai = hẹn giờ)"
                            >
                                <input
                                    id="post-published-at"
                                    type="datetime-local"
                                    value={data.published_at}
                                    onChange={(e) =>
                                        setData('published_at', e.target.value)
                                    }
                                    className={inputCls}
                                />
                            </Field>
                            <Field
                                label="Tác giả"
                                htmlFor="post-author"
                                error={errors.author_name}
                                hint="(trống = BỐP CAMPING)"
                            >
                                <input
                                    id="post-author"
                                    type="text"
                                    value={data.author_name}
                                    onChange={(e) =>
                                        setData('author_name', e.target.value)
                                    }
                                    className={inputCls}
                                />
                            </Field>
                        </section>

                        <section className="space-y-2 rounded-[14px] border border-cardBorder bg-white p-4">
                            <h2 className="text-[14px] font-extrabold text-pine">
                                Ảnh bìa
                            </h2>
                            {post?.cover_url && !data.remove_cover && (
                                <img
                                    src={post.cover_url}
                                    alt=""
                                    className="h-32 w-full rounded-[10px] border border-cardBorder object-cover"
                                />
                            )}
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                aria-label="Chọn ảnh bìa"
                                onChange={(e) =>
                                    setData(
                                        'cover',
                                        e.target.files?.[0] ?? null,
                                    )
                                }
                                className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[12.5px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine"
                            />
                            <p className="text-[11.5px] text-moss">
                                Ngang 16:9, rộng ≥1600px cho nét — hệ thống tự
                                sinh bản nhỏ cho điện thoại.
                            </p>
                            {post?.cover_url && (
                                <label className="flex items-center gap-2 text-[12.5px] text-pine">
                                    <input
                                        type="checkbox"
                                        checked={data.remove_cover}
                                        onChange={(e) =>
                                            setData(
                                                'remove_cover',
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded border-cardBorder text-grass focus:ring-grass"
                                    />
                                    Gỡ ảnh bìa hiện tại
                                </label>
                            )}
                            {errors.cover && (
                                <p className="text-[12px] text-[#b3493a]">
                                    {errors.cover}
                                </p>
                            )}
                        </section>

                        <section className="space-y-3 rounded-[14px] border border-cardBorder bg-white p-4">
                            <h2 className="text-[14px] font-extrabold text-pine">
                                SEO{' '}
                                <span className="text-[12px] font-normal text-moss">
                                    (tuỳ chọn)
                                </span>
                            </h2>
                            <Field
                                label="Tiêu đề SEO"
                                htmlFor="post-seo-title"
                                error={errors.seo_title}
                            >
                                <input
                                    id="post-seo-title"
                                    type="text"
                                    value={data.seo_title}
                                    onChange={(e) =>
                                        setData('seo_title', e.target.value)
                                    }
                                    placeholder={data.title}
                                    className={inputCls}
                                />
                                <div className="mt-1 flex justify-between text-[11.5px] text-moss">
                                    <span>Tự nối “| BỐP CAMPING”</span>
                                    <Counter value={data.seo_title} max={45} />
                                </div>
                            </Field>
                            <Field
                                label="Mô tả SEO"
                                htmlFor="post-seo-desc"
                                error={errors.seo_description}
                            >
                                <textarea
                                    id="post-seo-desc"
                                    rows={4}
                                    value={data.seo_description}
                                    onChange={(e) =>
                                        setData(
                                            'seo_description',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Trống = dùng phần Tóm tắt"
                                    className={inputCls}
                                />
                                <div className="mt-1 text-right">
                                    <Counter
                                        value={data.seo_description}
                                        min={120}
                                        max={160}
                                    />
                                </div>
                            </Field>
                        </section>

                        <section className="space-y-3 rounded-[14px] border border-cardBorder bg-white p-4">
                            <h2 className="text-[14px] font-extrabold text-pine">
                                Đồ dùng trong bài
                            </h2>
                            <p className="text-[11.5px] text-moss">
                                Hiện cuối bài kèm nút “Thuê ngay”.
                            </p>
                            <MultiPicker
                                label="Combo"
                                options={combos}
                                value={data.combo_ids}
                                onChange={(ids) => setData('combo_ids', ids)}
                                error={errors.combo_ids}
                            />
                            <MultiPicker
                                label="Sản phẩm"
                                options={products}
                                value={data.product_ids}
                                onChange={(ids) => setData('product_ids', ids)}
                                error={errors.product_ids}
                            />
                        </section>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-cardBorder pt-4 lg:col-span-2">
                        <div>
                            {post && (
                                <button
                                    type="button"
                                    onClick={() => setConfirmDelete(true)}
                                    className="text-[13px] font-semibold text-[#b3493a] hover:underline"
                                >
                                    Xoá bài viết
                                </button>
                            )}
                        </div>
                        <div className="flex items-center gap-3">
                            <Link
                                href={route('admin.posts')}
                                className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                            >
                                Quay lại
                            </Link>
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-[10px] bg-grass px-6 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                            >
                                {form.processing ? 'Đang lưu…' : 'Lưu bài'}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {confirmDelete && post && (
                <div
                    className="fixed inset-0 z-[90] grid place-items-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="confirm-delete-title"
                >
                    <div className="w-full max-w-[380px] rounded-[16px] bg-white p-6 shadow-xl">
                        <h2
                            id="confirm-delete-title"
                            className="text-[16px] font-extrabold text-pine"
                        >
                            Xoá bài viết này?
                        </h2>
                        <p className="mt-1.5 text-[13.5px] text-moss">
                            Không hoàn tác được. Muốn ẩn tạm thì chuyển về Bản
                            nháp.
                        </p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setConfirmDelete(false)}
                                className="rounded-[10px] border border-cardBorder px-4 py-2 text-[13px] font-semibold text-pine"
                            >
                                Huỷ
                            </button>
                            <button
                                type="button"
                                onClick={() =>
                                    router.delete(
                                        route('admin.posts.destroy', post.id),
                                    )
                                }
                                className="rounded-[10px] bg-[#b3493a] px-4 py-2 text-[13px] font-bold text-white"
                            >
                                Xoá bài
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {toastMsg && (
                <div className="fixed bottom-6 left-1/2 z-[90] -translate-x-1/2 rounded-pill bg-pine px-5 py-2.5 text-[13.5px] font-semibold text-white shadow-lg">
                    {toastMsg}
                </div>
            )}
        </div>
    );
}

AdminPostForm.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
