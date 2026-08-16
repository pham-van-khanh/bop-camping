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
            <div className="bg-[#eceae5] py-6 sm:py-10">
                <div className="mx-auto max-w-[820px] px-3 sm:px-4">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h1 className="text-lg font-semibold text-stone-800 sm:text-xl">
                                {stage_label}
                            </h1>
                            <p className="mt-0.5 text-sm text-stone-500">
                                Hợp đồng số {code} · {customer_name}
                            </p>
                        </div>
                        {has_pdf ? (
                            <a
                                href={pdfUrl}
                                className="rounded-md border border-stone-800 bg-stone-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-stone-700"
                            >
                                Tải bản PDF
                            </a>
                        ) : (
                            waitingForPdf && (
                                <span className="max-w-[16rem] rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm text-stone-500">
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
                    />

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
                        <p className="mt-5 rounded-md border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                            Hợp đồng đã ký đủ cả ba phần. Cảm ơn bạn!
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
            <div className="mx-auto max-w-md px-4 py-12">
                <h1 className="text-xl font-semibold text-stone-800">
                    Xác nhận để mở hợp đồng
                </h1>
                <p className="mt-2 text-sm text-stone-600">
                    {customerName ? `Chào ${customerName}. ` : ''}
                    Nhập <strong>4 số cuối</strong> của số điện thoại bạn đã
                    dùng để đặt đơn.
                </p>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        post(`/hop-dong/${token}/mo`, { preserveScroll: true });
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
                                e.target.value.replace(/\D/g, '').slice(0, 4),
                            )
                        }
                        className="w-full rounded-md border-stone-300 text-center text-2xl tracking-[0.5em]"
                        placeholder="••••"
                    />
                    {errors.last4 && (
                        <p className="mt-2 text-sm text-red-600">
                            {errors.last4}
                        </p>
                    )}
                    <button
                        type="submit"
                        disabled={data.last4.length !== 4 || processing}
                        className="mt-4 w-full rounded-md bg-stone-800 px-4 py-2.5 text-white disabled:opacity-40"
                    >
                        Mở hợp đồng
                    </button>
                </form>
            </div>
        </SiteLayout>
    );
}

function StageProgress({
    signed,
    labels,
}: {
    signed: Stage[];
    labels?: Record<Stage, string>;
}) {
    const order: Stage[] = ['main', 'handover', 'return'];

    return (
        <ol className="mt-4 flex flex-wrap gap-2 text-xs">
            {order.map((s) => (
                <li
                    key={s}
                    className={`rounded-full px-3 py-1 ${
                        signed.includes(s)
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-stone-100 text-stone-500'
                    }`}
                >
                    {signed.includes(s) ? '✓ ' : ''}
                    {labels?.[s] ?? s}
                </li>
            ))}
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
            className="contract-sheet mt-4 !p-6"
        >
            <h2 className="text-base font-semibold text-stone-800">
                Chữ ký của bạn
            </h2>
            <p className="mb-3 mt-1 text-sm text-stone-600">
                Ký tên nghĩa là bạn đã đọc và đồng ý toàn bộ nội dung ở trên.
            </p>

            <SignaturePadField onChange={handleChange} disabled={processing} />

            <input type="hidden" value={data.content_hash} readOnly />

            {errors.content_hash && (
                <p className="mt-3 text-sm text-red-600">
                    {errors.content_hash}
                </p>
            )}
            {stageError && (
                <p className="mt-3 text-sm text-red-600">{stageError}</p>
            )}
            {errors.signature && (
                <p className="mt-3 text-sm text-red-600">{errors.signature}</p>
            )}

            <button
                type="submit"
                disabled={!signature || processing}
                className="mt-4 w-full rounded-md bg-stone-800 px-4 py-2.5 text-white disabled:opacity-40"
            >
                {processing ? 'Đang lưu…' : 'Ký và lưu'}
            </button>
        </form>
    );
}
