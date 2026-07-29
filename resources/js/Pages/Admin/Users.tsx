import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/format';
import { STATUS_LABEL, STATUS_STYLE } from '@/lib/orderStatus';
import type { PageProps } from '@/types';

type Customer = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    orders_count: number;
    total_spent: number;
    last_order_at: string | null;
    created_at: string;
};
type AdminUser = { id: number; name: string; phone: string | null; email: string | null; created_at: string };
// Shipper (bopcamping-2xf6): upcoming_legs = số lượt giao/thu sắp tới còn gán cho người này.
type ShipperUser = AdminUser & { is_admin: boolean; upcoming_legs: number };
type OrderRow = {
    id: number;
    code: string;
    start_date: string;
    end_date: string;
    total_price: number;
    status: string;
    linked: boolean;
};
type CustomerDetail = { id: number; name: string; phone: string | null; email: string | null; orders: OrderRow[] };
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
type AdminFormData = { name: string; phone: string; email: string; password: string; role: 'admin' | 'shipper' };

export default function AdminUsers({
    tab,
    filters,
    customers,
    admins,
    shippers,
    customerDetail,
    stats,
}: {
    tab: 'customers' | 'admins' | 'shippers';
    filters: { q: string };
    customers: Paginator<Customer>;
    admins: AdminUser[];
    shippers: ShipperUser[];
    customerDetail: CustomerDetail | null;
    stats: { customers: number; admins: number; shippers: number };
}) {
    const { auth, flash } = usePage<PageProps>().props;
    const meId = auth.user?.id;

    const [search, setSearch] = useState(filters.q);
    const [expandedId, setExpandedId] = useState<number | null>(customerDetail?.id ?? null);
    const [loadingDetailId, setLoadingDetailId] = useState<number | null>(null);
    const [toastMsg, setToastMsg] = useState('');
    const [actionError, setActionError] = useState('');

    const [deleteTarget, setDeleteTarget] = useState<{ id: number; name: string; warning?: string } | null>(null);
    // Tắt vai shipper — cảnh báo nếu còn lượt sắp tới (bopcamping-2xf6).
    const [unassignTarget, setUnassignTarget] = useState<ShipperUser | null>(null);
    const [demoteTarget, setDemoteTarget] = useState<AdminUser | null>(null);
    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editingAdmin, setEditingAdmin] = useState<AdminUser | null>(null);

    const form = useForm<AdminFormData>({ name: '', phone: '', email: '', password: '', role: 'admin' });

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    /* --- navigation --- */
    const switchTab = (t: 'customers' | 'admins' | 'shippers') =>
        router.get(route('admin.users'), { tab: t }, { preserveScroll: true });

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('admin.users'), { tab: 'customers', q: search }, { preserveScroll: true, replace: true });
    };

    const toggleDetail = (c: Customer) => {
        if (expandedId === c.id) {
            setExpandedId(null);
            return;
        }
        setExpandedId(c.id);
        setLoadingDetailId(c.id);
        router.get(
            route('admin.users'),
            { tab: 'customers', q: filters.q, page: customers.current_page, customer: c.id },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['customerDetail'],
                onFinish: () => setLoadingDetailId(null),
            },
        );
    };

    const gotoPage = (p: number) =>
        router.get(route('admin.users'), { tab: 'customers', q: filters.q, page: p }, { preserveScroll: true });

    /* --- admin modal --- */
    const openCreate = (role: 'admin' | 'shipper' = 'admin') => {
        form.setData({ name: '', phone: '', email: '', password: '', role });
        form.clearErrors();
        setEditingAdmin(null);
        setModalMode('create');
    };
    const openEdit = (a: AdminUser, role: 'admin' | 'shipper' = 'admin') => {
        form.setData({ name: a.name, phone: a.phone ?? '', email: a.email ?? '', password: '', role });
        form.clearErrors();
        setEditingAdmin(a);
        setModalMode('edit');
    };
    const closeModal = () => {
        setModalMode(null);
        setEditingAdmin(null);
        form.reset();
    };
    const submitAdmin = (e: React.FormEvent) => {
        e.preventDefault();
        if (modalMode === 'create') {
            form.post(route('admin.users.store'), { onSuccess: closeModal });
        } else if (editingAdmin) {
            form.put(route('admin.users.update', editingAdmin.id), { onSuccess: closeModal });
        }
    };

    /* --- guarded mutations --- */
    const confirmDemote = () => {
        if (!demoteTarget) return;
        router.patch(route('admin.users.role', demoteTarget.id), { is_admin: false }, {
            preserveScroll: true,
            onSuccess: () => { setDemoteTarget(null); setActionError(''); },
            onError: (errs) => setActionError((errs as Record<string, string>).message ?? 'Không thành công, thử lại nhé.'),
        });
    };
    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('admin.users.destroy', deleteTarget.id), {
            data: deleteTarget.warning ? { force: true } : {},
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setActionError(''); },
            onError: (errs) => setActionError((errs as Record<string, string>).message ?? 'Không thành công, thử lại nhé.'),
        });
    };

    const confirmUnassign = () => {
        if (!unassignTarget) return;
        router.patch(
            route('admin.users.role', unassignTarget.id),
            // force = đồng ý để các lượt sắp tới của người này về "chưa gán"
            { is_shipper: false, force: unassignTarget.upcoming_legs > 0 },
            {
                preserveScroll: true,
                onSuccess: () => { setUnassignTarget(null); setActionError(''); },
                onError: (errs) => setActionError((errs as Record<string, string>).message ?? 'Không thành công, thử lại nhé.'),
            },
        );
    };

    const onlyAdmin = admins.length <= 1;

    return (
        <>
            <Head title="Admin · Người dùng" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                {/* Header */}
                <div className="mb-5">
                    <h1 className="text-[22px] font-extrabold text-pine">Người dùng</h1>
                    <p className="mt-0.5 text-[13px] text-moss">
                        <span className="font-mono">{stats.customers}</span> khách hàng ·{' '}
                        <span className="font-mono">{stats.admins}</span> quản trị viên
                    </p>
                </div>

                {/* Tabs */}
                <div className="mb-4 flex w-fit gap-1 rounded-[12px] border border-cardBorder bg-white p-1">
                    {([['customers', 'Khách hàng'], ['admins', 'Quản trị viên'], ['shippers', 'Shipper']] as const).map(([key, label]) => (
                        <button
                            key={key}
                            onClick={() => switchTab(key)}
                            className={`rounded-[9px] px-4 py-2 text-[13px] font-semibold transition ${
                                tab === key ? 'bg-grass text-white' : 'text-pine hover:bg-[#f1f4ea]'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>

                {/* ============ CUSTOMERS ============ */}
                {tab === 'customers' && (
                    <>
                        <form onSubmit={submitSearch} className="mb-4 flex gap-2">
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Tìm theo tên hoặc số điện thoại…"
                                className="w-full max-w-xs rounded-[10px] border border-cardBorder px-3.5 py-2 text-[13.5px] outline-none transition focus:border-grass"
                            />
                            <button
                                type="submit"
                                className="rounded-[10px] bg-grass px-4 py-2 text-[13px] font-bold text-white transition hover:bg-pine"
                            >
                                Tìm
                            </button>
                            {filters.q && (
                                <button
                                    type="button"
                                    onClick={() => { setSearch(''); router.get(route('admin.users'), { tab: 'customers' }, { preserveScroll: true }); }}
                                    className="rounded-[10px] border border-cardBorder px-4 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                                >
                                    Xoá lọc
                                </button>
                            )}
                        </form>

                        <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                            {customers.data.length === 0 ? (
                                <div className="py-16 text-center text-moss">
                                    <div className="mb-2 text-[32px]">🧑‍🤝‍🧑</div>
                                    <div className="text-[14px]">{filters.q ? 'Không tìm thấy khách phù hợp' : 'Chưa có khách hàng nào'}</div>
                                </div>
                            ) : (
                                <table className="w-full text-[13px]">
                                    <thead>
                                        <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Khách hàng</th>
                                            <th className="hidden px-4 py-3 text-left font-semibold text-moss md:table-cell">Email</th>
                                            <th className="px-4 py-3 text-center font-semibold text-moss">Số đơn</th>
                                            <th className="px-4 py-3 text-right font-semibold text-moss">Tổng chi</th>
                                            <th className="hidden px-4 py-3 text-center font-semibold text-moss lg:table-cell">Đơn gần nhất</th>
                                            <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {customers.data.map((c) => {
                                            const expanded = expandedId === c.id;
                                            return (
                                                <Fragment key={c.id}>
                                                    <tr
                                                        className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                        onClick={() => toggleDetail(c)}
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-pine">{c.name}</div>
                                                            <div className="font-mono text-[11px] text-moss">{c.phone ?? '-'}</div>
                                                        </td>
                                                        <td className="hidden px-4 py-3 text-moss md:table-cell">
                                                            {c.email ?? <span className="text-[#c4cca8]">-</span>}
                                                        </td>
                                                        <td className="px-4 py-3 text-center font-mono font-bold text-pine">{c.orders_count}</td>
                                                        <td className="px-4 py-3 text-right font-mono font-bold text-pine">{money(c.total_spent)}</td>
                                                        <td className="hidden px-4 py-3 text-center font-mono text-moss lg:table-cell">
                                                            {c.last_order_at ?? <span className="text-[#c4cca8]">-</span>}
                                                        </td>
                                                        <td className="px-4 py-3 text-right" onClick={(e) => e.stopPropagation()}>
                                                            <div className="flex items-center justify-end gap-2">
                                                                <button
                                                                    onClick={() => toggleDetail(c)}
                                                                    className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                                >
                                                                    {expanded ? 'Ẩn' : 'Xem'}
                                                                </button>
                                                                <button
                                                                    onClick={() => { setDeleteTarget({ id: c.id, name: c.name }); setActionError(''); }}
                                                                    className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                                >
                                                                    Xoá
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>

                                                    {expanded && (
                                                        <tr className="border-b border-[#f1f4ea]">
                                                            <td colSpan={6} className="px-6 pb-5 pt-3" style={{ background: '#fafcf7' }}>
                                                                {loadingDetailId === c.id || customerDetail?.id !== c.id ? (
                                                                    <div className="py-4 text-center text-[12.5px] text-moss">Đang tải lịch sử đơn…</div>
                                                                ) : customerDetail.orders.length === 0 ? (
                                                                    <div className="py-4 text-center text-[12.5px] text-moss">Khách chưa có đơn nào.</div>
                                                                ) : (
                                                                    <>
                                                                        <div className="mb-2 text-[12.5px] font-semibold text-moss">
                                                                            Lịch sử đơn ({customerDetail.orders.length}) · đối soát theo SĐT
                                                                        </div>
                                                                        <div className="overflow-hidden rounded-[10px] border border-[#eef2e3]">
                                                                            <table className="w-full text-[12px]">
                                                                                <thead>
                                                                                    <tr style={{ background: '#f1f4ea' }}>
                                                                                        <th className="px-3 py-2 text-left font-semibold text-moss">Mã đơn</th>
                                                                                        <th className="px-3 py-2 text-left font-semibold text-moss">Ngày thuê</th>
                                                                                        <th className="px-3 py-2 text-right font-semibold text-moss">Tổng tiền</th>
                                                                                        <th className="px-3 py-2 text-left font-semibold text-moss">Trạng thái</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>
                                                                                    {customerDetail.orders.map((o) => {
                                                                                        const st = STATUS_STYLE[o.status] ?? STATUS_STYLE.pending;
                                                                                        return (
                                                                                            <tr key={o.id} className="border-t border-[#eef2e3]">
                                                                                                <td className="px-3 py-2">
                                                                                                    <span className="font-mono font-bold text-pine">{o.code}</span>
                                                                                                    {!o.linked && (
                                                                                                        <span className="ml-2 rounded-pill bg-[#eef2e3] px-1.5 py-0.5 text-[10px] font-semibold text-moss">
                                                                                                            vãng lai
                                                                                                        </span>
                                                                                                    )}
                                                                                                </td>
                                                                                                <td className="px-3 py-2 font-mono text-pine">{o.start_date} → {o.end_date}</td>
                                                                                                <td className="px-3 py-2 text-right font-mono font-bold text-ink">{money(o.total_price)}</td>
                                                                                                <td className="px-3 py-2">
                                                                                                    <span className="rounded-pill px-2 py-0.5 text-[11px] font-bold" style={{ color: st.color, background: st.bg }}>
                                                                                                        {STATUS_LABEL[o.status] ?? o.status}
                                                                                                    </span>
                                                                                                </td>
                                                                                            </tr>
                                                                                        );
                                                                                    })}
                                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                    </>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            )}
                        </div>

                        {/* Pagination */}
                        {customers.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between text-[12.5px] text-moss">
                                <span className="font-mono">{customers.from}–{customers.to} / {customers.total}</span>
                                <div className="flex gap-2">
                                    <button
                                        disabled={customers.current_page <= 1}
                                        onClick={() => gotoPage(customers.current_page - 1)}
                                        className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                                    >
                                        Trước
                                    </button>
                                    <button
                                        disabled={customers.current_page >= customers.last_page}
                                        onClick={() => gotoPage(customers.current_page + 1)}
                                        className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                                    >
                                        Sau
                                    </button>
                                </div>
                            </div>
                        )}
                    </>
                )}

                {/* ============ ADMINS ============ */}
                {tab === 'admins' && (
                    <>
                        <div className="mb-4 flex justify-end">
                            <button
                                onClick={() => openCreate('admin')}
                                className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
                                </svg>
                                Thêm quản trị viên
                            </button>
                        </div>

                        <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                            <table className="w-full text-[13px]">
                                <thead>
                                    <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                        <th className="px-4 py-3 text-left font-semibold text-moss">Tên</th>
                                        <th className="px-4 py-3 text-left font-semibold text-moss">SĐT</th>
                                        <th className="px-4 py-3 text-left font-semibold text-moss">Email nhận thông báo</th>
                                        <th className="hidden px-4 py-3 text-left font-semibold text-moss sm:table-cell">Tạo lúc</th>
                                        <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {admins.map((a) => {
                                        const isSelf = a.id === meId;
                                        return (
                                            <tr key={a.id} className="border-b border-[#f1f4ea] last:border-0 hover:bg-[#fafcf7]">
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-pine">{a.name}</span>
                                                    {isSelf && (
                                                        <span className="ml-2 rounded-pill bg-[#dceaf6] px-2 py-0.5 text-[10.5px] font-bold text-[#2a6ea0]">bạn</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-moss">{a.phone ?? '-'}</td>
                                                <td className="px-4 py-3 text-moss">{a.email ?? <span className="text-[#c4cca8]">chưa đặt</span>}</td>
                                                <td className="hidden px-4 py-3 font-mono text-moss sm:table-cell">{a.created_at}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            onClick={() => openEdit(a)}
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                        >
                                                            Sửa
                                                        </button>
                                                        <button
                                                            disabled={isSelf || onlyAdmin}
                                                            title={isSelf ? 'Không thể tự hạ quyền' : onlyAdmin ? 'Không thể hạ quyền admin cuối cùng' : ''}
                                                            onClick={() => { setDemoteTarget(a); setActionError(''); }}
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            Hạ quyền
                                                        </button>
                                                        <button
                                                            disabled={isSelf || onlyAdmin}
                                                            title={isSelf ? 'Không thể xoá chính mình' : onlyAdmin ? 'Không thể xoá admin cuối cùng' : ''}
                                                            onClick={() => { setDeleteTarget({ id: a.id, name: a.name }); setActionError(''); }}
                                                            className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6] disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            Xoá
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {tab === 'shippers' && (
                    <>
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                            <p className="text-[12.5px] text-moss">
                                Shipper đăng nhập ở <span className="font-mono">/shipper/dang-nhap</span> và chỉ thấy đơn được gán cho mình.
                            </p>
                            <button
                                onClick={() => openCreate('shipper')}
                                className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                    <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
                                </svg>
                                Thêm shipper
                            </button>
                        </div>

                        {shippers.length === 0 ? (
                            <div className="rounded-[16px] border border-cardBorder bg-white py-12 text-center text-[13px] text-moss">
                                Chưa có tài khoản shipper nào.
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                                <table className="w-full text-[13px]">
                                    <thead>
                                        <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">Tên</th>
                                            <th className="px-4 py-3 text-left font-semibold text-moss">SĐT</th>
                                            <th className="hidden px-4 py-3 text-left font-semibold text-moss md:table-cell">Email nhận lịch</th>
                                            <th className="px-4 py-3 text-center font-semibold text-moss">Lượt sắp tới</th>
                                            <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {shippers.map((s) => (
                                            <tr key={s.id} className="border-b border-[#f1f4ea] last:border-0 hover:bg-[#fafcf7]">
                                                <td className="px-4 py-3">
                                                    <span className="font-semibold text-pine">{s.name}</span>
                                                    {s.id === meId && (
                                                        <span className="ml-2 rounded-pill bg-[#dceaf6] px-2 py-0.5 text-[10.5px] font-bold text-[#2a6ea0]">bạn</span>
                                                    )}
                                                    {s.is_admin && (
                                                        <span className="ml-2 rounded-pill bg-[#eef5e0] px-2 py-0.5 text-[10.5px] font-bold text-grass">cũng là admin</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-moss">{s.phone ?? '-'}</td>
                                                <td className="hidden px-4 py-3 text-moss md:table-cell">
                                                    {s.email ?? <span className="text-[#c4cca8]">chưa đặt</span>}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {s.upcoming_legs > 0 ? (
                                                        <span className="rounded-pill px-2 py-0.5 text-[11.5px] font-bold" style={{ background: '#f6ddd6', color: '#b3493a' }}>
                                                            {s.upcoming_legs}
                                                        </span>
                                                    ) : (
                                                        <span className="text-[#c4cca8]">0</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            onClick={() => openEdit(s, 'shipper')}
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                        >
                                                            Sửa
                                                        </button>
                                                        <button
                                                            disabled={s.id === meId}
                                                            title={s.id === meId ? 'Không thể tự đổi quyền của mình' : ''}
                                                            onClick={() => { setUnassignTarget(s); setActionError(''); }}
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            Tắt vai
                                                        </button>
                                                        <button
                                                            disabled={s.id === meId}
                                                            title={s.id === meId ? 'Không thể xoá chính mình' : ''}
                                                            onClick={() => {
                                                                setDeleteTarget({
                                                                    id: s.id,
                                                                    name: s.name,
                                                                    warning: s.upcoming_legs > 0
                                                                        ? `Còn ${s.upcoming_legs} lượt giao/thu sắp tới đang gán cho người này — xoá xong các lượt đó sẽ về "chưa gán".`
                                                                        : undefined,
                                                                });
                                                                setActionError('');
                                                            }}
                                                            className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6] disabled:cursor-not-allowed disabled:opacity-40"
                                                        >
                                                            Xoá
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* Tắt vai shipper — cảnh báo nếu còn lượt sắp tới */}
            {unassignTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" onClick={() => setUnassignTarget(null)}>
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-2 text-[17px] font-extrabold text-pine">Tắt vai shipper</h2>
                        <p className="mb-3 text-[13.5px] text-moss">
                            <span className="font-semibold text-pine">{unassignTarget.name}</span> sẽ không đăng nhập được vào khu vực shipper nữa.
                        </p>
                        {unassignTarget.upcoming_legs > 0 && (
                            <p className="mb-3 rounded-[10px] p-2.5 text-[12.5px]" style={{ background: '#f6ddd6', color: '#b3493a' }}>
                                Còn {unassignTarget.upcoming_legs} lượt giao/thu sắp tới — các lượt đó sẽ về &quot;chưa gán&quot;, cần gán lại người khác.
                            </p>
                        )}
                        {actionError && <p className="mb-3 text-[12.5px] text-[#b3493a]">{actionError}</p>}
                        <div className="flex justify-end gap-2">
                            <button
                                onClick={() => setUnassignTarget(null)}
                                className="rounded-[10px] border border-cardBorder px-4 py-2 text-[13px] font-semibold text-pine"
                            >
                                Thôi
                            </button>
                            <button
                                onClick={confirmUnassign}
                                className="rounded-[10px] px-4 py-2 text-[13px] font-bold text-white"
                                style={{ background: '#b3493a' }}
                            >
                                Tắt vai
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Create / Edit admin modal */}
            {modalMode && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8" onClick={closeModal}>
                    <div className="w-full max-w-md rounded-[18px] bg-white p-6 shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">
                            {modalMode === 'create'
                                ? form.data.role === 'shipper' ? 'Thêm shipper' : 'Thêm quản trị viên'
                                : 'Sửa tài khoản'}
                        </h2>
                        <form onSubmit={submitAdmin} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Tên <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    autoFocus
                                />
                                {form.errors.name && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.name}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Số điện thoại <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="tel"
                                    inputMode="tel"
                                    value={form.data.phone}
                                    onChange={(e) => form.setData('phone', e.target.value)}
                                    placeholder="0912345678"
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                />
                                {form.errors.phone && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.phone}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Email nhận thông báo <span className="font-normal text-moss">(tuỳ chọn — nhận mail khi có đơn mới)</span>
                                </label>
                                <input
                                    type="email"
                                    inputMode="email"
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                    placeholder="admin@bopcamping.vn"
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                />
                                {form.errors.email && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.email}</p>}
                            </div>
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Mật khẩu {modalMode === 'create' ? <span className="text-[#b3493a]">*</span> : <span className="font-normal text-moss">(để trống = giữ nguyên)</span>}
                                </label>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) => form.setData('password', e.target.value)}
                                    placeholder={modalMode === 'create' ? 'Tối thiểu 6 ký tự' : '••••••••'}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                />
                                {form.errors.password && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.password}</p>}
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button type="button" onClick={closeModal} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">
                                    Huỷ
                                </button>
                                <button type="submit" disabled={form.processing} className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60">
                                    {form.processing ? 'Đang lưu…' : 'Lưu lại'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Demote confirm */}
            {demoteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Hạ quyền quản trị</h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Tài khoản <span className="font-semibold text-pine">{demoteTarget.name}</span> sẽ trở thành khách hàng thường, không còn vào được trang quản trị.
                        </p>
                        {actionError && (
                            <div className="mb-4 rounded-[9px] bg-[#f6ddd6] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">{actionError}</div>
                        )}
                        <div className="flex justify-end gap-3">
                            <button onClick={() => { setDemoteTarget(null); setActionError(''); }} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">
                                Huỷ
                            </button>
                            <button onClick={confirmDemote} className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine">
                                Hạ quyền
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Delete confirm */}
            {deleteTarget && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Xác nhận xoá người dùng</h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Xoá <span className="font-semibold text-pine">{deleteTarget.name}</span>? Lịch sử đơn vẫn được giữ lại. Hành động không thể hoàn tác.
                        </p>
                        {deleteTarget.warning && (
                            <p className="mb-4 rounded-[10px] p-2.5 text-[12.5px]" style={{ background: '#f6ddd6', color: '#b3493a' }}>
                                {deleteTarget.warning}
                            </p>
                        )}
                        {actionError && (
                            <div className="mb-4 rounded-[9px] bg-[#f6ddd6] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">{actionError}</div>
                        )}
                        <div className="flex justify-end gap-3">
                            <button onClick={() => { setDeleteTarget(null); setActionError(''); }} className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]">
                                Huỷ
                            </button>
                            <button onClick={confirmDelete} className="rounded-[10px] bg-[#b3493a] px-5 py-2 text-[13px] font-bold text-white transition hover:bg-[#8a3328]">
                                Xoá
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

AdminUsers.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
