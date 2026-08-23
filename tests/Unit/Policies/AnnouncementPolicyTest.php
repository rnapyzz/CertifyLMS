<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AnnouncementPolicy の判定を検証する。全 ability が admin 専用であることを role マトリクスで確認する。
 */
class AnnouncementPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_is_admin_only(): void
    {
        $policy = new AnnouncementPolicy;

        $this->assertTrue($policy->viewAny(User::factory()->admin()->make()));
        $this->assertFalse($policy->viewAny(User::factory()->coach()->make()));
        $this->assertFalse($policy->viewAny(User::factory()->student()->make()));
    }

    public function test_create_is_admin_only(): void
    {
        $policy = new AnnouncementPolicy;

        $this->assertTrue($policy->create(User::factory()->admin()->make()));
        $this->assertFalse($policy->create(User::factory()->coach()->make()));
        $this->assertFalse($policy->create(User::factory()->student()->make()));
    }

    public function test_view_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create(['created_by_user_id' => $admin->id]);
        $policy = new AnnouncementPolicy;

        $this->assertTrue($policy->view($admin, $announcement));
        $this->assertFalse($policy->view(User::factory()->coach()->make(), $announcement));
        $this->assertFalse($policy->view(User::factory()->student()->make(), $announcement));
    }
}
