import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';

type Location = {
    id: number;
    name: string;
    area: string | null;
    status: 'open' | 'coming';
    sort_order: number;
    spots_count: number;
};

type FormData = {
    name: string;
    area: string;
    status: 'open' | 'coming';
    sort_order: number | '';
};

const STATUS_LABEL: Record<Location['status'], string> = { open: 'Đang mở', coming: 'Sắp mở' };

export default function AdminServiceLocations({ locations }: { locations: Location[] }) {
    const { flash } = usePage<PageProps>().props;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Location | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [toastMsg, setToastMsg] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const blank = (): FormData => ({ name: '', area: '', status: 'open', sort_order: '' });
    const form = useForm<FormData>(blank());

    const openCreate = () => { form.setData(blank()); form.clearErrors(); setEditing(null); setModalMode('create'); };
    const openEdit = (l: Location) => {
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
        <>
            <Head title="Admin · Vị trí phục vụ" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">✓ {toastMsg}</div>
            )}

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">Vị trí phục vụ</h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            Nơi shop giao nhận đồ thuê — hiện ở panel "Đang phục vụ tại" trang chủ
                        </p>
                    </div>
                    <button onClick={openCreate} className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" /></svg>
                        Thêm vị trí
                    </button>
                </div>

                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {locations.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">📍</div>
                            <div className="text-[14px]">Chưa có vị trí phục vụ nào</div>
                            <button onClick={openCreate} className="mt-3 text-[13px] font-semibold text-grass underline">Thêm vị trí đầu tiên</button>
                        </div>
                    ) : (
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">Tên vị trí</th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss sm:table-cell">Khu vực</th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">Trạng thái</th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss md:table-cell">Điểm gắn</th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss lg:table-cell">Thứ tự</th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {locations.map((l) => (
                                    <tr key={l.id} className="border-b border-[#f1f4ea] hover:bg-[#fafcf7]">
                                        <td className="px-4 py-3 font-semibold text-pine">{l.name}</td>
                                        <td className="hidden px-4 py-3 text-moss sm:table-cell">{l.area ?? <span className="text-[#c4cca8]">—</span>}</td>
                                        <td className="px-4 py-3 text-center">
                                            <span
                                                className="rounded-full px-2.5 py-1 text-[11.5px] font-semibold"
                                                style={l.status === 'open' ? { background: '#dcebc4', color: '#3a5a1f' } : { background: '#f3ead3', color: '#8a6320' }}
                                            >
                                                {STATUS_LABEL[l.status]}
                                            </span>
                                        </td>
                                        <td className="hidden px-4 py-3 text-center font-mono text-moss md:table-cell">{l.spots_count}</td>
                                        <td className="hidden px-4 py-3 text-center font-mono text-moss lg:table-cell">{l.sort_order}</td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button onClick={() => openEdit(l)} className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass">Sửa</button>
                                                <button onClick={() => setDeleteId(l.id)} className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]">Xoá</button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

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
        </>
    );
}

const inputCls = 'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

AdminServiceLocations.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
