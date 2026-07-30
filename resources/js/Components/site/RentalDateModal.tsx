import RentalDatePicker, {
    type RentalLocationOption,
} from '@/Components/site/RentalDatePicker';
import { useEffect, useRef } from 'react';

/**
 * Popup đặt lịch — mở từ ô đặt lịch trên banner trang chủ.
 *
 * Rộng hơn dải inline dưới hero (920px vs 720px) và lịch dùng size 'lg' nên ô ngày to
 * hơn hẳn trên PC. Trên mobile vẫn tràn full width và lịch tự xếp dọc.
 *
 * Theo pattern modal sẵn có của CampingGuideModal: backdrop bấm để đóng, ESC để đóng,
 * chặn scroll trang nền.
 */
export default function RentalDateModal({
    serviceLocations,
    initialStart = null,
    initialEnd = null,
    initialLocation = null,
    onClose,
}: {
    serviceLocations: RentalLocationOption[];
    initialStart?: string | null;
    initialEnd?: string | null;
    initialLocation?: string | null;
    onClose: () => void;
}) {
    const panelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        // Đưa focus vào panel để người dùng bàn phím không bị kẹt ngoài modal.
        panelRef.current?.focus();

        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    return (
        <div
            onClick={onClose}
            className="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto p-4 sm:p-6"
            style={{
                background: 'rgba(24,35,15,.6)',
                backdropFilter: 'blur(4px)',
            }}
        >
            <div
                ref={panelRef}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                aria-label="Chọn ngày thuê"
                onClick={(e) => e.stopPropagation()}
                className="my-2 w-full max-w-[920px] overflow-hidden rounded-[22px] bg-[#f3f5ec] shadow-2xl outline-none sm:my-6"
            >
                <div
                    className="relative px-6 py-6 sm:px-9 sm:py-7"
                    style={{
                        background: 'linear-gradient(135deg,#2C3D22,#3f5a2a)',
                    }}
                >
                    <div className="mb-1.5 font-mono text-[12px] tracking-[0.14em] text-[#bfe06a]">
                        CHỌN NGÀY TRƯỚC, CHỌN ĐỒ SAU
                    </div>
                    <h2
                        className="font-extrabold tracking-tight text-white"
                        style={{ fontSize: 'clamp(24px,3.4vw,34px)' }}
                    >
                        Bạn đi ngày nào?
                    </h2>
                    <p className="mt-2 max-w-[560px] text-[14px] leading-[1.6] text-white/80">
                        Chọn ngày nhận và ngày trả, tụi mình lọc sẵn những thiết
                        bị còn rảnh trong khoảng đó.
                    </p>

                    <button
                        onClick={onClose}
                        aria-label="Đóng"
                        className="absolute right-5 top-5 grid h-9 w-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15"
                        style={{ background: 'rgba(255,255,255,.12)' }}
                    >
                        ×
                    </button>
                </div>

                <div className="px-5 py-6 sm:px-9 sm:py-7">
                    <RentalDatePicker
                        variant="hero"
                        calendarSize="lg"
                        serviceLocations={serviceLocations}
                        initialStart={initialStart}
                        initialEnd={initialEnd}
                        initialLocation={initialLocation}
                    />
                </div>
            </div>
        </div>
    );
}
