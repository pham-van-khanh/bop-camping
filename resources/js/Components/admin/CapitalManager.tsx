import { money } from '@/lib/format';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Sổ góp vốn (bopcamping-n4qy) — ghi TỪNG LẦN góp, không phải một con số mỗi người.
 *
 * Nhờ vậy góp thêm về sau vẫn giữ dấu vết ai bỏ bao nhiêu lúc nào, và thêm thành viên
 * thứ ba chỉ là thêm dòng — mọi tỉ lệ chia lợi nhuận tự tính lại từ tổng.
 */

export type CapitalRow = {
    id: number;
    user_id: number;
    user_name: string;
    amount: number;
    contributed_on: string;
    contributed_on_label: string;
    note: string | null;
};

export type AdminOption = { id: number; name: string };

const todayISO = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const inputCls =
    'h-10 rounded-[10px] border border-cardBorder bg-white px-2.5 text-[13px] text-ink outline-none focus:border-grass';

export default function CapitalManager({
    rows,
    admins,
}: {
    rows: CapitalRow[];
    admins: AdminOption[];
}) {
    const [editId, setEditId] = useState<number | null>(null);
    const [confirmDelete, setConfirmDelete] = useState<number | null>(null);

    const form = useForm<{
        user_id: string;
        amount: string;
        contributed_on: string;
        note: string;
    }>({
        user_id: String(admins[0]?.id ?? ''),
        amount: '',
        contributed_on: todayISO(),
        note: '',
    });

    const resetForm = () => {
        setEditId(null);
        form.reset();
        form.setData('contributed_on', todayISO());
        form.clearErrors();
    };

    const startEdit = (r: CapitalRow) => {
        setEditId(r.id);
        form.setData({
            user_id: String(r.user_id),
            amount: String(r.amount),
            contributed_on: r.contributed_on,
            note: r.note ?? '',
        });
    };

    const submit = () => {
        const opts = { preserveScroll: true, onSuccess: () => resetForm() };
        if (editId) form.put(route('admin.capital.update', editId), opts);
        else form.post(route('admin.capital.store'), opts);
    };

    const remove = (id: number) =>
        router.delete(route('admin.capital.destroy', id), {
            preserveScroll: true,
            onFinish: () => setConfirmDelete(null),
        });

    const canSubmit =
        form.data.user_id !== '' &&
        Number(form.data.amount) > 0 &&
        form.data.contributed_on !== '' &&
        !form.processing;

    const total = rows.reduce((s, r) => s + r.amount, 0);
    const firstError =
        form.errors.user_id || form.errors.amount || form.errors.contributed_on;

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-1 flex items-baseline justify-between gap-3">
                <h2 className="text-[15px] font-bold text-ink">
                    Quản lý vốn góp
                </h2>
                <span className="text-[11.5px] text-moss">
                    Tổng{' '}
                    <span className="font-mono font-bold text-pine">
                        {money(total)}
                    </span>
                </span>
            </div>
            <p className="mb-4 text-[12px] leading-snug text-[#a3ad92]">
                Mỗi lần góp là một dòng. Thêm người góp vốn hoặc góp thêm thì tỉ
                lệ chia lợi nhuận tự tính lại.
            </p>

            <div className="mb-4 grid grid-cols-2 gap-2">
                <select
                    aria-label="Người góp vốn"
                    value={form.data.user_id}
                    onChange={(e) => form.setData('user_id', e.target.value)}
                    className={inputCls}
                >
                    {admins.map((a) => (
                        <option key={a.id} value={a.id}>
                            {a.name}
                        </option>
                    ))}
                </select>
                <input
                    type="number"
                    min={1}
                    aria-label="Số tiền góp"
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                    placeholder="Số tiền góp (đ)"
                    className={inputCls}
                />
                <input
                    type="date"
                    aria-label="Ngày góp"
                    value={form.data.contributed_on}
                    onChange={(e) =>
                        form.setData('contributed_on', e.target.value)
                    }
                    className={inputCls}
                />
                <input
                    aria-label="Ghi chú vốn góp"
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    placeholder="Ghi chú (vd: vốn ban đầu)"
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
                          ? 'Cập nhật vốn góp'
                          : 'Thêm vốn góp'}
                </button>
                {firstError && (
                    <p className="col-span-2 text-[12px] text-red-500">
                        {firstError}
                    </p>
                )}
                {editId && (
                    <button
                        onClick={resetForm}
                        className="col-span-2 text-[12px] font-semibold text-moss underline"
                    >
                        Huỷ sửa
                    </button>
                )}
            </div>

            {rows.length === 0 ? (
                <p className="py-4 text-center text-[13px] text-[#a3ad92]">
                    Chưa khai vốn góp nào. Mọi tỉ lệ chia lợi nhuận tính từ đây,
                    nên cần nhập trước.
                </p>
            ) : (
                <div className="max-h-[280px] overflow-y-auto">
                    <table className="w-full text-[12.5px]">
                        <tbody>
                            {rows.map((r) => (
                                <tr
                                    key={r.id}
                                    className="border-t border-[#f1f4ea]"
                                >
                                    <td className="py-2 pr-2 font-mono text-[11.5px] text-moss">
                                        {r.contributed_on_label}
                                    </td>
                                    <td className="py-2 pr-2 font-semibold text-pine">
                                        {r.user_name}
                                        {r.note && (
                                            <span className="ml-1.5 font-normal text-[#8a967a]">
                                                {r.note}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-2 pr-2 text-right font-mono font-semibold text-grass">
                                        {money(r.amount)}
                                    </td>
                                    <td className="py-2 text-right">
                                        {confirmDelete === r.id ? (
                                            <span className="inline-flex gap-1">
                                                <button
                                                    onClick={() => remove(r.id)}
                                                    className="rounded-[7px] bg-[#f6ddd6] px-2 py-1 text-[11px] font-bold text-[#b3493a]"
                                                >
                                                    Xoá
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setConfirmDelete(null)
                                                    }
                                                    className="rounded-[7px] border border-cardBorder px-2 py-1 text-[11px] font-semibold text-pine"
                                                >
                                                    Huỷ
                                                </button>
                                            </span>
                                        ) : (
                                            <span className="inline-flex gap-2">
                                                <button
                                                    onClick={() => startEdit(r)}
                                                    className="text-[11.5px] font-semibold text-pine hover:text-grass"
                                                >
                                                    Sửa
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        setConfirmDelete(r.id)
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
            )}
        </div>
    );
}
