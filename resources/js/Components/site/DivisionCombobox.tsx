import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { useState } from 'react';

/**
 * Dropdown chọn tỉnh / xã.
 *
 * VÌ SAO KHÔNG DÙNG <select>: danh sách của <select> do trình duyệt + hệ điều hành vẽ,
 * CSS gần như không vào được (macOS bỏ qua gần hết) — muốn style được từng dòng thì
 * bắt buộc phải tự dựng. Dùng Combobox của @headlessui (đã là dependency sẵn, Breeze
 * dùng rồi) để khỏi tự viết phần bàn phím + aria.
 *
 * Ô gõ được là BẮT BUỘC chứ không phải thêm thắt: <select> có sẵn type-ahead của OS,
 * bỏ nó đi mà không có ô lọc thì khách phải cuộn tay qua 130 xã của Nghệ An.
 */

export type Division = { code: number; name: string };

/**
 * Bỏ dấu để "ha noi" tìm ra "Hà Nội" — người Việt gõ tên tỉnh hiếm khi bỏ dấu đúng,
 * và bàn phím mặc định trên máy tính cũng không gõ dấu.
 */
function khongDau(s: string): string {
    return s
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase()
        .trim();
}

const boxCls =
    'h-[48px] w-full rounded-[11px] border border-cardBorder bg-white pl-3.5 pr-9 text-[14px] font-medium text-ink outline-none transition placeholder:font-normal placeholder:text-[#9aa78a] focus:border-grass focus:ring-2 focus:ring-grass/20 disabled:cursor-not-allowed disabled:bg-[#f4f6ee] disabled:text-[#aab39a]';

/** Generic để trả về đúng Province/Ward chứ không tụt xuống Division. */
export default function DivisionCombobox<T extends Division>({
    label,
    placeholder,
    items,
    value,
    onChange,
    disabled = false,
}: {
    label: string;
    placeholder: string;
    items: T[];
    value: T | null;
    onChange: (d: T | null) => void;
    disabled?: boolean;
}) {
    const [query, setQuery] = useState('');

    const q = khongDau(query);
    const filtered =
        q === '' ? items : items.filter((i) => khongDau(i.name).includes(q));

    return (
        <Combobox
            value={value}
            onChange={onChange}
            disabled={disabled}
            onClose={() => setQuery('')}
            immediate
        >
            <div className="relative">
                <ComboboxInput
                    aria-label={label}
                    placeholder={placeholder}
                    className={boxCls}
                    displayValue={(d: T | null) => d?.name ?? ''}
                    onChange={(e) => setQuery(e.target.value)}
                />
                <ComboboxButton
                    aria-label={`Mở danh sách ${label}`}
                    className="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-grass"
                >
                    <svg
                        viewBox="0 0 20 20"
                        fill="none"
                        className="h-[18px] w-[18px]"
                    >
                        <path
                            d="M6 8l4 4 4-4"
                            stroke="currentColor"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    </svg>
                </ComboboxButton>

                <ComboboxOptions className="absolute z-30 mt-1.5 max-h-64 w-full overflow-auto rounded-[12px] border border-cardBorder bg-white p-1 shadow-[0_12px_28px_rgba(51,64,38,0.14)] focus:outline-none">
                    {filtered.length === 0 && (
                        <p className="px-3 py-2.5 text-[13px] text-moss">
                            Không tìm thấy “{query}”
                        </p>
                    )}
                    {filtered.map((d) => (
                        <ComboboxOption
                            key={d.code}
                            value={d}
                            className="group flex cursor-pointer items-center justify-between gap-2 rounded-[8px] px-3 py-2.5 text-[14px] text-ink transition-colors data-[focus]:bg-[#eef3e4] data-[selected]:font-semibold data-[selected]:text-grass"
                        >
                            <span className="truncate">{d.name}</span>
                            <svg
                                viewBox="0 0 20 20"
                                fill="none"
                                aria-hidden="true"
                                className="hidden h-4 w-4 shrink-0 text-grass group-data-[selected]:block"
                            >
                                <path
                                    d="M4.5 10.5l3.5 3.5 7.5-8"
                                    stroke="currentColor"
                                    strokeWidth="1.9"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                />
                            </svg>
                        </ComboboxOption>
                    ))}
                </ComboboxOptions>
            </div>
        </Combobox>
    );
}
