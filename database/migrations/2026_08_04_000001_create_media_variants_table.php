<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biến thể ảnh đã resize (bopcamping-ix4n).
     *
     * Key theo `source_path` chứ KHÔNG theo product_image_id: một file ảnh được
     * CHIA SẺ giữa nhiều row product_images/combo_images (xem app/Support/MediaRef.php),
     * nên biến thể phải thuộc về FILE để khỏi resize lặp và khỏi lệch nhau.
     */
    public function up(): void
    {
        Schema::create('media_variants', function (Blueprint $table) {
            $table->id();
            $table->string('source_path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            // Với biến thể lớn nhất, `path` có thể trỏ về chính source_path.
            $table->string('path');
            $table->timestamps();

            // Unique composite đã phủ luôn việc tra theo source_path (leftmost prefix)
            // nên không cần thêm index riêng.
            $table->unique(['source_path', 'width']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_variants');
    }
};
