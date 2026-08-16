import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export type ContractStage = 'main' | 'handover' | 'return';

export type ContractBlock = {
    code: string;
    sign_url: string;
    signed_stages: ContractStage[];
    stage_labels: Record<ContractStage, string>;
    id_number: string | null;
    id_issued_on: string | null;
    id_issued_place: string | null;
    has_pdf: boolean;
};

const STAGE_ORDER: ContractStage[] = ['main', 'handover', 'return'];

/**
 * Khối hợp đồng điện tử trên màn chi tiết đơn (bopcamping-4jao).
 *
 * Chủ shop xem ảnh CCCD khách gửi qua Zalo rồi NHẬP TAY vào đây — hệ thống cố ý không nhận
 * upload ảnh CCCD, nên không phải hứa xoá và không ôm rủi ro dữ liệu cá nhân.
 */
export default function ContractPanel({
    orderId,
    contract,
    isParent,
}: {
    orderId: number;
    contract: ContractBlock | null;
    isParent: boolean;
}) {
    const [copied, setCopied] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        id_number: contract?.id_number ?? '',
        id_issued_on: contract?.id_issued_on ?? '',
        id_issued_place: contract?.id_issued_place ?? '',
    });

    // Đơn cha chỉ gom đợt, không có ngày/đồ riêng — hợp đồng lập trên từng đơn con.
    if (isParent) {
        return null;
    }

    const copyLink = async () => {
        if (!contract) return;
        await navigator.clipboard.writeText(contract.sign_url);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="rounded-[16px] border border-cardBorder bg-white p-5">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                    Hợp đồng điện tử
                </h2>
                {contract && (
                    <span className="font-mono text-[12px] text-moss">
                        {contract.code}
                    </span>
                )}
            </div>

            {contract && (
                <ol className="mb-4 flex flex-wrap gap-2 text-[11px]">
                    {STAGE_ORDER.map((s) => {
                        const done = contract.signed_stages.includes(s);
                        return (
                            <li
                                key={s}
                                className={`rounded-pill px-2.5 py-1 font-bold ${
                                    done
                                        ? 'bg-[#e7f3e0] text-grass'
                                        : 'bg-[#fdecec] text-[#c0392b]'
                                }`}
                            >
                                {done ? '✓ ' : '• Chưa ký — '}
                                {contract.stage_labels[s]}
                            </li>
                        );
                    })}
                </ol>
            )}

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.contracts.store', orderId), {
                        preserveScroll: true,
                    });
                }}
            >
                <p className="mb-2 text-[12px] text-moss">
                    Xem CCCD khách gửi qua Zalo rồi nhập tay. Hệ thống{' '}
                    <strong>không lưu ảnh CCCD</strong> — đúng như Điều 8 của
                    hợp đồng cam kết.
                </p>

                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="block">
                        <span className="text-[11px] font-bold text-moss">
                            Số CCCD
                        </span>
                        <input
                            value={data.id_number}
                            onChange={(e) =>
                                setData('id_number', e.target.value)
                            }
                            className="mt-1 w-full rounded-[10px] border-cardBorder font-mono text-[13px]"
                            placeholder="040202015437"
                        />
                    </label>
                    <label className="block">
                        <span className="text-[11px] font-bold text-moss">
                            Ngày cấp
                        </span>
                        <input
                            type="date"
                            value={data.id_issued_on}
                            onChange={(e) =>
                                setData('id_issued_on', e.target.value)
                            }
                            className="mt-1 w-full rounded-[10px] border-cardBorder text-[13px]"
                        />
                    </label>
                    <label className="block">
                        <span className="text-[11px] font-bold text-moss">
                            Nơi cấp
                        </span>
                        <input
                            value={data.id_issued_place}
                            onChange={(e) =>
                                setData('id_issued_place', e.target.value)
                            }
                            className="mt-1 w-full rounded-[10px] border-cardBorder text-[13px]"
                            placeholder="Cục CSQLHC về TTXH"
                        />
                    </label>
                </div>

                {(errors.id_number ||
                    errors.id_issued_on ||
                    errors.id_issued_place) && (
                    <p className="mt-2 text-[12px] font-semibold text-[#c0392b]">
                        {errors.id_number ??
                            errors.id_issued_on ??
                            errors.id_issued_place}
                    </p>
                )}

                <div className="mt-4 flex flex-wrap items-center gap-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-pill bg-grass px-4 py-2 text-[13px] font-bold text-white disabled:opacity-40"
                    >
                        {contract ? 'Cập nhật thông tin' : 'Lập hợp đồng'}
                    </button>

                    {contract && (
                        <>
                            <button
                                type="button"
                                onClick={copyLink}
                                className="rounded-pill border border-grass px-4 py-2 text-[13px] font-bold text-grass"
                            >
                                {copied ? 'Đã sao chép ✓' : 'Sao chép link ký'}
                            </button>
                            {contract.has_pdf && (
                                <a
                                    href={`${contract.sign_url}/pdf`}
                                    className="text-[13px] font-semibold text-pine underline"
                                >
                                    Tải PDF
                                </a>
                            )}
                        </>
                    )}
                </div>
            </form>

            {contract && (
                <p className="mt-3 break-all font-mono text-[11px] text-[#b0ba98]">
                    {contract.sign_url}
                </p>
            )}
        </div>
    );
}
