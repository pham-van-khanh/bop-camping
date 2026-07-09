<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\EditorHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Màn soạn "nội dung chi tiết" (setup/mô tả lớn) của sản phẩm — Epic 1 mục 1.4.
 * Tách riêng khỏi form sản phẩm vì editor cần full-width.
 */
class ProductContentController extends Controller
{
    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/ProductContent', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'setup_content' => $product->setup_content,
            ],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'setup_content' => 'nullable|string|max:200000',
        ], [
            'setup_content.max' => 'Nội dung quá dài.',
        ]);

        $product->update([
            'setup_content' => EditorHtml::clean($data['setup_content'] ?? null),
        ]);

        return back()->with('success', 'Đã lưu nội dung chi tiết.');
    }
}
