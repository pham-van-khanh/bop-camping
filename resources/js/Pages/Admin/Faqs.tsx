import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';

type Faq = {
    id: number;
    question: string;
    answer: string;
    sort_order: number;
    is_active: boolean;
};

export default function AdminFaqs({ faqs }: { faqs: Faq[] }) {
    const { flash } = usePage<PageProps>().props;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Faq | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [toastMsg, setToastMsg] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const form = useForm({
        question: '',
        answer: '',
        sort_order: 0 as number | '',
        is_active: true,
    });

    const openCreate = () => {
        form.setData({ question: '', answer: '', sort_order: faqs.length + 1, is_active: true });
        form.clearErrors();
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (faq: Faq) => {
        form.setData({ question: faq.question, answer: faq.answer, sort_order: faq.sort_order, is_active: faq.is_active });
        form.clearErrors();
        setEditing(faq);
        setModalMode('edit');
    };

    const closeModal = () => {
        setModalMode(null);
        setEditing(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, sort_order: data.sort_order === '' ? 0 : Number(data.sort_order) }));
        const opts = { preserveScroll: true, onSuccess: closeModal };
        if (modalMode === 'create') {
            form.post(route('admin.faqs.store'), opts);
        } else if (editing) {
            form.put(route('admin.faqs.update', editing.id), opts);
        }
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.faqs.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => setDeleteId(null),
        });
    };

    return (
        <>
            <Head title="Admin · FAQ" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">Câu hỏi thường gặp</h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">{faqs.length}</span> câu · hiển thị ở trang chủ theo thứ tự · tắt để ẩn tạm
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
                        </svg>
                        Thêm câu hỏi
                    </button>
                </div>

                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {faqs.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">❓</div>
                            <div className="text-[14px]">Chưa có câu hỏi nào</div>
                            <button onClick={openCreate} className="mt-3 text-[13px] font-semibold text-grass underline">
                                Thêm câu hỏi đầu tiên
                            </button>
                        </div>
                    ) : (
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                    <th className="w-16 px-4 py-3 text-center font-semibold text-moss">Thứ tự</th>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">Câu hỏi</th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss lg:table-cell">Trả lời</th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">Hiển thị</th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {faqs.map((faq) => (
                                    <tr key={faq.id} className="border-b border-[#f1f4ea] last:border-0 hover:bg-[#fafcf7]">
                                        <td className="px-4 py-3 text-center font-mono text-moss">{faq.sort_order}</td>
                                        <td className="px-4 py-3 font-semibold text-pine">{faq.question}</td>
                                        <td className="hidden max-w-[360px] truncate px-4 py-3 text-moss lg:table-cell">{faq.answer}</td>
                                        <td className="px-4 py-3 text-center">
                                            {faq.is_active ? (
                                                <span className="rounded-pill bg-[#dcebc4] px-2.5 py-1 text-[11px] font-bold text-[#3a5a1f]">Đang hiện</span>
                                            ) : (
                                                <span className="rounded-pill bg-[#f1f4ea] px-2.5 py-1 text-[11px] font-bold text-moss">Đang ẩn</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => openEdit(faq)}
                                                    className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                >
                                                    Sửa
                                                </button>
                                                <button
                                                    onClick={() => setDeleteId(faq.id)}
                                                    className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                >
                                                    Xoá
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Create / Edit Modal */}
            {modalMode && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" onClick={closeModal}>
                    <div className="w-full max-w-lg rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">
                            {modalMode === 'create' ? 'Thêm câu hỏi' : 'Sửa câu hỏi'}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Câu hỏi <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.question}
                                    onChange={(e) => form.setData('question', e.target.value)}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="VD: Có phải trả tiền trước không?"
                                    autoFocus
                                />
                                {form.errors.question && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.question}</p>}
                            </div>

                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Trả lời <span className="text-[#b3493a]">*</span>
                                </label>
                                <textarea
                                    value={form.data.answer}
                                    onChange={(e) => form.setData('answer', e.target.value)}
                                    rows={4}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="Nội dung trả lời cho khách"
                                />
                                {form.errors.answer && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.answer}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">Thứ tự hiển thị</label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.sort_order}
                                        onChange={(e) => form.setData('sort_order', e.target.value === '' ? '' : Number(e.target.value))}
                                        className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    />
                                    <p className="mt-1 text-[11px] text-[#a3ad92]">Nhỏ = lên trước</p>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">Trạng thái</label>
                                    <label className="flex cursor-pointer items-center gap-2 rounded-[10px] border border-cardBorder px-3.5 py-2.5">
                                        <input
                                            type="checkbox"
                                            checked={form.data.is_active}
                                            onChange={(e) => form.setData('is_active', e.target.checked)}
                                            className="accent-grass"
                                        />
                                        <span className="text-[13px] text-pine">Hiện ở trang chủ</span>
                                    </label>
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                                >
                                    Huỷ
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                                >
                                    {form.processing ? 'Đang lưu…' : 'Lưu lại'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Confirm */}
            {deleteId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Xác nhận xoá</h2>
                        <p className="mb-4 text-[13px] text-moss">Xoá câu hỏi này khỏi trang chủ? Hành động không thể hoàn tác.</p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setDeleteId(null)}
                                className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                            >
                                Huỷ
                            </button>
                            <button
                                onClick={doDelete}
                                className="rounded-[10px] bg-[#b3493a] px-5 py-2 text-[13px] font-bold text-white transition hover:bg-[#8a3328]"
                            >
                                Xoá
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

AdminFaqs.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
