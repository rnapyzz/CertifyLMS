<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Plan;
use App\Models\User;
use App\Policies\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PlanPolicy の ability × Role のマトリクス検証。全 ability が admin 専用であることを網羅する。
 * delete の可否条件(下書き かつ 受講者未紐づき)は Policy ではなく `Plan\DestroyAction` の責務のため、
 * ここでは role 判定のみを検証する(詳細検証は tests/Feature/Http/Plan/DestroyTest.php)。
 */
class PlanPolicyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('abilityMatrix')]
    public function test_ability_matches_role_expectation(string $actingRole, string $ability, bool $expected): void
    {
        $actor = User::factory()->{$actingRole}()->create();
        $plan = Plan::factory()->create();
        $policy = new PlanPolicy;

        $result = $ability === 'create'
            ? $policy->create($actor)
            : $policy->{$ability}($actor, $plan);

        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$ability} で ".($expected ? 'true' : 'false').' を返すはず',
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function abilityMatrix(): array
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'publish', 'archive', 'unarchive'];
        $roles = [
            'admin' => true,
            'coach' => false,
            'student' => false,
        ];

        $cases = [];
        foreach ($roles as $role => $expected) {
            foreach ($abilities as $ability) {
                $caseKey = $expected
                    ? "{$role} は {$ability} を実行できる"
                    : "{$role} は {$ability} を実行できない";
                $cases[$caseKey] = [$role, $ability, $expected];
            }
        }

        return $cases;
    }
}
