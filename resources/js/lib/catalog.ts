// Dữ liệu mẫu (single source of truth phía client cho đến khi có Models/DB).
// Dùng chung cho Trang chủ, Danh sách, Chi tiết, Admin.

export type CatKey =
    | 'tents'
    | 'sleeping'
    | 'cooking'
    | 'lighting'
    | 'furniture';

export type Spec = { k: string; v: string };

export type Product = {
    id: number;
    name: string;
    cat: CatKey;
    blurb: string;
    price: number; // thuê/ngày
    deposit: number; // cọc (hoàn lại)
    stock: number;
    rating: number;
    grad: string;
    specs: Spec[];
    featured?: boolean;
};

export const CATEGORIES: { key: CatKey; label: string }[] = [
    { key: 'tents', label: 'Lều trại' },
    { key: 'sleeping', label: 'Túi ngủ & nệm' },
    { key: 'cooking', label: 'Bếp & nấu' },
    { key: 'lighting', label: 'Đèn & sáng' },
    { key: 'furniture', label: 'Bàn ghế' },
];

export const catLabel = (key: CatKey) =>
    CATEGORIES.find((c) => c.key === key)?.label ?? '';

const GRAD: Record<CatKey, string> = {
    tents: 'linear-gradient(150deg,#3f6322,#6e9440)',
    sleeping: 'linear-gradient(150deg,#2c5a6e,#6ea7bd)',
    cooking: 'linear-gradient(150deg,#7a5a2a,#c79a52)',
    lighting: 'linear-gradient(150deg,#4a4f2a,#9aa05a)',
    furniture: 'linear-gradient(150deg,#4a4032,#8a7a5a)',
};

export const PRODUCTS: Product[] = [
    {
        id: 1,
        name: 'Lều Coleman 4 người chống mưa',
        cat: 'tents',
        featured: true,
        blurb: 'Lều 2 lớp, 4 người thoải mái, chống mưa tốt cho mọi chuyến đi.',
        price: 120000,
        deposit: 500000,
        stock: 5,
        rating: 4.9,
        grad: GRAD.tents,
        specs: [
            { k: 'Sức chứa', v: '4 người' },
            { k: 'Trọng lượng', v: '4.2 kg' },
            { k: 'Chống nước', v: '3000mm' },
            { k: 'Dựng lều', v: '~5 phút' },
        ],
    },
    {
        id: 2,
        name: 'Lều 2 lớp 4 người dựng nhanh',
        cat: 'tents',
        featured: true,
        blurb: 'Chống mưa gió, dựng nhanh trong 5 phút, gọn nhẹ khi gấp.',
        price: 180000,
        deposit: 500000,
        stock: 3,
        rating: 4.8,
        grad: GRAD.tents,
        specs: [
            { k: 'Sức chứa', v: '4 người' },
            { k: 'Trọng lượng', v: '3.8 kg' },
            { k: 'Chống nước', v: '2500mm' },
            { k: 'Số cửa', v: '2 cửa' },
        ],
    },
    {
        id: 3,
        name: 'Túi ngủ lông vũ -10°C',
        cat: 'sleeping',
        featured: true,
        blurb: 'Ấm cho đêm núi cao, siêu nhẹ chỉ 900g, gói gọn trong balo.',
        price: 70000,
        deposit: 250000,
        stock: 2,
        rating: 4.9,
        grad: GRAD.sleeping,
        specs: [
            { k: 'Nhiệt độ', v: '-10°C' },
            { k: 'Trọng lượng', v: '900 g' },
            { k: 'Chất liệu', v: 'Lông vũ' },
            { k: 'Kích thước', v: '210×80 cm' },
        ],
    },
    {
        id: 4,
        name: 'Nệm hơi tự bơm đôi',
        cat: 'sleeping',
        blurb: 'Êm lưng cho 2 người, tự bơm bằng chân, gấp gọn nhẹ.',
        price: 60000,
        deposit: 200000,
        stock: 4,
        rating: 4.7,
        grad: GRAD.sleeping,
        specs: [
            { k: 'Kích thước', v: '190×130 cm' },
            { k: 'Độ dày', v: '5 cm' },
            { k: 'Trọng lượng', v: '2.1 kg' },
            { k: 'Bơm', v: 'Tự bơm' },
        ],
    },
    {
        id: 5,
        name: 'Bếp gas mini + bình',
        cat: 'cooking',
        featured: true,
        blurb: 'Gọn trong lòng bàn tay, lửa khỏe, đun sôi nhanh giữa gió.',
        price: 45000,
        deposit: 150000,
        stock: 6,
        rating: 4.8,
        grad: GRAD.cooking,
        specs: [
            { k: 'Công suất', v: '2.7 kW' },
            { k: 'Trọng lượng', v: '380 g' },
            { k: 'Kèm', v: '1 bình gas' },
            { k: 'Chắn gió', v: 'Có' },
        ],
    },
    {
        id: 6,
        name: 'Bộ nồi du lịch 4 món',
        cat: 'cooking',
        blurb: 'Chống dính, lồng vào nhau gọn gàng, đủ nấu cho nhóm 4 người.',
        price: 40000,
        deposit: 150000,
        stock: 5,
        rating: 4.6,
        grad: GRAD.cooking,
        specs: [
            { k: 'Số món', v: '4 món' },
            { k: 'Chất liệu', v: 'Hợp kim nhôm' },
            { k: 'Trọng lượng', v: '700 g' },
            { k: 'Phục vụ', v: '3-4 người' },
        ],
    },
    {
        id: 7,
        name: 'Đèn lều tích điện 3 mức',
        cat: 'lighting',
        featured: true,
        blurb: 'Sáng 12 giờ, 3 mức sáng, treo được trong lều, sạc USB.',
        price: 30000,
        deposit: 100000,
        stock: 8,
        rating: 4.9,
        grad: GRAD.lighting,
        specs: [
            { k: 'Thời lượng', v: '~12 giờ' },
            { k: 'Mức sáng', v: '3 mức' },
            { k: 'Sạc', v: 'USB-C' },
            { k: 'Chống nước', v: 'IPX4' },
        ],
    },
    {
        id: 8,
        name: 'Đèn pin đội đầu LED',
        cat: 'lighting',
        blurb: 'Rảnh tay khi nấu nướng, dựng lều, pin sạc dùng nhiều đêm.',
        price: 20000,
        deposit: 80000,
        stock: 10,
        rating: 4.7,
        grad: GRAD.lighting,
        specs: [
            { k: 'Độ sáng', v: '230 lumen' },
            { k: 'Thời lượng', v: '~8 giờ' },
            { k: 'Sạc', v: 'USB' },
            { k: 'Trọng lượng', v: '95 g' },
        ],
    },
    {
        id: 9,
        name: 'Bàn xếp nhôm dã ngoại',
        cat: 'furniture',
        blurb: 'Gấp gọn như vali, mặt nhôm chịu lực, kê đồ nấu chắc chắn.',
        price: 50000,
        deposit: 150000,
        stock: 4,
        rating: 4.6,
        grad: GRAD.furniture,
        specs: [
            { k: 'Kích thước', v: '70×70 cm' },
            { k: 'Tải trọng', v: '30 kg' },
            { k: 'Trọng lượng', v: '2.8 kg' },
            { k: 'Gấp gọn', v: 'Dạng vali' },
        ],
    },
    {
        id: 10,
        name: 'Ghế xếp tựa lưng (cặp 2)',
        cat: 'furniture',
        blurb: 'Ngồi lửa trại êm cả tối, khung chắc, gấp gọn mang theo.',
        price: 40000,
        deposit: 120000,
        stock: 6,
        rating: 4.8,
        grad: GRAD.furniture,
        specs: [
            { k: 'Số lượng', v: '2 ghế' },
            { k: 'Tải trọng', v: '120 kg/ghế' },
            { k: 'Trọng lượng', v: '1.6 kg/ghế' },
            { k: 'Tựa lưng', v: 'Cao' },
        ],
    },
];

export const getProduct = (id: number) => PRODUCTS.find((p) => p.id === id);
export const featuredProducts = () =>
    PRODUCTS.filter((p) => p.featured).slice(0, 4);
