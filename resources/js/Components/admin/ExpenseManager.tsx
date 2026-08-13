import { money } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Thêm/sửa/xoá khoản chi (bopcamping-n4qy).
 *
 * Trước đây khối này nằm trong Admin/Stats.tsx. Tách ra file riêng khi màn Tài chính
 * ra đời để chỉ có MỘT form nhập cho bảng `expenses` — chép sang màn thứ hai là sớm
 * muộn hai bên lệch validate rồi số liệu sai theo.
 */

export type ExpenseRow = {
    id: number;
    spent_on: string;
    spent_on_label: string;
    amount: number;
    category: string;
    category_label: string;
    note: string | null;
};

export type CategoryOption = { value: string; label: string; color: string };

const todayISO = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const inputCls =
    'h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass';

export default function ExpenseManager({
    expenses,
    categories,
    totalCount,
    canManage = true,
}: {
    expenses: ExpenseRow[];
    categories: CategoryOption[];
    /** Tổng số khoản trong kỳ — dùng để nói rõ khi danh sách bị cắt bớt. */
    totalCount?: number;
    /**
     * Có quyền sửa không (chỉ super admin — bopcamping-n4qy).
     *
     * Ẩn form/nút ở đây CHỈ là giao diện; chặn thật nằm ở middleware 'super-admin' trên
     * route ghi. Không có lớp server thì ai biết URL vẫn POST thẳng được.
     */
    canManage?: boolean;
}) {
    const [editId, setEditId] = useState<number | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<number | null>(null);

    const form = useForm<{
        spent_on: string;
        amount: string;
        category: string;
        note: string;
    }>({
        spent_on: todayISO(),
        amount: '',
        category: categories[0]?.value ?? 'other',
        note: '',
    });

    const resetForm = () => {
        setEditId(null);
        form.reset();
        form.setData('spent_on', todayISO());
        form.clearErrors();
    };

    const startEdit = (e: ExpenseRow) => {
        setEditId(e.id);
        form.setData({
            spent_on: e.spent_on,
            amount: String(e.amount),
            category: e.category,
            note: e.note ?? '',
        });
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: () => resetForm() };
        if (editId) form.put(route('admin.expenses.update', editId), opts);
        else form.post(route('admin.expenses.store'), opts);
    };

    const remove = (id: number) =>
        router.delete(route('admin.expenses.destroy', id), {
            preserveScroll: true,
            onFinish: () => setConfirmDelete(null),
        });

    const canSubmit =
        form.data.spent_on !== '' &&
        Number(form.data.amount) > 0 &&
        !form.processing;

    const hidden = (totalCount ?? expenses.length) - expenses.length;
    const colorOf = (value: string) =>
        categories.find((c) => c.value === value)?.color ?? '#8A8A7B';

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-3 flex items-center justify-between">
                <h2 className="text-[15px] font-bold text-ink">
                    {canManage ? 'Quản lý khoản chi' : 'Khoản chi'}
                </h2>
                {canManage && editId && (
                    <button
                        onClick={resetForm}
                        className="text-[12px] font-semibold text-moss underline"
                    >
                        Huỷ sửa
                    </button>
                )}
            </div>

            {!canManage && (
                <p className="mb-4 rounded-[10px] bg-[#f7f9f2] px-3 py-2 text-[12px] text-[#8a967a]">
                    Chỉ super admin được thêm/sửa/xoá khoản chi. Bạn vẫn xem
                    được đầy đủ số liệu.
                </p>
            )}

            {/* KHÔNG dùng thuộc tính hidden ở đây: class `grid` đặt display:grid sau
                quy tắc [hidden]{display:none} của preflight nên form vẫn hiện. Không
                render hẳn thì chắc chắn, lại không gửi markup form cho người không có quyền. */}
            {canManage && (
                <div className="mb-4 grid grid-cols-2 gap-2">
                    <input
                        type="date"
                        aria-label="Ngày chi"
                        value={form.data.spent_on}
                        onChange={(e) =>
                            form.setData('spent_on', e.target.value)
                        }
                        className={inputCls}
                    />
                    <input
                        type="number"
                        min={1}
                        aria-label="Số tiền"
                        value={form.data.amount}
                        onChange={(e) => form.setData('amount', e.target.value)}
                        placeholder="Số tiền (đ)"
                        className={inputCls}
                    />
                    <select
                        aria-label="Loại chi phí"
                        value={form.data.category}
                        onChange={(e) =>
                            form.setData('category', e.target.value)
                        }
                        className={inputCls}
                    >
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </select>
                    <input
                        aria-label="Ghi chú"
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        placeholder="Ghi chú"
                        maxLength={255}
                        className={inputCls}
                    />
                    <button
                        onClick={submit}
                        disabled={!canSubmit}
                        className="col-span-2 h-10 rounded-[10px] text-[13px] font-bold text-white transition disabled:cursor-not-allowed"
                        style={{
                            background: canSubmit ? '#557A2B' : '#c4cfae',
                        }}
                    >
                        {form.processing
                            ? 'Đang lưu…'
                            : editId
                              ? 'Cập nhật khoản chi'
                              : 'Thêm khoản chi'}
                    </button>
                    {(form.errors.amount ||
                        form.errors.spent_on ||
                        form.errors.category) && (
                        <p className="col-span-2 text-[12px] text-red-500">
                            {form.errors.amount ||
                                form.errors.spent_on ||
                                form.errors.category}
                        </p>
                    )}
                </div>
            )}

            {expenses.length === 0 ? (
                <p className="py-4 text-center text-[13px] text-[#a3ad92]">
                    Chưa có khoản chi nào trong kỳ.
                </p>
            ) : (
                <>
                    <div className="max-h-[320px] overflow-y-auto">
                        <table className="w-full text-[12.5px]">
                            <tbody>
                                {expenses.map((e) => (
                                    <tr
                                        key={e.id}
                                        className="border-t border-[#f1f4ea]"
                                    >
                                        <td className="py-2 pr-2 font-mono text-[11.5px] text-moss">
                                            {e.spent_on_label}
                                        </td>
                                        <td className="py-2 pr-2">
                                            <span
                                                className="rounded-pill px-1.5 py-0.5 text-[10.5px] font-bold text-white"
                                                style={{
                                                    background: colorOf(
                                                        e.category,
                                                    ),
                                                }}
                                            >
                                                {e.category_label}
                                            </span>
                                            {e.note && (
                                                <span className="ml-1.5 text-[#8a967a]">
                                                    {e.note}
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-2 pr-2 text-right font-mono font-semibold text-campfire">
                                            {money(e.amount)}
                                        </td>
                                        <td className="py-2 text-right">
                                            {!canManage ? null : confirmDelete ===
                                              e.id ? (
                                                <span className="inline-flex gap-1">
                                                    <button
                                                        onClick={() =>
                                                            remove(e.id)
                                                        }
                                                        className="rounded-[7px] bg-[#f6ddd6] px-2 py-1 text-[11px] font-bold text-[#b3493a]"
                                                    >
                                                        Xoá
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            setConfirmDelete(
                                                                null,
                                                            )
                                                        }
                                                        className="rounded-[7px] border border-cardBorder px-2 py-1 text-[11px] font-semibold text-pine"
                                                    >
                                                        Huỷ
                                                    </button>
                                                </span>
                                            ) : (
                                                <span className="inline-flex gap-2">
                                                    <button
                                                        onClick={() =>
                                                            startEdit(e)
                                                        }
                                                        className="text-[11.5px] font-semibold text-pine hover:text-grass"
                                                    >
                                                        Sửa
                                                    </button>
                                                    <button
                                                        onClick={() =>
                                                            setConfirmDelete(
                                                                e.id,
                                                            )
                                                        }
                                                        className="text-[11.5px] font-semibold text-[#b3493a] hover:underline"
                                                    >
                                                        Xoá
                                                    </button>
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {hidden > 0 && (
                        // Nói thẳng phần bị cắt: danh sách thiếu mà im lặng thì admin
                        // tưởng đã xem hết và cộng tay ra số sai.
                        <p className="mt-2 text-[11.5px] text-[#a3ad92]">
                            Đang hiện 200 khoản gần nhất — còn {hidden} khoản cũ
                            hơn không hiển thị (tổng chi phía trên vẫn tính đủ).
                        </p>
                    )}
                </>
            )}
        </div>
    );
}
