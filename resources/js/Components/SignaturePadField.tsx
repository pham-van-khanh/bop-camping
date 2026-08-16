import { useCallback, useEffect, useRef, useState } from 'react';
import SignaturePad from 'signature_pad';

type Props = {
    /** Gọi lại với data URL PNG sau mỗi nét, hoặc null khi ô trống. */
    onChange: (dataUrl: string | null) => void;
    disabled?: boolean;
};

/**
 * Ô vẽ chữ ký tay (bopcamping-4jao).
 *
 * Canvas phải scale theo devicePixelRatio, không thì nét ký vỡ hạt trên màn hình retina —
 * lỗi hay gặp nhất của canvas ký trên điện thoại. `touch-none` chặn trình duyệt cuộn trang
 * khi khách kéo ngón tay để ký.
 */
export default function SignaturePadField({
    onChange,
    disabled = false,
}: Props) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const padRef = useRef<SignaturePad | null>(null);
    const [empty, setEmpty] = useState(true);

    // Giữ onChange trong ref: nếu đưa thẳng vào deps của useEffect thì mỗi lần cha render
    // lại là canvas bị dựng lại và xoá sạch nét khách vừa ký.
    const onChangeRef = useRef(onChange);
    useEffect(() => {
        onChangeRef.current = onChange;
    }, [onChange]);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const pad = new SignaturePad(canvas, {
            backgroundColor: 'rgba(0,0,0,0)',
            penColor: '#1c1917',
        });
        padRef.current = pad;

        /**
         * Đổi kích thước canvas là XOÁ SẠCH bitmap của nó — nên phải cứu nét trước rồi vẽ lại.
         *
         * Hai điều bắt buộc, đừng bỏ:
         *   1. Chỉ làm khi kích thước THẬT SỰ đổi. Trên điện thoại, chỉ cần cuộn trang làm
         *      thanh địa chỉ thu lại là trình duyệt bắn 'resize'. Cứ nghe là dựng lại canvas
         *      thì khách đang ký sẽ mất nét giữa chừng.
         *   2. Cứu bằng toData()/fromData() (toạ độ nét), KHÔNG phải ảnh — vẽ lại từ toạ độ
         *      thì nét vẫn sắc ở kích thước mới.
         */
        const resize = () => {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const width = canvas.offsetWidth * ratio;
            const height = canvas.offsetHeight * ratio;

            // offsetWidth = 0 khi phần tử đang bị ẩn (tab nền, display:none). Dựng canvas
            // 0px ở đây là mất chữ ký mà chẳng đổi lại được gì.
            if (width === 0 || height === 0) return;
            if (canvas.width === width && canvas.height === height) return;

            const strokes = pad.toData();

            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d')?.scale(ratio, ratio);
            pad.clear(); // dọn state nội bộ của signature_pad sau khi bitmap đã mất

            if (strokes.length > 0) {
                pad.fromData(strokes);
                return; // còn nét thì trạng thái "đã ký" giữ nguyên
            }

            setEmpty(true);
            onChangeRef.current(null);
        };

        pad.addEventListener('endStroke', () => {
            setEmpty(false);
            onChangeRef.current(pad.toDataURL('image/png'));
        });

        resize();
        window.addEventListener('resize', resize);

        return () => {
            window.removeEventListener('resize', resize);
            pad.off();
        };
    }, []);

    const clear = useCallback(() => {
        padRef.current?.clear();
        setEmpty(true);
        onChangeRef.current(null);
    }, []);

    return (
        <div>
            <canvas
                ref={canvasRef}
                aria-label="Ô ký tên"
                className={`h-44 w-full touch-none rounded-md border border-stone-300 bg-white ${
                    disabled ? 'pointer-events-none opacity-50' : ''
                }`}
            />
            <div className="mt-2 flex items-center justify-between gap-3">
                <p className="text-sm text-stone-500">
                    Ký bằng ngón tay hoặc chuột vào khung trên.
                </p>
                <button
                    type="button"
                    onClick={clear}
                    disabled={empty || disabled}
                    className="text-sm underline disabled:opacity-40"
                >
                    Xoá chữ ký
                </button>
            </div>
        </div>
    );
}
