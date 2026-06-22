// Single source of truth cho nhãn + màu trạng thái đơn (DRY — dùng ở Admin/Orders + Admin/Users).

export const STATUS_LABEL: Record<string, string> = {
    pending: 'Chờ xác nhận',
    confirmed: 'Đã xác nhận',
    renting: 'Đang thuê',
    returned: 'Đã trả',
    cancelled: 'Đã huỷ',
};

export const STATUS_STYLE: Record<string, { color: string; bg: string }> = {
    pending: { color: '#9a7a2a', bg: '#fbf2d8' },
    confirmed: { color: '#2a6ea0', bg: '#dceaf6' },
    renting: { color: '#3a5a1f', bg: '#dcebc4' },
    returned: { color: '#5C6E47', bg: '#e7ecdc' },
    cancelled: { color: '#b3493a', bg: '#f6ddd6' },
};
