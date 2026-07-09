<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Upload ảnh chèn vào nội dung rich text (TipTap) ở admin — trả URL để editor nhúng. */
class EditorImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Chỉ ảnh tĩnh, không SVG (stored XSS — CWE-434), như thumbnail sản phẩm
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'image.mimes' => 'Chỉ nhận ảnh jpg, png hoặc webp.',
            'image.max' => 'Ảnh tối đa 4MB.',
        ]);

        $path = $request->file('image')->store('editor', 'media');

        return response()->json(['url' => Storage::disk('media')->url($path)]);
    }
}
