<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * bopcamping-boq — auto-fill /tra-cuu: prefill SĐT khi đăng nhập; sau đặt đơn flash SĐT để dựng link.
 */
class LookupAutofillTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function logged_in_user_phone_prefills_lookup_form(): void
    {
        $user = User::factory()->create(['phone' => '0912345678']);

        $this->actingAs($user)->get(route('lookup'))->assertInertia(fn (Assert $page) => $page
            ->component('OrderLookup')
            ->where('query.phone', '0912345678')
            ->where('query.code', ''));
    }

    /** @test */
    public function guest_lookup_form_phone_is_empty(): void
    {
        $this->get(route('lookup'))->assertInertia(fn (Assert $page) => $page
            ->where('query.phone', '')
            ->where('query.code', ''));
    }

    /** @test */
    public function explicit_query_phone_overrides_user_phone(): void
    {
        $user = User::factory()->create(['phone' => '0912345678']);

        $this->actingAs($user)->get(route('lookup', ['phone' => '0999999999', 'code' => 'BOP-X']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('query.phone', '0999999999')
                ->where('query.code', 'BOP-X'));
    }
}
