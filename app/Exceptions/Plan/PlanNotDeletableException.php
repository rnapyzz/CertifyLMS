<?php

declare(strict_types=1);

namespace App\Exceptions\Plan;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 削除条件を満たさないプランを削除しようとした際の例外(HTTP 409)。
 * `Plan\DestroyAction` が「下書き状態 かつ 受講者(ソフト削除済含む)が 1 人も紐づいていない場合のみ削除可」の
 * ドメインルールから throw する。users.plan_id / user_plan_logs.plan_id は restrictOnDelete のため、
 * 参照が残った状態での削除は本来 DB 制約違反になる。
 */
final class PlanNotDeletableException extends ConflictHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('下書き状態かつ受講者が紐づいていないプランのみ削除できます。', $previous);
    }
}
