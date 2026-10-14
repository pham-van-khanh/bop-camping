import { terrainGradient } from '@/lib/terrain';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export type ServiceLocation = {
    name: string;
    /** Có slug + đang mở -> bấm sang trang khu vực /thue-do-cam-trai/{slug}. */
    slug?: string;
    area: string | null;
    status: 'open' | 'coming';
};
export type SuggestedSpot = {
    id: number;
    name: string;
    slug: string;
    terrain_tag: string;
    province: string;
    nearest_name: string | null;
};

/** Panel hero phải: Đang phục vụ tại + Điểm cắm trại gợi ý + nút mở cẩm nang. */
export default function HomeServingPanel({
    locations,
    suggested,
    onOpenGuide,
}: {
    locations: ServiceLocation[];
    suggested: SuggestedSpot[];
    onOpenGuide: () => void;
}) {
    return (
        <div
            className="rounded-[20px] p-5"
            style={{
                background: 'rgba(18,28,10,.5)',
                border: '1px solid rgba(207,224,168,.25)',
                backdropFilter: 'blur(12px)',
                boxShadow: '0 24px 60px -24px rgba(0,0,0,.6)',
            }}
        >
            {/* Đang phục vụ tại */}
            <div className="mb-2 flex items-center gap-2 font-mono text-[11.5px] tracking-[0.12em] text-[#bfe06a]">
                <span
                    className="h-[7px] w-[7px] rounded-full"
                    style={{
                        background: '#9bd34b',
                        boxShadow: '0 0 8px #9bd34b',
                    }}
                />
                ĐANG PHỤC VỤ TẠI
            </div>
            <p className="mb-3 text-[12.5px] leading-[1.5] text-white/75">
                Hiện tụi mình giao nhận đồ thuê tại {locations.length} thành
                phố. Khu vực khác sẽ mở sớm.
            </p>
            <div className="flex flex-col gap-2">
                {locations.map((l) => (
                    <LocationRow key={l.name} l={l}>
                        <span
                            className="grid h-9 w-9 flex-none place-items-center rounded-[10px]"
                            style={{ background: 'rgba(85,122,43,.45)' }}
                        >
                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                className="text-[#cfe0a8]"
                            >
                                <path
                                    d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                />
                                <circle
                                    cx="12"
                                    cy="10"
                                    r="2.4"
                                    stroke="currentColor"
                                    strokeWidth="1.8"
                                />
                            </svg>
                        </span>
                        <div className="min-w-0 flex-1 leading-tight">
                            <div className="text-[14px] font-bold text-white">
                                {l.name}
                            </div>
                            {l.area && (
                                <div className="text-[11.5px] text-white/55">
                                    {l.area}
                                </div>
                            )}
                        </div>
                        <span
                            className="flex-none rounded-full px-2.5 py-1 font-mono text-[10px] tracking-[0.05em]"
                            style={
                                l.status === 'open'
                                    ? {
                                          background: 'rgba(155,211,75,.18)',
                                          color: '#bfe06a',
                                          border: '1px solid rgba(155,211,75,.35)',
                                      }
                                    : {
                                          background: 'rgba(201,123,54,.18)',
                                          color: '#e7a86a',
                                          border: '1px solid rgba(201,123,54,.35)',
                                      }
                            }
                        >
                            {l.status === 'open' ? 'ĐANG MỞ' : 'SẮP MỞ'}
                        </span>
                    </LocationRow>
                ))}
            </div>

            {/* Điểm cắm trại gợi ý */}
            {suggested.length > 0 && (
                <>
                    <div
                        className="my-4 h-px"
                        style={{ background: 'rgba(207,224,168,.18)' }}
                    />
                    <div className="mb-2 flex items-center gap-2 font-mono text-[11.5px] tracking-[0.12em] text-[#e7a86a]">
                        <span aria-hidden>⛺</span> ĐIỂM CẮM TRẠI GỢI Ý
                    </div>
                    <div className="flex flex-col gap-2">
                        {suggested.map((s) => (
                            <Link
                                key={s.id}
                                href={`/diem-cam-trai/${s.slug}`}
                                className="flex items-center gap-3 rounded-[10px] transition hover:bg-white/5"
                            >
                                <span
                                    className="h-9 w-9 flex-none rounded-[10px]"
                                    style={{
                                        background: terrainGradient(
                                            s.terrain_tag,
                                        ),
                                    }}
                                />
                                <div className="min-w-0 flex-1 leading-tight">
                                    <div className="truncate text-[13.5px] font-bold text-white">
                                        {s.name}
                                    </div>
                                    <div className="font-mono text-[11px] text-white/55">
                                        {s.nearest_name ?? s.province}
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                </>
            )}

            <button
                onClick={onOpenGuide}
                className="mt-4 flex w-full items-center justify-center gap-2 rounded-[13px] px-4 py-3 text-center text-[13.5px] font-bold text-white transition hover:-translate-y-0.5"
                style={{
                    background: '#557A2B',
                    boxShadow: '0 14px 30px -12px rgba(0,0,0,.6)',
                }}
            >
                Nếu bạn chưa biết cắm trại ở đâu, tụi mình sẽ giúp bạn
                <span aria-hidden>→</span>
            </button>
        </div>
    );
}

/** Một dòng cơ sở — cơ sở đang mở là link sang trang khu vực (bopcamping-y05c). */
function LocationRow({
    l,
    children,
}: {
    l: ServiceLocation;
    children: ReactNode;
}) {
    const className =
        'flex items-center gap-3 rounded-[12px] px-3 py-2.5 transition';
    const style = {
        background: 'rgba(255,255,255,.06)',
        border: '1px solid rgba(207,224,168,.18)',
    };
    if (l.status === 'open' && l.slug) {
        return (
            <Link
                href={`/thue-do-cam-trai/${l.slug}`}
                className={`${className} hover:bg-white/10`}
                style={style}
            >
                {children}
            </Link>
        );
    }
    return (
        <div className={className} style={style}>
            {children}
        </div>
    );
}
