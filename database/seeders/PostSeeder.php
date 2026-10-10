<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Bài mẫu Cẩm nang (bopcamping-ennp) — CHỈ DÙNG KHI DEV.
 *
 * Cả 3 bài đều là BẢN NHÁP (status=draft): chúng là khuôn để chủ shop xem cách trình
 * bày một bài (H2/H3, danh sách, trích dẫn) rồi sửa lại bằng giọng của shop. Không có
 * bài nào tự hiện ra trang khách hay sitemap. DatabaseSeeder chỉ gọi seeder này ở môi
 * trường local/testing.
 *
 * Nội dung chỉ gồm kinh nghiệm thực hành phổ biến, không có số liệu hay cam kết bịa.
 */
class PostSeeder extends Seeder
{
    public function run(): void
    {
        $checklist = Post::create([
            'title' => 'Checklist đồ cắm trại cho người mới: mang gì, bỏ gì',
            'slug' => 'checklist-do-cam-trai-cho-nguoi-moi',
            'excerpt' => 'Danh sách đồ cần chuẩn bị cho chuyến cắm trại qua đêm đầu tiên, chia theo nhóm ngủ – nấu – sáng – cá nhân, kèm mẹo thứ gì nên thuê thay vì mua.',
            'content' => $this->checklist(),
            'status' => Post::STATUS_DRAFT,
            'seo_title' => 'Checklist đồ cắm trại cho người mới',
            'seo_description' => 'Chuẩn bị gì cho chuyến cắm trại qua đêm đầu tiên? Checklist theo nhóm ngủ, nấu ăn, chiếu sáng, đồ cá nhân và mẹo chọn đồ nên thuê thay vì mua.',
        ]);

        $tent = Post::create([
            'title' => 'Chọn lều 2 người hay 4 người? Cách tính cho đúng',
            'slug' => 'chon-leu-2-nguoi-hay-4-nguoi',
            'excerpt' => 'Số người ghi trên lều là số người nằm sát nhau, chưa tính hành lý. Cách chọn cỡ lều theo nhóm đi, mùa và kiểu chuyến đi.',
            'content' => $this->tent(),
            'status' => Post::STATUS_DRAFT,
            'seo_title' => 'Chọn lều 2 người hay 4 người cho đúng',
            'seo_description' => 'Số người ghi trên lều là nằm sát nhau, chưa tính đồ đạc. Hướng dẫn chọn cỡ lều theo số người, mùa và kiểu chuyến đi để ngủ thoải mái hơn.',
        ]);

        Post::create([
            'title' => 'Cắm trại mùa mưa: chuẩn bị gì để ngủ khô ráo',
            'slug' => 'cam-trai-mua-mua',
            'excerpt' => 'Chọn chỗ dựng lều, căng tăng che, lót đáy và giữ đồ khô — những việc nhỏ quyết định đêm cắm trại mùa mưa dễ chịu hay ướt sũng.',
            'content' => $this->rain(),
            'status' => Post::STATUS_DRAFT,
            'seo_title' => 'Cắm trại mùa mưa: chuẩn bị gì để ngủ khô',
            'seo_description' => 'Cắm trại mùa mưa cần chọn chỗ dựng lều cao ráo, căng tăng che đúng cách, lót đáy và giữ đồ khô. Các bước chuẩn bị cụ thể cho người mới.',
        ]);

        // Gợi ý sẵn vài món liên quan nếu dữ liệu demo có — để bản nháp xem trước được
        // khối "Đồ dùng trong bài". Không có thì thôi, bài vẫn dùng được.
        $tentCategory = Category::where('slug', 'leu-cam-trai')->first();
        if ($tentCategory) {
            $tent->products()->sync(
                Product::active()->where('category_id', $tentCategory->id)->limit(3)->pluck('id')
            );
        }
        $checklist->products()->sync(Product::active()->orderBy('id')->limit(4)->pluck('id'));
    }

    private function checklist(): string
    {
        return '<p>Chuyến cắm trại đầu tiên thường hỏng vì hai lý do: quên một món quan trọng, hoặc mang quá nhiều thứ không dùng tới. Danh sách dưới đây chia theo nhóm để bạn soát nhanh trước khi lên đường.</p>'
            .'<h2>1. Nhóm ngủ — quan trọng nhất</h2>'
            .'<p>Ngủ không ngon thì cả chuyến mệt. Đây là nhóm nên ưu tiên chất lượng.</p>'
            .'<ul><li><strong>Lều</strong> đủ chỗ cho cả người lẫn hành lý (xem bài chọn cỡ lều).</li>'
            .'<li><strong>Tấm lót đáy lều</strong> chống ẩm và chống thủng đáy.</li>'
            .'<li><strong>Túi ngủ</strong> hợp nhiệt độ ban đêm nơi bạn đến — trên núi đêm lạnh hơn ban ngày nhiều.</li>'
            .'<li><strong>Đệm hơi hoặc thảm xốp</strong>: cách nhiệt với mặt đất, thứ hay bị bỏ quên nhất.</li>'
            .'<li>Gối hơi hoặc gối nhỏ.</li></ul>'
            .'<h2>2. Nhóm nấu ăn</h2>'
            .'<ul><li>Bếp dã ngoại và bình gas mini (mang dư một bình).</li>'
            .'<li>Nồi, chảo, kẹp gắp, dao nhỏ, thớt gấp.</li>'
            .'<li>Bát đũa, cốc dùng lại được — hạn chế đồ nhựa dùng một lần.</li>'
            .'<li>Nước uống, nước rửa và túi đựng rác mang về.</li></ul>'
            .'<h2>3. Nhóm chiếu sáng</h2>'
            .'<ul><li>Đèn treo lều hoặc đèn bàn cho khu sinh hoạt chung.</li>'
            .'<li>Đèn pin đội đầu cho từng người — rảnh tay khi nấu ăn, đi vệ sinh đêm.</li>'
            .'<li>Sạc dự phòng đã sạc đầy.</li></ul>'
            .'<h2>4. Nhóm cá nhân</h2>'
            .'<ul><li>Áo khoác giữ ấm, một bộ đồ khô để thay, dép.</li>'
            .'<li>Kem chống muỗi, kem chống nắng, thuốc cá nhân, băng dán.</li>'
            .'<li>Khăn, đồ vệ sinh cá nhân, giấy ướt.</li></ul>'
            .'<h2>5. Nên thuê hay nên mua?</h2>'
            .'<p>Nếu mỗi năm bạn chỉ đi vài lần, những món cồng kềnh và đắt như <strong>lều, túi ngủ, bàn ghế, bếp</strong> nên thuê: không tốn chỗ cất, không phải tự bảo quản, và mỗi chuyến chọn được cỡ phù hợp với nhóm. Đồ cá nhân nhỏ (đèn đội đầu, cốc, khăn) thì nên tự sắm.</p>'
            .'<blockquote><p>Mẹo: dựng thử lều một lần ở nhà hoặc nhờ shop hướng dẫn khi nhận đồ — tới nơi trời tối mới mở hộp là dễ luống cuống nhất.</p></blockquote>';
    }

    private function tent(): string
    {
        return '<p>Con số "2 người", "4 người" trên lều là số người <strong>nằm sát nhau</strong> trên đệm tiêu chuẩn — chưa tính ba lô, giày dép hay khoảng trống để trở mình. Vì vậy chọn đúng số người đi thường sẽ chật.</p>'
            .'<h2>Quy tắc dễ nhớ: cộng thêm 1</h2>'
            .'<p>Với đa số chuyến đi, hãy chọn lều có sức chứa <strong>lớn hơn số người thật 1 bậc</strong>:</p>'
            .'<ul><li>Đi 1 người → lều 2 người.</li>'
            .'<li>Đi 2 người → lều 3 hoặc 4 người nếu muốn để đồ trong lều.</li>'
            .'<li>Gia đình 2 lớn 1–2 nhỏ → lều 4–6 người.</li></ul>'
            .'<h2>Khi nào lều "đúng số" vẫn ổn?</h2>'
            .'<ul><li>Bạn đi trekking phải tự mang lều — nhẹ quan trọng hơn rộng.</li>'
            .'<li>Lều có tiền sảnh (khoảng mái che trước cửa) để ba lô bên ngoài.</li>'
            .'<li>Cả nhóm có thêm tăng (tấm che) dựng khu sinh hoạt riêng.</li></ul>'
            .'<h2>Ngoài sức chứa, xem gì nữa?</h2>'
            .'<h3>Chiều cao</h3>'
            .'<p>Lều cao đủ để ngồi thẳng lưng giúp thay đồ, chơi bài buổi tối dễ chịu hơn nhiều — đặc biệt khi đi cùng trẻ nhỏ.</p>'
            .'<h3>Số cửa</h3>'
            .'<p>Lều 3–4 người có hai cửa thì ra vào ban đêm không phải bò qua người khác.</p>'
            .'<h3>Khả năng chống mưa</h3>'
            .'<p>Lều hai lớp (lớp trong thoáng khí + lớp ngoài chống nước) phù hợp mùa mưa và nơi có sương đêm nhiều.</p>'
            .'<h2>Tóm lại</h2>'
            .'<p>Đi chơi gần, có xe chở đồ: cứ chọn rộng hơn một bậc. Đi bộ đường dài: chọn đúng số người và tận dụng tiền sảnh. Nếu còn phân vân, nhắn shop kèm số người, số ngày và nơi định đến để được gợi ý cỡ lều.</p>';
    }

    private function rain(): string
    {
        return '<p>Mưa không làm hỏng chuyến cắm trại — nước đọng dưới đáy lều mới làm hỏng. Phần lớn rắc rối đến từ chỗ dựng lều và cách che chắn, không phải từ bản thân cái lều.</p>'
            .'<h2>1. Chọn chỗ dựng lều</h2>'
            .'<ul><li>Chọn chỗ <strong>hơi cao</strong> hơn xung quanh, tránh lòng chảo, rãnh khô hay bãi cỏ trũng — mưa xuống là thành vũng.</li>'
            .'<li>Không dựng sát suối hay dưới cây có cành khô.</li>'
            .'<li>Quay cửa lều tránh hướng gió chính.</li></ul>'
            .'<h2>2. Lót đáy đúng cách</h2>'
            .'<p>Tấm lót phải <strong>nhỏ hơn đáy lều</strong> một chút và gấp mép vào trong. Nếu tấm lót thò ra ngoài, nó sẽ hứng nước mưa rồi dẫn thẳng vào dưới lều.</p>'
            .'<h2>3. Căng lớp ngoài và tăng che</h2>'
            .'<ul><li>Kéo căng lớp ngoài (rainfly) để vải không chạm vào lều trong — chỗ nào chạm là chỗ đó thấm.</li>'
            .'<li>Dựng tăng che phía trên khu nấu ăn để vẫn sinh hoạt được khi mưa.</li>'
            .'<li>Mang dư dây và cọc: đất ướt giữ cọc kém hơn.</li></ul>'
            .'<h2>4. Giữ đồ khô</h2>'
            .'<ul><li>Cho quần áo, túi ngủ vào túi chống nước hoặc túi nilon dày trước khi bỏ vào ba lô.</li>'
            .'<li>Để giày, áo mưa ướt ở tiền sảnh, không mang vào trong lều.</li>'
            .'<li>Hé lỗ thông gió để hơi nước trong lều thoát ra, tránh đọng sương bên trong.</li></ul>'
            .'<h2>5. Khi trả đồ thuê</h2>'
            .'<p>Lều bị ướt thì gấp gọn mang về và báo tình trạng cho shop khi trả, đừng cố phơi vội ngoài trời đang mưa. (Chủ shop: chỉnh đoạn này theo đúng quy định trả đồ ướt của shop trước khi đăng.)</p>'
            .'<blockquote><p>Xem dự báo thời tiết trước ngày đi và cả trong ngày: mưa giông kèm sấm sét ở vùng đồi trống thì nên dời lịch, an toàn quan trọng hơn kế hoạch.</p></blockquote>';
    }
}
