import MagazineContent from '@/Components/site/MagazineContent';
import SiteLayout from '@/Layouts/SiteLayout';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

/**
 * Trang giới thiệu /gioi-thieu (Epic 4) — nội dung sửa trong admin "Trang nội dung".
 * Thân trang render bố cục magazine (text/ảnh xen kẽ) như khối chi tiết sản phẩm.
 */
export default function About({
    page,
}: {
    page: { title: string; cover_url: string | null; content: string | null };
}) {
    return (
        <>
            <Head title={page.title} />

            {/* Hero: ảnh bìa (nếu có) + tiêu đề overlay; không có ảnh → banner nền be */}
            {page.cover_url ? (
                <div className="relative h-[280px] overflow-hidden sm:h-[360px]">
                    <img
                        src={page.cover_url}
                        alt={page.title}
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                    <div
                        className="absolute inset-0"
                        style={{
                            background:
                                'linear-gradient(180deg,rgba(24,35,15,.15),rgba(24,35,15,.55))',
                        }}
                    />
                    <div className="absolute inset-x-0 bottom-0 mx-auto max-w-[1120px] px-5 pb-8">
                        <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-[#f3d9b8]">
                            GIỚI THIỆU
                        </div>
                        <h1
                            className="max-w-[720px] font-extrabold leading-[1.15] tracking-tight text-white"
                            style={{ fontSize: 'clamp(26px,4vw,38px)' }}
                        >
                            {page.title}
                        </h1>
                    </div>
                </div>
            ) : (
                <div className="mx-auto max-w-[1120px] px-5 pt-10">
                    <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                        GIỚI THIỆU
                    </div>
                    <h1
                        className="max-w-[720px] font-extrabold leading-[1.15] tracking-tight text-ink"
                        style={{ fontSize: 'clamp(26px,4vw,38px)' }}
                    >
                        {page.title}
                    </h1>
                </div>
            )}

            <main className="mx-auto max-w-[1120px] px-5 pb-16 pt-10">
                {page.content && <MagazineContent html={page.content} />}

                {/* CTA cuối trang */}
                <div
                    className="mt-14 flex flex-col items-center gap-3 rounded-[20px] px-6 py-10 text-center"
                    style={{ background: '#eef2e3' }}
                >
                    <div className="text-[20px] font-extrabold tracking-tight text-ink">
                        Sẵn sàng cho chuyến đi kế tiếp?
                    </div>
                    <p className="max-w-[440px] text-[14px] leading-[1.6] text-moss">
                        Chọn ngày, chọn đồ, đặt thuê trong vài phút — cọc hoàn
                        lại đầy đủ khi trả đồ.
                    </p>
                    <Link
                        href="/thiet-bi"
                        className="mt-1 rounded-control bg-grass px-7 py-3.5 text-[14.5px] font-bold text-white transition hover:bg-pine"
                    >
                        Thuê đồ ngay →
                    </Link>
                </div>
            </main>
        </>
    );
}

About.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
