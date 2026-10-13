export default function Logo({
    size = 38,
    className = '',
    loading,
}: {
    size?: number;
    className?: string;
    /** 'lazy' cho chỗ dưới màn hình đầu (footer); header để mặc định (tải ngay). */
    loading?: 'lazy' | 'eager';
}) {
    return (
        <img
            src="/images/logo-128.png"
            alt="Bốp Camping"
            width={size}
            height={size}
            loading={loading}
            className={`shrink-0 rounded-full object-cover ${className}`}
        />
    );
}
