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
    images: { path: string; sort_order: number; type: 'image' | 'video' }[];
    featured: boolean;
    // Vị trí phục vụ đang mở (badge thẻ). all_locations = phục vụ toàn hệ thống.
    locations?: { name: string; slug: string }[];
    all_locations?: boolean;
    // only present in show response
    unavailable_dates?: string[];
}
