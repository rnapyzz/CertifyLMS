<?php

declare(strict_types=1);

namespace App\Exceptions\Mentoring;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

/**
 * コーチ本人が行う Google カレンダー連携操作(認可コード交換 / トークン更新)が失敗した場合の例外。
 *
 * 「連携」というユーザー操作そのものの成否を表すため、面談予約 / キャンセル / 空き枠取得のような
 * バックグラウンドの Google 通信(GoogleCalendarService::busyIntervals 等)とは異なり、
 * 呼出元(GoogleCalendarController)まで伝播させて画面にエラーとして表示する。
 * HTTP 422 として Handler が redirect+flash に変換する。
 */
final class GoogleCalendarSyncException extends UnprocessableEntityHttpException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
