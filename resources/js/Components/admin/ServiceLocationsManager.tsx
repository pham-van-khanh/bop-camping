import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

export type ServiceLocation = {
    id: number;
    name: string;
    area: string | null;
    status: 'open' | 'coming';
    sort_order: number;
    spots_count: number;
};

type FormData = { name: string; area: string; status: 'open' | 'coming'; sort_order: number | '' };

const STATUS_LABEL: Record<ServiceLocation['status'], string> = { open: 'Đang mở', coming: 'Sắp mở' };
const inputCls = 'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

/** Quản lý vị trí phục vụ (Vinh, Hà Nội...) — nhúng trong màn Điểm cắm trại. */
export default function ServiceLocationsManager({ locations }: { locations: ServiceLocation[] }) {
    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<ServiceLocation | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);

    const blank = (): FormData => ({ name: '', area: '', status: 'open', sort_order: '' });
    const form = useForm<FormData>(blank());

    const openCreate = () => { form.setData(blank()); form.clearErrors(); setEditing(null); setModalMode('create'); };
    const openEdit = (l: ServiceLocation) => {
        form.setData({ name: l.name, area: l.area ?? '', status: l.status, sort_order: l.sort_order });
        form.clearErrors(); setEditing(l); setModalMode('edit');
    };
    const closeModal = () => { setModalMode(null); setEditing(null); form.reset(); };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: closeModal };
        if (modalMode === 'create') form.post(route('admin.service-locations.store'), opts);
        else if (editing) form.put(route('admin.service-locations.update', editing.id), opts);
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.service-locations.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => setDeleteId(null),
        });
    };

    const deleting = deleteId !== null ? locations.find((l) => l.id === deleteId) : null;

    return (
        <div className="mb-6 rounded-[16px] border border-cardBorder bg-white p-4">
            <div className="mb-3 flex items-center justify-between">
                <div>
                    <h2 className="text-[15px] font-bold text-pine">Vị trí phục vụ</h2>
                    <p className="text-[12px] text-moss">Nơi shop giao nhận — hiện ở panel "Đang phục vụ tại" trang chủ</p>
                </div>
                <button onClick={openCreate} className="flex items-center gap-1.5 rounded-[10px] border border-cardBorder bg-white px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" /></svg>
                    Thêm vị trí
                </button>
            </div>

            {locations.length === 0 ? (
                <div className="rounded-[10px] border border-dashed border-cardBorder py-5 text-center text-[12.5px] text-moss">Chưa có vị trí phục vụ nào</div>
            ) : (
                <div className="flex flex-wrap gap-2.5">
                    {locations.map((l) => (
                        <div key={l.id} className="group flex items-center gap-2.5 rounded-[12px] border border-cardBorder bg-[#fafcf7] px-3 py-2">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" className="text-grass"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7" /></svg>
                            <div className="leading-tight">
                                <div className="text-[13px] font-semibold text-pine">{l.name}</div>
                                <div className="text-[11px] text-moss">{l.area ?? '—'} · {l.spots_count} điểm</div>
                            </div>
                            <span className="rounded-full px-2 py-0.5 text-[10.5px] font-semibold" style={l.status === 'open' ? { background: '#dcebc4', color: '#3a5a1f' } : { background: '#f3ead3', color: '#8a6320' }}>
                                {STATUS_LABEL[l.status]}
                            </span>
                            <div className="ml-1 flex items-center gap-1 opacity-0 transition group-hover:opacity-100">
                                <button onClick={() => openEdit(l)} title="Sửa" className="grid h-6 w-6 place-items-center rounded-[7px] border border-cardBorder text-pine hover:border-grass hover:text-grass">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M4 20h4L18.5 9.5a2 2 0 0 0-3-3L5 17v3Z" stroke="currentColor" strokeWidth="1.7" strokeLinejoin="round" /></svg>
                                </button>
                                <button onClick={() => setDeleteId(l.id)} title="Xoá" className="grid h-6 w-6 place-items-center rounded-[7px] border border-[#f6ddd6] text-[#b3493a] hover:bg-[#f6ddd6]">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M5 7h14M10 11v6M14 11v6M6 7l1 12h10l1-12M9 7V4h6v3" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" /></svg>
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Create / Edit modal */}
            {modalMode && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" onClick={closeModal}>
                    <div className="w-full max-w-md rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">{modalMode === 'create' ? 'Thêm vị trí phục vụ' : 'Sửa vị trí phục vụ'}</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">Tên vị trí <span className="text-[#b3493a]">*</span></label>
                                <input type="text" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} className={inputCls} placeholder="VD: Vinh" autoFocus />
                                {form.errors.name && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">Khu vực</label>
                                <input type="text" value={form.data.area} onChange={(e) => form.setData('area', e.target.value)} className={inputCls} placeholder="VD: Nghệ An / Nội thành" />
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">Trạng thái <span className="text-[#b3493a]">*</span></label>
                                    <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value as FormData['status'])} className={inputCls}>
                                        <option value="open">Đang mở</option>
                                        <option value="coming">Sắp mở</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">Thứ tự</label>
                                    <input type="number" min="0" value={form.data.sort_order} onChange={(e) => form.setData('sort_order', e.target.value === '' ? '' : Number(e.target.value))} className={inputCls} placeholder="0" />
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
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Xác nhận xoá vị trí</h2>
                        <p className="mb-4 text-[13px] text-moss">
                            {deleting && deleting.spots_count > 0
                                ? `${deleting.spots_count} điểm cắm trại đang gắn vị trí này sẽ được gỡ liên kết (không bị xoá).`
                                : 'Hành động không thể hoàn tác.'}
                        </p>
                        <div className="flex justify-end gap-3">
                            <button onClick={() => setDeleteId(null)} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">Huỷ</button>
                            <button onClick={doDelete} className="rounded-[10px] bg-[#b3493a] px-5 py-2 text-[13px] font-bold text-white transition hover:bg-[#8a3328]">Xoá</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
