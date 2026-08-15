import { money } from '@/lib/format';

/**
 * Khối QR chuyển khoản của một đơn (bopcamping-55rh) — dùng chung cho admin, trang tra
 * cứu và trang tài khoản.
 *
 * Backend (PaymentQrService) đã quyết đơn nào có QR: prop null thì không vẽ gì. Component
 * KHÔNG tự suy luận lại điều kiện, vì suy hai nơi là sớm muộn lệch nhau.
 *
 * `download_url` chỉ admin mới có — nút tải ảnh là để gửi khách qua Zalo.
 */
export type PaymentQrData = {
    url: string;
    amount: number;
    content: string;
    download_url?: string;
    // Thông tin người nhận dạng CHỮ (bopcamping-r3fy) — xem chú thích ở khối dự phòng.
    bank?: string | null;
    account?: string | null;
    holder?: string | null;
};

export default function PaymentQr({
    qr,
    title = 'QR chuyển khoản',
}: {
    qr: PaymentQrData | null;
    title?: string;
}) {
    if (!qr) return null;

    return (
        <div className="rounded-[10px] border border-[#eef2e3] bg-white p-3">
            <div className="mb-2 text-[12px] font-bold uppercase tracking-[0.04em] text-grass">
                {title}
            </div>

            <div className="flex flex-wrap items-start gap-4">
                {/* referrerPolicy: trang tra cứu có SĐT khách ngay trên URL
                    (/tra-cuu?code=…&phone=…). Ảnh này tải từ miền SePay, nên nếu trình
                    duyệt gửi Referer đầy đủ thì SĐT khách đi thẳng sang bên thứ ba.
                    Trình duyệt hiện đại mặc định đã chỉ gửi origin, nhưng mặc định là
                    thứ đổi được — khoá cứng ở đây thì không phụ thuộc vào nó nữa. */}
                <img
                    src={qr.url}
                    alt={`Mã QR chuyển khoản ${money(qr.amount)}, nội dung ${qr.content}`}
                    loading="lazy"
                    referrerPolicy="no-referrer"
                    className="w-[200px] max-w-full rounded-[8px] border border-[#eef2e3]"
                />

                <div className="min-w-[180px] flex-1 text-[13px]">
                    <div className="text-moss">Số tiền</div>
                    <div className="font-mono text-[17px] font-bold text-ink">
                        {money(qr.amount)}
                    </div>

                    {/* Nội dung CK hiện dạng chữ, KHÔNG chỉ nằm trong ảnh: đối soát ở đây
                        làm tay, đây là chuỗi duy nhất để dò ra tiền của đơn nào trong sao kê. */}
                    <div className="mt-2 text-moss">Nội dung chuyển khoản</div>
                    <div className="select-all font-mono text-[15px] font-bold text-pine">
                        {qr.content}
                    </div>

                    {/* Người nhận dạng CHỮ (bopcamping-r3fy). Trước đây ba thông tin này chỉ
                        nằm trong pixel của ảnh và trong query string, nên SePay sập hoặc
                        mạng lỗi là khách hết đường: biết số tiền, biết nội dung, mà không
                        biết chuyển cho ai. */}
                    {qr.account && (
                        <>
                            <div className="mt-2 text-moss">Chuyển tới</div>
                            <div className="select-all font-mono text-[14px] font-bold text-pine">
                                {qr.account}
                                {qr.bank ? ` · ${qr.bank}` : ''}
                            </div>
                            {qr.holder && (
                                <div className="text-[12.5px] text-moss">
                                    {qr.holder}
                                </div>
                            )}
                        </>
                    )}

                    {qr.download_url && (
                        <a
                            href={qr.download_url}
                            rel="noreferrer"
                            className="mt-3 inline-block rounded-[9px] border border-cardBorder px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                        >
                            Tải ảnh QR
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}
