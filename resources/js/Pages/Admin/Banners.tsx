import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';

type Banner = {
    id: number;
    placement: 'hero' | 'promo';
    image: string;
    title: string | null;
    subtitle: string | null;
    link_url: string | null;
    sort_order: number;
    is_active: boolean;
};

type FormData = {
    placement: 'hero' | 'promo';
    image: File | null;
    title: string;
    subtitle: string;
    link_url: string;
    sort_order: number | '';
    is_active: boolean;
};

const inputCls = 'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

export default function AdminBanners({ banners, placements }: { banners: Banner[]; placements: Record<string, string> }) {
    const { flash } = usePage<PageProps>().props;
    const placementKeys = Object.keys(placements) as Array<'hero' | 'promo'>;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Banner | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [toastMsg, setToastMsg] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const blank = (): FormData => ({ placement: placementKeys[0] ?? 'hero', image: null, title: '', subtitle: '', link_url: '', sort_order: '', is_active: true });
    const form = useForm<FormData>(blank());

    const openCreate = () => { form.setData(blank()); form.clearErrors(); setEditing(null); setModalMode('create'); };
    const openEdit = (b: Banner) => {
        form.setData({ placement: b.placement, image: null, title: b.title ?? '', subtitle: b.subtitle ?? '', link_url: b.link_url ?? '', sort_order: b.sort_order, is_active: b.is_active });
        form.clearErrors(); setEditing(b); setModalMode('edit');
    };
    const closeModal = () => { setModalMode(null); setEditing(null); form.reset(); };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { forceFormData: true, preserveScroll: true, onSuccess: closeModal };
        if (modalMode === 'create') form.post(route('admin.banners.store'), opts);
        else if (editing) {
            // PHP không nạp $_FILES cho PUT → POST + _method spoof để upload ảnh khi sửa.
            form.transform((d) => ({ ...d, _method: 'put' }));
            form.post(route('admin.banners.update', editing.id), opts);
        }
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.banners.destroy', deleteId), { preserveScroll: true, onSuccess: () => setDeleteId(null) });
    };

    const toggleActive = (b: Banner) => {
        const fd = new FormData();
        fd.append('_method', 'put');
        fd.append('placement', b.placement);
        fd.append('title', b.title ?? '');
        fd.append('subtitle', b.subtitle ?? '');
        fd.append('link_url', b.link_url ?? '');
        fd.append('sort_order', String(b.sort_order));
        fd.append('is_active', b.is_active ? '0' : '1');
        router.post(route('admin.banners.update', b.id), fd, { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title="Admin · Banner" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">✓ {toastMsg}</div>
            )}

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">Banner</h1>
                        <p className="mt-0.5 text-[13px] text-moss">Ảnh slideshow đầu trang (hero) và dải khuyến mãi (promo)</p>
                    </div>
                    <button onClick={openCreate} className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" /></svg>
                        Thêm banner
                    </button>
                </div>

                {placementKeys.map((pk) => {
                    const list = banners.filter((b) => b.placement === pk);
                    return (
                        <section key={pk} className="mb-7">
                            <h2 className="mb-3 text-[15px] font-bold text-pine">{placements[pk]} <span className="font-mono text-[12px] text-moss">({list.length})</span></h2>
                            {list.length === 0 ? (
                                <div className="rounded-[12px] border border-dashed border-cardBorder py-8 text-center text-[13px] text-moss">Chưa có banner ở vị trí này</div>
                            ) : (
                                <div className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' }}>
                                    {list.map((b) => (
                                        <div key={b.id} className="overflow-hidden rounded-[14px] border border-cardBorder bg-white">
                                            <div className="relative" style={{ aspectRatio: '16/7', background: '#eef2e3' }}>
                                                <img src={b.image} alt={b.title ?? ''} className="h-full w-full object-cover" />
                                                {!b.is_active && <span className="absolute left-2 top-2 rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-semibold text-white">Đang ẩn</span>}
                                                {b.link_url && <span className="absolute right-2 top-2 rounded-full bg-grass/90 px-2 py-1 text-[10.5px] font-semibold text-white">link</span>}
                                            </div>
                                            <div className="p-3.5">
                                                <div className="truncate text-[14px] font-bold text-pine">{b.title || <span className="text-moss">(không tiêu đề)</span>}</div>
                                                {b.subtitle && <div className="truncate text-[12px] text-moss">{b.subtitle}</div>}
                                                <div className="mt-3 flex items-center justify-between">
                                                    <label className="flex cursor-pointer items-center gap-2 text-[12.5px] text-pine">
                                                        <input type="checkbox" checked={b.is_active} onChange={() => toggleActive(b)} className="accent-grass" />
                                                        Hiện
                                                    </label>
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-mono text-[11px] text-moss">#{b.sort_order}</span>
                                                        <button onClick={() => openEdit(b)} className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass">Sửa</button>
                                                        <button onClick={() => setDeleteId(b.id)} className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]">Xoá</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </section>
                    );
                })}
            </div>

            {/* Create / Edit modal */}
            {modalMode && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" onClick={closeModal}>
                    <div className="w-full max-w-md rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">{modalMode === 'create' ? 'Thêm banner' : 'Sửa banner'}</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <Field label="Vị trí" required error={form.errors.placement}>
                                <select value={form.data.placement} onChange={(e) => form.setData('placement', e.target.value as FormData['placement'])} className={inputCls}>
                                    {placementKeys.map((k) => <option key={k} value={k}>{placements[k]}</option>)}
                                </select>
                            </Field>

                            <Field label={modalMode === 'create' ? 'Ảnh banner' : 'Ảnh (để trống = giữ ảnh cũ)'} required={modalMode === 'create'} error={form.errors.image}>
                                {modalMode === 'edit' && editing && (
                                    <img src={editing.image} alt="" className="mb-2 w-full rounded-[10px] border border-cardBorder object-cover" style={{ aspectRatio: '16/7' }} />
                                )}
                                <input type="file" accept="image/*" onChange={(e) => form.setData('image', e.target.files?.[0] ?? null)} className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine" />
                            </Field>

                            <Field label="Tiêu đề">
                                <input type="text" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className={inputCls} placeholder="VD: Ưu đãi cuối tuần" />
                            </Field>
                            <Field label="Mô tả phụ">
                                <input type="text" value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} className={inputCls} placeholder="Dòng nhỏ dưới tiêu đề (tuỳ chọn)" />
                            </Field>
                            <Field label="Link khi bấm (tuỳ chọn)" error={form.errors.link_url}>
                                <input type="text" value={form.data.link_url} onChange={(e) => form.setData('link_url', e.target.value)} className={inputCls} placeholder="/thiet-bi hoặc https://..." />
                            </Field>

                            <div className="flex items-center justify-between gap-3">
                                <label className="flex cursor-pointer items-center gap-2 text-[13px] text-pine">
                                    <input type="checkbox" checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} className="accent-grass" />
                                    Hiện banner
                                </label>
                                <div className="flex items-center gap-2">
                                    <span className="text-[12.5px] text-moss">Thứ tự</span>
                                    <input type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value === '' ? '' : Number(e.target.value))} className="w-20 rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none focus:border-grass" placeholder="0" />
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button type="button" onClick={closeModal} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">Huỷ</button>
                                <button type="submit" disabled={form.processing} className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60">{form.processing ? 'Đang lưu…' : 'Lưu lại'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete confirm */}
            {deleteId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Xác nhận xoá banner</h2>
                        <p className="mb-4 text-[13px] text-moss">Ảnh banner cũng sẽ bị xoá. Hành động không thể hoàn tác.</p>
                        <div className="flex justify-end gap-3">
                            <button onClick={() => setDeleteId(null)} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">Huỷ</button>
                            <button onClick={doDelete} className="rounded-[10px] bg-[#b3493a] px-5 py-2 text-[13px] font-bold text-white transition hover:bg-[#8a3328]">Xoá</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

function Field({ label, required, error, children }: { label: string; required?: boolean; error?: string; children: ReactNode }) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                {label} {required && <span className="text-[#b3493a]">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-[12px] text-[#b3493a]">{error}</p>}
        </div>
    );
}

AdminBanners.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
