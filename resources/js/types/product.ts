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
    images: { path: string; sort_order: number }[];
    featured: boolean;
    // only present in show response
    unavailable_dates?: string[];
}
