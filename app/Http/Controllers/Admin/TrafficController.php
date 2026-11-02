<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisitEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Thống kê truy cập: lượt xem, sản phẩm được xem nhiều, nguồn, và số lần khách bấm liên hệ. */
class TrafficController extends Controller
{
    private const PERIODS = ['today' => 0, '7d' => 6, '30d' => 29];

    public function index(Request $request): Response
    {
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : '7d';
        $from = Carbon::today()->subDays(self::PERIODS[$period]);
        $events = fn () => VisitEvent::where('created_at', '>=', $from);

        $views = $events()->whereIn('type', [VisitEvent::TYPE_PAGE, VisitEvent::TYPE_PRODUCT]);
        $contactByMethod = $events()->where('type', VisitEvent::TYPE_CONTACT)
            ->select('method', DB::raw('count(*) as c'))->groupBy('method')->pluck('c', 'method');

        $perDay = $events()->select(DB::raw('date(created_at) as d'), 'type', DB::raw('count(*) as c'), DB::raw('count(distinct visitor_id) as v'))
            ->groupBy('d', 'type')->get()->groupBy('d');
        $chart = [];
        for ($day = $from->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $rows = $perDay->get($day->toDateString(), collect());
            $chart[] = [
                'label' => $day->format('d/m'),
                'views' => (int) $rows->whereIn('type', [VisitEvent::TYPE_PAGE, VisitEvent::TYPE_PRODUCT])->sum('c'),
                'contacts' => (int) $rows->where('type', VisitEvent::TYPE_CONTACT)->sum('c'),
            ];
        }

        return Inertia::render('Admin/Traffic', [
            'period' => $period,
            'totals' => [
                'visitors' => (int) (clone $views)->distinct()->count('visitor_id'),
                'page_views' => (int) (clone $views)->count(),
                'product_views' => (int) $events()->where('type', VisitEvent::TYPE_PRODUCT)->count(),
                'contacts' => (int) $contactByMethod->sum(),
                'contact_visitors' => (int) $events()->where('type', VisitEvent::TYPE_CONTACT)->distinct()->count('visitor_id'),
            ],
            'contact_by_method' => collect(VisitEvent::METHODS)->map(fn ($m) => ['method' => $m, 'count' => (int) ($contactByMethod[$m] ?? 0)])->all(),
            'chart' => $chart,
            'top_products' => $events()->where('type', VisitEvent::TYPE_PRODUCT)->whereNotNull('product_id')
                ->select('product_id', DB::raw('count(*) as views'), DB::raw('count(distinct visitor_id) as visitors'))
                ->groupBy('product_id')->orderByDesc('views')->limit(10)->with('product:id,name')->get()
                ->map(fn ($r) => ['name' => $r->product?->name ?? 'Đã xoá', 'views' => (int) $r->views, 'visitors' => (int) $r->visitors])->all(),
            'top_pages' => $events()->where('type', VisitEvent::TYPE_PAGE)
                ->select('path', DB::raw('count(*) as views'))->groupBy('path')->orderByDesc('views')->limit(10)->get()
                ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views])->all(),
            'sources' => (clone $views)->select(DB::raw("coalesce(source, 'Trực tiếp / không rõ') as source"), DB::raw('count(distinct visitor_id) as visitors'))
                ->groupBy('source')->orderByDesc('visitors')->limit(10)->get()
                ->map(fn ($r) => ['source' => $r->source, 'visitors' => (int) $r->visitors])->all(),
            'recent_contacts' => $events()->where('type', VisitEvent::TYPE_CONTACT)->with(['product:id,name', 'user:id,name,phone'])
                ->latest('created_at')->limit(30)->get()
                ->map(fn ($e) => [
                    'at' => $e->created_at->format('d/m H:i'),
                    'method' => $e->method,
                    'page' => $e->product?->name ?? $e->path,
                    'source' => $e->source,
                    'who' => $e->user ? $e->user->name.' · '.$e->user->phone : null,
                ])->all(),
        ]);
    }
}
