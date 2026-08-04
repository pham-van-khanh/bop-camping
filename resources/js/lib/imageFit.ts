// Chọn object-fit cho ảnh chính gallery sao cho ảnh KHÔNG bị phóng to quá ngưỡng
// (bopcamping-slnb — "ảnh sản phẩm mờ").
//
// Bối cảnh: khung ảnh chính ProductDetail là 668x680 CSS px; trên màn Retina
// (devicePixelRatio = 2) nó cần ~1336x1360 pixel THẬT để nét. Nhiều ảnh trong DB
// chỉ 578-790px chiều rộng, và `object-cover` còn phóng thêm để lấp kín khung:
// ảnh dọc 790x1146 bị phóng 1.69x, trong khi `object-contain` chỉ 1.19x.
//
// Nên: mặc định `cover` (lấp kín khung, đẹp hơn), nhưng nếu cover phải phóng quá
// MAX_UPSCALE thì đổi sang `contain` để giữ nét — chấp nhận có nền be hai bên.
// Khi ảnh gốc đủ lớn (>=1600px, sau khi có pipeline bopcamping-ix4n) thì cover
// không còn phóng nữa nên hàm này luôn trả 'cover' — tự vô hiệu hoá, không thay
// đổi thiết kế vĩnh viễn.

/** Ngưỡng phóng tối đa (pixel thật / pixel ảnh gốc) còn coi là nét. */
export const MAX_UPSCALE = 1.35;

/**
 * Chỉ đổi sang contain khi nó nét hơn cover ít nhất 20%. Đo trên ảnh thật của
 * shop: ảnh dọc 790x1146 lợi 30% (1.69x → 1.19x) VÀ hiện đủ ảnh thay vì bị cắt
 * mất ~30% chiều cao → đáng đổi. Ảnh 578x678 gần vuông chỉ lợi 13% (2.31x →
 * 2.01x) mà lại thành khối ảnh lọt giữa nền be → không đáng; ca đó phải upload
 * lại ảnh gốc to hơn, không có mẹo hiển thị nào cứu được.
 */
const MIN_GAIN = 0.8;

export type ObjectFit = 'cover' | 'contain';

/**
 * @param natural  kích thước thật của file ảnh
 * @param box      kích thước khung hiển thị (CSS px)
 * @param dpr      devicePixelRatio của màn hình
 */
export function pickObjectFit(
    natural: { width: number; height: number },
    box: { width: number; height: number },
    dpr: number,
): ObjectFit {
    // Chưa đo được (ảnh chưa load / khung chưa layout) → giữ mặc định.
    if (!natural.width || !natural.height || !box.width || !box.height)
        return 'cover';

    // cover = scale để lấp kín khung → lấy cạnh cần phóng NHIỀU hơn.
    const coverScale = Math.max(
        box.width / natural.width,
        box.height / natural.height,
    );
    const upscale = coverScale * Math.max(dpr, 1);

    if (upscale <= MAX_UPSCALE) return 'cover';

    // contain = scale để vừa khung → lấy cạnh cần phóng ÍT hơn.
    const containScale = Math.min(
        box.width / natural.width,
        box.height / natural.height,
    );
    return containScale <= coverScale * MIN_GAIN ? 'contain' : 'cover';
}
