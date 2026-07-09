<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\EditorHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Nền tảng editor (Epic 1 T1): sanitize HTML TipTap + upload ảnh chèn vào nội dung. */
class AdminEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * Sanitize: script/style/onerror bị loại (stored XSS — CWE-79), thẻ nội dung + ảnh giữ nguyên.
     *
     * @test
     */
    public function editor_html_strips_script_keeps_content_and_img(): void
    {
        $dirty = '<h2>Setup lều</h2><p onclick="x()">Bước 1</p>'
            .'<script>alert(1)</script>'
            .'<img src="https://cdn.example.com/a.jpg" alt="lều" onerror="hack()">'
            .'<a href="javascript:alert(1)">độc</a><a href="https://bop.vn" target="_blank">ok</a>';

        $clean = EditorHtml::clean($dirty);

        $this->assertStringContainsString('<h2>Setup lều</h2>', $clean);
        $this->assertStringContainsString('<img src="https://cdn.example.com/a.jpg" alt="lều"', $clean);
        $this->assertStringContainsString('href="https://bop.vn"', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    /**
     * Rỗng hoặc chỉ còn thẻ trống sau khi lọc -> null (tránh lưu "<p></p>").
     *
     * @test
     */
    public function editor_html_empty_becomes_null(): void
    {
        $this->assertNull(EditorHtml::clean(null));
        $this->assertNull(EditorHtml::clean('   '));
        $this->assertNull(EditorHtml::clean('<p><br></p>'));
        $this->assertNull(EditorHtml::clean('<script>alert(1)</script>'));
    }

    /** @test */
    public function admin_can_upload_editor_image(): void
    {
        Storage::fake('media');

        $response = $this->actingAs($this->admin())->post('/admin/editor/images', [
            'image' => UploadedFile::fake()->create('setup.jpg', 200, 'image/jpeg'),
        ]);

        $response->assertOk()->assertJsonStructure(['url']);

        $path = str_replace(Storage::disk('media')->url(''), '', $response->json('url'));
        $this->assertStringStartsWith('editor/', $path);
        Storage::disk('media')->assertExists($path);
    }

    /** Chặn SVG (stored XSS — CWE-434) như thumbnail sản phẩm. @test */
    public function editor_image_rejects_svg(): void
    {
        Storage::fake('media');

        $this->actingAs($this->admin())->post('/admin/editor/images', [
            'image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');
    }

    /** @test */
    public function guest_cannot_upload_editor_image(): void
    {
        Storage::fake('media');

        $this->post('/admin/editor/images', [
            'image' => UploadedFile::fake()->create('setup.jpg', 200, 'image/jpeg'),
        ])->assertRedirect('/admin/login');
    }
}
