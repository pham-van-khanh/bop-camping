/** Màu gradient theo loại địa hình (đồng bộ panel gợi ý + thẻ cẩm nang). */
export function terrainGradient(tag: string): string {
    const t = tag.toLowerCase();
    if (t.includes('cát')) return 'linear-gradient(135deg,#c89a4e,#e3c382)';
    if (t.includes('hồ') && t.includes('núi')) return 'linear-gradient(135deg,#2f8f86,#5bb6a6)';
    if (t.includes('biển') || t.includes('hồ') || t.includes('ven')) return 'linear-gradient(135deg,#3f7fb0,#7cb6db)';
    if (t.includes('rừng') || t.includes('núi') || t.includes('đỉnh')) return 'linear-gradient(135deg,#2C3D22,#4a6b2e)';
    return 'linear-gradient(135deg,#557A2B,#7fae4e)'; // đồi cỏ / view sông / mặc định
}
