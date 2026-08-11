export interface ProductResource {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_per_day: number;
    quantity: number;
    deposit: number;
    // Ưu đãi trả sớm trong ngày % (adr_pricing_models) — 0 = không có.
    early_return_discount_pct?: number;
    // Khung giờ nhận/trả override theo sản phẩm (null = theo shop) — bopcamping-fica.
    thumbnail: string | null;
    // srcset của thumbnail (bopcamping-ix4n) — null khi ảnh chưa có biến thể resize.
    thumbnail_srcset?: string | null;
    status: string;
    category: { id: number; name: string; slug: string };
    images: { url: string; srcset?: string | null; sort_order: number; type: 'image' | 'video' }[];
    featured: boolean;
    // Vị trí phục vụ đang mở (badge thẻ). all_locations = phục vụ toàn hệ thống.
    locations?: { name: string; slug: string }[];
    all_locations?: boolean;
    // Lọc theo khoảng ngày trên /thiet-bi (bopcamping-3kn9). null = khách CHƯA chọn ngày,
    // khác hẳn 0 = đã chọn nhưng hết hàng — FE phải phân biệt hai trạng thái này.
    available?: number | null;
    in_range?: boolean | null;
    // Kho đang giữ con số `available` khi khách CHƯA chọn kho (bopcamping-kvcc).
    // null = con số đúng ở mọi kho; có tên = các kho lệch nhau, số đó là của kho này.
    available_at?: string | null;
    // only present in show response
    unavailable_dates?: string[];
    // Trang chi tiết (Epic 1): thông số key–value + nội dung setup (HTML đã sanitize)
    specs?: { key: string; value: string }[];
    setup_content?: string | null;
    // Per-store stock: tồn theo từng cửa hàng phục vụ (trang chi tiết)
    stock_by_location?: {
        id: number;
        name: string;
        slug: string;
        quantity: number;
    }[];
}
