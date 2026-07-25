<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-wtuv (T1) — nền đơn cha/con: quan hệ, scope topLevel (ẩn con),
 * trạng thái cha suy từ con.
 */
class ParentChildOrderModelTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attrs = []): Order
    {
        return Order::create(array_merge([
            'customer_name' => 'Khách', 'customer_phone' => '0900000000',
            'start_date' => '2030-08-01', 'end_date' => '2030-08-02',
            'status' => 'confirmed', 'payment_method' => 'cod',
        ], $attrs));
    }

    /** @test */
    public function parent_and_children_relations_link_both_ways(): void
    {
        $parent = $this->order(['is_parent' => true, 'start_date' => '2030-08-01', 'end_date' => '2030-08-05']);
        $c1 = $this->order(['parent_id' => $parent->id, 'start_date' => '2030-08-01', 'end_date' => '2030-08-02']);
        $c2 = $this->order(['parent_id' => $parent->id, 'start_date' => '2030-08-04', 'end_date' => '2030-08-05']);

        $this->assertTrue($parent->fresh()->is_parent);
        $this->assertSame(2, $parent->children()->count());
        $this->assertSame($parent->id, $c1->parent->id);
        // children sắp theo ngày bắt đầu
        $this->assertSame([$c1->id, $c2->id], $parent->children->pluck('id')->all());
    }

    /** @test */
    public function top_level_scope_hides_children(): void
    {
        $normal = $this->order();
        $parent = $this->order(['is_parent' => true]);
        $this->order(['parent_id' => $parent->id]);
        $this->order(['parent_id' => $parent->id]);

        // topLevel = đơn thường + cha, KHÔNG gồm 2 con.
        $ids = Order::topLevel()->pluck('id')->sort()->values()->all();
        $this->assertSame([$normal->id, $parent->id], $ids);
    }

    /** @test */
    public function parent_aggregate_status_is_derived_from_children(): void
    {
        $parent = $this->order(['is_parent' => true, 'status' => 'pending']);
        $c1 = $this->order(['parent_id' => $parent->id, 'status' => 'returned']);
        $c2 = $this->order(['parent_id' => $parent->id, 'status' => 'renting']);
        $this->assertSame('renting', $parent->fresh()->load('children')->aggregateStatus());

        $c2->update(['status' => 'returned']);
        $this->assertSame('returned', $parent->fresh()->load('children')->aggregateStatus());

        $c1->update(['status' => 'cancelled']);
        $c2->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $parent->fresh()->load('children')->aggregateStatus());
    }
}
