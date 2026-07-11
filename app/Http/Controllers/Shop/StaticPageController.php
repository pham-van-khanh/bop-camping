<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use App\Services\SeoService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Trang tĩnh phía khách (Epic 4): /gioi-thieu. */
class StaticPageController extends Controller
{
    public function __construct(private SeoService $seo) {}

    public function about(): Response
    {
        $page = StaticPage::about();
        $cover = $page->cover_path ? Storage::disk('media')->url($page->cover_path) : null;

        return Inertia::render('About', [
            'page' => [
                'title' => $page->title,
                'cover_url' => $cover,
                'content' => $page->content,
            ],
            // SEO: title trang + description tự sinh từ nội dung (strip HTML).
            'seo' => $this->seo->page(
                $page->title.' | BỐP CAMPING',
                $page->content,
                $cover,
                jsonld: $this->seo->breadcrumb([
                    ['Trang chủ', url('/')],
                    ['Giới thiệu', url('/gioi-thieu')],
                ]),
            ),
        ]);
    }
}
