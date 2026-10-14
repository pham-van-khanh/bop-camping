/** Kiểu dữ liệu Cẩm nang (bopcamping-ennp) — khớp Shop\PostController::card(). */

export type PostCover = { url: string; srcset: string | null };

export type PostCardData = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    cover: PostCover | null;
    published_at: string | null;
    published_label: string | null;
    reading_minutes: number;
    author: string;
};

export type PostDetailData = PostCardData & {
    content: string | null;
    updated_at: string | null;
};

/** Sản phẩm/combo gợi ý trong bài — khối "Đồ dùng trong bài". */
export type PostGearItem = {
    id: number;
    name: string;
    url: string;
    price: number;
    image: string | null;
    srcset: string | null;
};

export type PostPaginator = {
    data: PostCardData[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
