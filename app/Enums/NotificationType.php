<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 通知の種別。値は `resources/views/notifications/` 配下の Blade が種別アイコンの出し分けに使う
 * 文字列(`notification_type`)と一致させる。
 */
enum NotificationType: string
{
    case ChatMessageReceived = 'chat_message_received';
    case QaReplyReceived = 'qa_reply_received';
    case MeetingReserved = 'meeting_reserved';
    case MeetingCanceled = 'meeting_canceled';
    case AdminAnnouncement = 'admin_announcement';
}
