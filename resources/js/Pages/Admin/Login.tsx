import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import Logo from '@/Components/Logo';

export default function AdminLogin() {
    const { data, setData, post, processing, errors } = useForm({
        phone: '',
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('admin.login.store'));
    };

    return (
        <>
            <Head title="Đăng nhập Admin" />
            <div className="flex min-h-screen items-center justify-center px-5" style={{ background: '#f4f6ef' }}>
                <div className="w-full max-w-[380px]">
                    {/* Logo */}
                    <div className="mb-8 flex flex-col items-center gap-3">
                        <Logo size={72} className="shadow-lg" />
                        <div className="text-center">
                            <div className="text-[22px] font-extrabold text-pine">BopCamping Admin</div>
                            <div className="mt-0.5 text-[13px] text-moss">Đăng nhập để quản trị</div>
                        </div>
                    </div>

                    {/* Form */}
                    <div className="rounded-[20px] border border-cardBorder bg-white p-7 shadow-sm">
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Số điện thoại
                                </label>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    autoFocus
                                    value={data.phone}
                                    onChange={(e) => setData('phone', e.target.value)}
                                    placeholder="0976544370"
                                    className={`h-12 w-full rounded-[11px] border bg-[#fafbf7] px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.phone ? 'border-red-400' : 'border-cardBorder'}`}
                                />
                                {errors.phone && (
                                    <p className="mt-1.5 text-[12px] text-red-500">{errors.phone}</p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Mật khẩu
                                </label>
                                <input
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                    className={`h-12 w-full rounded-[11px] border bg-[#fafbf7] px-3.5 text-[15px] text-ink outline-none focus:border-grass ${errors.password ? 'border-red-400' : 'border-cardBorder'}`}
                                />
                                {errors.password && (
                                    <p className="mt-1.5 text-[12px] text-red-500">{errors.password}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing || !data.phone || !data.password}
                                className="mt-1 h-12 w-full rounded-[11px] text-[15px] font-bold text-white transition disabled:cursor-not-allowed"
                                style={{ background: (!processing && data.phone && data.password) ? '#557A2B' : '#c4cfae' }}
                            >
                                {processing ? 'Đang đăng nhập…' : 'Đăng nhập'}
                            </button>
                        </form>
                    </div>

                    <p className="mt-5 text-center text-[12px] text-[#9aab82]">
                        BopCamping · Admin Panel
                    </p>
                </div>
            </div>
        </>
    );
}
