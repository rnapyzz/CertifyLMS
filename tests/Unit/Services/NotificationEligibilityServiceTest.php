<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\NotificationEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * NotificationEligibilityService の判定を role × status のマトリクスで検証する。
 * admin は常に対象外、student/coach は in_progress のときのみ対象。
 */
class NotificationEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('matrix')]
    public function test_is_eligible(UserRole $role, UserStatus $status, bool $expected): void
    {
        $user = User::factory()->create(['role' => $role->value, 'status' => $status->value]);
        $service = new NotificationEligibilityService;

        $this->assertSame($expected, $service->isEligible($user));
    }

    /**
     * @return array<string, array{0: UserRole, 1: UserStatus, 2: bool}>
     */
    public static function matrix(): array
    {
        return [
            'in_progress student is eligible' => [UserRole::Student, UserStatus::InProgress, true],
            'in_progress coach is eligible' => [UserRole::Coach, UserStatus::InProgress, true],
            'in_progress admin is not eligible' => [UserRole::Admin, UserStatus::InProgress, false],
            'invited student is not eligible' => [UserRole::Student, UserStatus::Invited, false],
            'graduated student is not eligible' => [UserRole::Student, UserStatus::Graduated, false],
            'withdrawn student is not eligible' => [UserRole::Student, UserStatus::Withdrawn, false],
            'graduated coach is not eligible' => [UserRole::Coach, UserStatus::Graduated, false],
        ];
    }
}
