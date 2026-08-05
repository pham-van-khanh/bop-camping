import { ImgHTMLAttributes } from 'react';

export default function ApplicationLogo(
    props: ImgHTMLAttributes<HTMLImageElement>,
) {
    return (
        <img
            src="/images/logo-128.png"
            alt="Bốp Camping"
            {...props}
            className={`rounded-full object-cover ${props.className ?? ''}`}
        />
    );
}
