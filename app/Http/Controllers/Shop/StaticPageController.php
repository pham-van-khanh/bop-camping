<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Trang tĩnh phía khách (Epic 4): /gioi-thieu. */
class StaticPageController extends Controller
{
    public function about(): Response
    {
        $page = StaticPage::about();

        return Inertia::render('About', [
            'page' => [
                'title' => $page->title,
                'cover_url' => $page->cover_path ? Storage::disk('media')->url($page->cover_path) : null,
                'content' => $page->content,
            ],
        ]);
    }
}
