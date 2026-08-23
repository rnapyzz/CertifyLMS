<?php

declare(strict_types=1);

namespace App\Exceptions\EnrollmentGoal;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * 個人目標の達成マーク / 達成解除が不正な開始状態から呼ばれた際の例外(HTTP 409)。
 */
final class EnrollmentGoalInvalidTransitionException extends ConflictHttpException
{
    public static function forMarkAchieved(): self
    {
        return new self('未達成の目標のみ達成にできます。');
    }

    public static function forUnmarkAchieved(): self
    {
        return new self('達成済の目標のみ達成を取り消せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
