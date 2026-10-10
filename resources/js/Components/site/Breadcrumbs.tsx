import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

export type Crumb = { label: string; href?: string };

/**
 * Breadcrumb nhìn thấy được — đi cặp với BreadcrumbList JSON-LD mà controller khai
 * (schema phải ứng với nội dung trên trang). Mục cuối = trang hiện tại, không có link.
 */
export default function Breadcrumbs({ items }: { items: Crumb[] }) {
    return (
        <nav aria-label="Breadcrumb" className="mb-3 min-w-0">
            <ol className="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[13px] text-moss">
                {items.map((c, i) => (
                    <Fragment key={`${c.label}-${i}`}>
                        {i > 0 && (
                            <li aria-hidden className="text-[#a3ad92]">
                                /
                            </li>
                        )}
                        <li className="min-w-0">
                            {c.href ? (
                                <Link
                                    href={c.href}
                                    className="font-semibold hover:text-grass"
                                >
                                    {c.label}
                                </Link>
                            ) : (
                                <span
                                    aria-current="page"
                                    className="break-words text-ink"
                                >
                                    {c.label}
                                </span>
                            )}
                        </li>
                    </Fragment>
                ))}
            </ol>
        </nav>
    );
}
