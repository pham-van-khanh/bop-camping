import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import MagazineContent from '@/Components/site/MagazineContent';

/**
 * Trang chính sách (bảo mật, điều khoản, thanh toán, giao nhận, hủy/đổi/trả).
 * Nội dung sửa trong admin "Trang nội dung". Bố cục prose, text-only là chính
 * (MagazineContent tự fallback về editor-content khi không có ảnh).
 */
export default function Policy({
    page,
}: {
    page: { title: string; cover_url: string | null; content: string | null };
}) {
    return (
        <>
            <Head title={page.title} />

            <div className="mx-auto max-w-[820px] px-5 pt-10">
                <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">CHÍNH SÁCH</div>
                <h1 className="font-extrabold leading-[1.15] tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3.5vw,34px)' }}>
                    {page.title}
                </h1>
            </div>

            <main className="mx-auto max-w-[820px] px-5 pb-16 pt-6">
                {page.content && <MagazineContent html={page.content} />}
            </main>
        </>
    );
}

Policy.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
