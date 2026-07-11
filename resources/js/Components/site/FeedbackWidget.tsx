import { useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Widget góp ý (Epic 2): nút nổi góc phải-dưới mọi trang khách → modal form.
 * Cần ít nhất SĐT hoặc email để admin phản hồi (validate cả server).
 */
export default function FeedbackWidget() {
    const [open, setOpen] = useState(false);
    const [sent, setSent] = useState(false);

    const form = useForm({ name: '', phone: '', email: '', content: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/gop-y', {
            preserveScroll: true,
            onSuccess: () => {
                setSent(true);
                form.reset();
            },
        });
    };

    const close = () => {
        setOpen(false);
        setSent(false);
        form.clearErrors();
    };

    const inputCls = 'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';
    const err = (msg?: string) => msg && <p className="mt-1 text-[12px] text-[#b3493a]">{msg}</p>;

    return (
        <>
            {/* Nút nổi — góc phải dưới, không che nội dung mobile */}
            <button
                onClick={() => setOpen(true)}
                aria-label="Gửi góp ý"
                title="Gửi góp ý cho BỐP CAMPING"
                className="fixed bottom-5 right-5 z-[80] flex h-12 items-center gap-2 rounded-pill bg-pine px-4 text-[13.5px] font-bold text-white shadow-lg transition hover:-translate-y-0.5 hover:bg-grass"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                    <path d="M21 11.5a8.4 8.4 0 0 1-8.5 8.3c-1.4 0-2.8-.3-4-1L3 20l1.3-4.2a8 8 0 0 1-1.3-4.3A8.4 8.4 0 0 1 11.5 3 8.4 8.4 0 0 1 21 11.5Z" stroke="currentColor" strokeWidth="1.9" strokeLinejoin="round" />
                    <path d="M8.5 10.5h7M8.5 13.5h4.5" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" />
                </svg>
                <span className="hidden sm:inline">Góp ý</span>
            </button>

            {open && (
                <div className="fixed inset-0 z-[160] flex items-center justify-center bg-black/45 px-4" onClick={close}>
                    <div className="w-full max-w-[480px] rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        {sent ? (
                            <div className="py-4 text-center">
                                <div className="mx-auto mb-3 grid h-14 w-14 place-items-center rounded-full" style={{ background: '#dcebc4' }}>
                                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
                                        <path d="m5 12.5 4.5 4.5L19 7.5" stroke="#557A2B" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
                                    </svg>
                                </div>
                                <h2 className="mb-1.5 text-[18px] font-extrabold text-ink">Cảm ơn bạn đã góp ý!</h2>
                                <p className="mx-auto mb-5 max-w-[320px] text-[13.5px] leading-[1.6] text-moss">
                                    Tụi mình đã nhận được và sẽ phản hồi qua thông tin bạn để lại sớm nhất có thể.
                                </p>
                                <button onClick={close} className="h-11 rounded-control bg-grass px-6 text-[13.5px] font-bold text-white transition hover:bg-pine">
                                    Đóng
                                </button>
                            </div>
                        ) : (
                            <>
                                <div className="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-[18px] font-extrabold text-ink">Góp ý cho BỐP CAMPING</h2>
                                        <p className="mt-0.5 text-[12.5px] leading-[1.5] text-moss">
                                            Trải nghiệm chưa mượt chỗ nào, bạn cứ nói thẳng — tụi mình đọc hết đó!
                                        </p>
                                    </div>
                                    <button onClick={close} aria-label="Đóng" className="grid h-9 w-9 flex-none place-items-center rounded-full bg-[#f1f4ea] text-[18px] text-pine transition hover:bg-[#e3e8d6]">
                                        ×
                                    </button>
                                </div>

                                <form onSubmit={submit} className="space-y-3">
                                    <div>
                                        <input
                                            type="text"
                                            value={form.data.name}
                                            onChange={(e) => form.setData('name', e.target.value)}
                                            placeholder="Tên của bạn *"
                                            className={inputCls}
                                        />
                                        {err(form.errors.name)}
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div>
                                            <input
                                                type="tel"
                                                value={form.data.phone}
                                                onChange={(e) => form.setData('phone', e.target.value)}
                                                placeholder="Số điện thoại"
                                                className={inputCls}
                                            />
                                            {err(form.errors.phone)}
                                        </div>
                                        <div>
                                            <input
                                                type="email"
                                                value={form.data.email}
                                                onChange={(e) => form.setData('email', e.target.value)}
                                                placeholder="Email"
                                                className={inputCls}
                                            />
                                            {err(form.errors.email)}
                                        </div>
                                    </div>
                                    <p className="text-[12px] text-moss">Cần ít nhất SĐT hoặc email để tụi mình phản hồi bạn nhé.</p>
                                    <div>
                                        <textarea
                                            value={form.data.content}
                                            onChange={(e) => form.setData('content', e.target.value)}
                                            placeholder="Nội dung góp ý *"
                                            rows={4}
                                            className={inputCls}
                                        />
                                        {err(form.errors.content)}
                                    </div>
                                    <button
                                        type="submit"
                                        disabled={form.processing}
                                        className="h-12 w-full rounded-control bg-grass text-[14px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                                    >
                                        {form.processing ? 'Đang gửi…' : 'Gửi góp ý'}
                                    </button>
                                </form>
                            </>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
