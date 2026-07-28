export default function Logo({
    size = 38,
    className = '',
}: {
    size?: number;
    className?: string;
}) {
    return (
        <img
            src="/images/logo-128.png"
            alt="Bốp Camping"
            width={size}
            height={size}
            className={`shrink-0 rounded-full object-cover ${className}`}
        />
    );
}
