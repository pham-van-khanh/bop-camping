import SignaturePadField from '@/Components/SignaturePadField';
import SiteLayout from '@/Layouts/SiteLayout';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';

type Stage = 'main' | 'handover' | 'return';

type Props = PageProps<{
    unlocked: boolean;
    token: string;
    customer_name?: string;
    code?: string;
    /** null = đã ký đủ cả ba giai đoạn. */
    stage?: Stage | null;
    stage_label?: string;
    content_html?: string;
    content_hash?: string;
    signed_stages?: Stage[];
    stage_labels?: Record<Stage, string>;
    has_pdf?: boolean;
}>;

/**
 * Trang ký hợp đồng của khách (bopcamping-4jao) — một link cho cả ba giai đoạn ký.
 *
 * Hai trạng thái: chưa mở khoá (chỉ có ô nhập 4 số cuối SĐT) và đã mở khoá (đọc + ký).
 */
export default function Contract({
    unlocked,
    token,
    customer_name,
    code,
    stage,
    stage_label,
    content_html,
    content_hash,
    signed_stages = [],
    stage_labels,
    has_pdf,
}: Props) {
    const pdfUrl = `/hop-dong/${token}/pdf`;

    // PDF sinh ở JOB NỀN (xem GenerateContractPdf), nên ngay sau khi bấm Ký thì file chưa
    // có. Không dò lại thì khách nhìn thấy một khoảng trống và tưởng hệ thống nuốt mất bản
    // hợp đồng. Dò vài nhịp rồi thôi — hết nhịp vẫn còn bản gửi qua email.
    const hasSigned = signed_stages.length > 0;
    const waitingForPdf = unlocked && hasSigned && !has_pdf;
    const [pdfGaveUp, setPdfGaveUp] = useState(false);

    useEffect(() => {
        if (!waitingForPdf) return;

        setPdfGaveUp(false);
        let tries = 0;
        const timer = window.setInterval(() => {
            tries += 1;
            if (tries > 10) {
                window.clearInterval(timer);
                // Dò 30 giây không thấy thì gần như chắc chắn queue worker không chạy. Nói
                // thật với khách thay vì để chữ "đang tạo" quay mãi — hợp đồng đã ký xong
                // rồi, thiếu mỗi file, và file vẫn sẽ tới qua email.
                setPdfGaveUp(true);
                return;
            }
            router.reload({ only: ['has_pdf'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [waitingForPdf]);

    if (!unlocked) {
        return <LockGate token={token} customerName={customer_name} />;
    }

    return (
        <SiteLayout>
            <Head title={`Hợp đồng ${code ?? ''}`} />
            {/* Nền xám để "tờ giấy" trắng nổi lên — khách phải cảm thấy đang đọc một
                VĂN BẢN, không phải một trang web. */}
            <div className="bg-[#eceae5] py-5 sm:py-9">
                <div className="mx-auto max-w-[860px] px-3 sm:px-4">
                    {/* Thẻ đầu trang: gom danh tính hợp đồng + việc cần làm + nút PDF vào
                        một chỗ, để khách không phải đoán mình đang ở đâu trong quy trình. */}
                    <div className="rounded-card border border-cardBorder bg-card p-4 sm:p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="font-mono text-[11px] uppercase tracking-[0.16em] text-campfire">
                                    Hợp đồng {code}
                                </p>
                                <h1 className="mt-1 text-lg font-bold leading-snug text-pine sm:text-xl">
                                    {stage_label}
                                </h1>
                                <p className="mt-0.5 text-sm text-moss">
                                    Bên thuê: {customer_name}
                                </p>
                            </div>
                            {has_pdf ? (
                                <a
                                    href={pdfUrl}
                                    className="shrink-0 rounded-pill bg-grass px-4 py-2 text-[13px] font-bold text-white shadow-btn transition hover:bg-grass-light"
                                >
                                    Tải bản PDF
                                </a>
                            ) : (
                                waitingForPdf && (
                                    <span className="max-w-[15rem] shrink-0 rounded-pill border border-cardBorder bg-white px-3.5 py-2 text-[12px] text-moss">
                                        {pdfGaveUp
                                            ? 'Bản PDF sẽ được gửi vào email của bạn.'
                                            : 'Đang tạo bản PDF…'}
                                    </span>
                                )
                            )}
                        </div>

                        <StageProgress
                            signed={signed_stages}
                            labels={stage_labels}
                            current={stage ?? null}
                        />
                    </div>

                    <article
                        className="contract-sheet contract-doc mt-4"
                        // Nội dung do ADMIN soạn, đã qua EditorHtml::clean() (HTMLPurifier) lúc
                        // lưu, và mọi giá trị của khách đều qua e() trong ContractService. Khách
                        // KHÔNG chèn được HTML vào đây.
                        dangerouslySetInnerHTML={{ __html: content_html ?? '' }}
                    />

                    {stage ? (
                        <SignForm
                            token={token}
                            stage={stage}
                            contentHash={content_hash ?? ''}
                        />
                    ) : (
                        <p className="mt-4 rounded-card border border-grass bg-[#f2f7ec] px-5 py-4 text-[14px] font-semibold text-grass">
                            ✓ Hợp đồng đã ký đủ cả ba phần. Cảm ơn bạn!
                        </p>
                    )}
                </div>
            </div>
        </SiteLayout>
    );
}

function LockGate({
    token,
    customerName,
}: {
    token: string;
    customerName?: string;
}) {
    const { data, setData, post, processing, errors } = useForm({ last4: '' });

    return (
        <SiteLayout>
            <Head title="Mở hợp đồng" />
            <div className="bg-[#eceae5] py-10 sm:py-16">
                <div className="mx-auto max-w-md px-4">
                    <div className="rounded-card border border-cardBorder bg-card p-6 sm:p-7">
                        <p className="font-mono text-[11px] uppercase tracking-[0.16em] text-campfire">
                            Hợp đồng thuê thiết bị
                        </p>
                        <h1 className="mt-1.5 text-xl font-bold text-pine">
                            Xác nhận để mở hợp đồng
                        </h1>
                        <p className="mt-2 text-[13.5px] leading-relaxed text-moss">
                            {customerName ? `Chào ${customerName}. ` : ''}
                            Nhập <strong>4 số cuối</strong> số điện thoại bạn đã
                            dùng để đặt đơn.
                        </p>

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                post(`/hop-dong/${token}/mo`, {
                                    preserveScroll: true,
                                });
                            }}
                            className="mt-5"
                        >
                            <label htmlFor="last4" className="sr-only">
                                Bốn số cuối số điện thoại
                            </label>
                            <input
                                id="last4"
                                inputMode="numeric"
                                autoComplete="off"
                                maxLength={4}
                                value={data.last4}
                                onChange={(e) =>
                                    setData(
                                        'last4',
                                        e.target.value
                                            .replace(/\D/g, '')
                                            .slice(0, 4),
                                    )
                                }
                                className="w-full rounded-control border-cardBorder bg-white py-3 text-center text-2xl tracking-[0.5em] text-pine focus:border-grass focus:ring-grass"
                                placeholder="••••"
                            />
                            {errors.last4 && (
                                <p className="mt-2 rounded-control border border-[#e9c4c4] bg-[#fdf2f2] px-3 py-2 text-[13px] text-[#a03028]">
                                    {errors.last4}
                                </p>
                            )}
                            <button
                                type="submit"
                                disabled={data.last4.length !== 4 || processing}
                                className="mt-4 w-full rounded-pill bg-grass px-4 py-3 text-[15px] font-bold text-white shadow-btn transition hover:bg-grass-light disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
                            >
                                Mở hợp đồng
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </SiteLayout>
    );
}

const STEP_NUMBER: Record<Stage, number> = { main: 1, handover: 2, return: 3 };

/** Nhãn ngắn cho thanh tiến trình — tên đầy đủ quá dài, xuống 3 dòng trên điện thoại. */
const SHORT_LABEL: Record<Stage, string> = {
    main: 'Hợp đồng',
    handover: 'Bàn giao',
    return: 'Nhận lại',
};

/**
 * Thanh ba bước ký. Khách phải nhìn một cái là biết mình đang ở đâu và còn phải làm gì —
 * hợp đồng này ký làm ba lần, cách nhau nhiều ngày, nên rất dễ quên.
 */
function StageProgress({
    signed,
    labels,
    current,
}: {
    signed: Stage[];
    labels?: Record<Stage, string>;
    current: Stage | null;
}) {
    const order: Stage[] = ['main', 'handover', 'return'];

    return (
        <ol className="mt-4 flex items-start gap-1 border-t border-cardBorder pt-4">
            {order.map((s, i) => {
                const done = signed.includes(s);
                const active = current === s;

                return (
                    <li
                        key={s}
                        className="flex flex-1 flex-col items-center gap-1.5 text-center"
                        aria-current={active ? 'step' : undefined}
                    >
                        <div className="flex w-full items-center">
                            {/* Đường nối: vẽ ở hai bên chấm để thành một dải liền mạch. */}
                            <span
                                className={`h-[2px] flex-1 ${i === 0 ? 'bg-transparent' : done || active ? 'bg-grass' : 'bg-cardBorder'}`}
                            />
                            <span
                                className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold ${
                                    done
                                        ? 'bg-grass text-white'
                                        : active
                                          ? 'border-2 border-grass bg-white text-grass'
                                          : 'border border-cardBorder bg-white text-[#b0ba98]'
                                }`}
                            >
                                {done ? '✓' : i + 1}
                            </span>
                            <span
                                className={`h-[2px] flex-1 ${i === order.length - 1 ? 'bg-transparent' : done ? 'bg-grass' : 'bg-cardBorder'}`}
                            />
                        </div>
                        <span
                            title={labels?.[s]}
                            className={`text-[11px] leading-tight sm:text-[12px] ${
                                active
                                    ? 'font-bold text-pine'
                                    : done
                                      ? 'font-semibold text-grass'
                                      : 'text-[#9aa585]'
                            }`}
                        >
                            {SHORT_LABEL[s]}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}

function SignForm({
    token,
    stage,
    contentHash,
}: {
    token: string;
    stage: Stage;
    contentHash: string;
}) {
    const [signature, setSignature] = useState<string | null>(null);
    const { post, processing, errors, setData, data } = useForm<{
        signature: string;
        content_hash: string;
    }>({ signature: '', content_hash: contentHash });

    // Lỗi 'stage' (đã ký rồi / chưa tới lượt / biên bản thiếu ô) không ứng với field nào của
    // form nên không nằm trong `errors` đã định kiểu — đọc từ prop errors chung của trang.
    const pageErrors = usePage().props.errors as Record<string, string>;
    const stageError = pageErrors?.stage;

    const handleChange = useCallback(
        (dataUrl: string | null) => {
            setSignature(dataUrl);
            setData('signature', dataUrl ?? '');
        },
        [setData],
    );

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`/hop-dong/${token}/ky/${stage}`, {
                    preserveScroll: true,
                });
            }}
            className="mt-4 rounded-card border-2 border-grass bg-card p-5 sm:p-6"
        >
            <div className="flex items-center gap-2">
                <span className="rounded-pill bg-grass px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white">
                    Bước {STEP_NUMBER[stage]}/3
                </span>
                <h2 className="text-[15px] font-bold text-pine">
                    Ký {SHORT_LABEL[stage].toLowerCase()}
                </h2>
            </div>
            <p className="mb-4 mt-1.5 text-[13px] leading-relaxed text-moss">
                Ký tên nghĩa là bạn đã đọc và đồng ý toàn bộ nội dung ở trên.
                Bản đã ký sẽ được gửi vào email của bạn.
            </p>

            <SignaturePadField onChange={handleChange} disabled={processing} />

            <input type="hidden" value={data.content_hash} readOnly />

            {[errors.content_hash, stageError, errors.signature]
                .filter(Boolean)
                .map((message) => (
                    <p
                        key={message}
                        className="mt-3 rounded-control border border-[#e9c4c4] bg-[#fdf2f2] px-3 py-2 text-[13px] text-[#a03028]"
                    >
                        {message}
                    </p>
                ))}

            <button
                type="submit"
                disabled={!signature || processing}
                className="mt-4 w-full rounded-pill bg-grass px-4 py-3 text-[15px] font-bold text-white shadow-btn transition hover:bg-grass-light disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
            >
                {processing ? 'Đang lưu…' : 'Ký và lưu'}
            </button>
            {!signature && !processing && (
                <p className="mt-2 text-center text-[12px] text-[#9aa585]">
                    Hãy ký vào khung ở trên để bật nút này.
                </p>
            )}
        </form>
    );
}
