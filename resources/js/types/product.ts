export interface ProductResource {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_per_day: number;
    quantity: number;
    deposit: number;
    thumbnail: string | null;
    status: string;
    category: { id: number; name: string; slug: string };
    images: { url: string; sort_order: number; type: 'image' | 'video' }[];
    featured: boolean;
    // Vị trí phục vụ đang mở (badge thẻ). all_locations = phục vụ toàn hệ thống.
    locations?: { name: string; slug: string }[];
    all_locations?: boolean;
    // only present in show response
    unavailable_dates?: string[];
    // Trang chi tiết (Epic 1): thông số key–value + nội dung setup (HTML đã sanitize)
    specs?: { key: string; value: string }[];
    setup_content?: string | null;
    // Per-store stock: tồn theo từng cửa hàng phục vụ (trang chi tiết)
    stock_by_location?: { id: number; name: string; slug: string; quantity: number }[];
}
