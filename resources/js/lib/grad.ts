// Gradient placeholder theo danh mục (khi sản phẩm chưa có ảnh) — dùng chung
// ProductDetail + Đặt lại đơn ở trang Tài khoản (bopcamping-7w8).
export const GRAD: Record<string, string> = {
    'leu-cam-trai':      'linear-gradient(150deg,#3a5a40,#588157)',
    'tui-ngu':           'linear-gradient(150deg,#4a4e69,#9a8c98)',
    'bep-nau-an':        'linear-gradient(150deg,#7f4f24,#b6873a)',
    'ban-ghe-da-ngoai':  'linear-gradient(150deg,#4a6741,#7a9b6b)',
    'den-chieu-sang':    'linear-gradient(150deg,#3d405b,#6e7db0)',
    'ba-lo-tui':         'linear-gradient(150deg,#5c4033,#8b6355)',
};

export const gradFor = (slug: string) => GRAD[slug] ?? 'linear-gradient(150deg,#4a6741,#7a9b6b)';
