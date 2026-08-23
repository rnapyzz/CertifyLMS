<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\CertificationCoachAttached;
use App\Events\CertificationCoachDetached;
use App\Events\ChatMessagePosted;
use App\Events\MeetingCanceled;
use App\Events\MeetingReserved;
use App\Events\QaReplyPosted;
use App\Listeners\SendChatMessageNotification;
use App\Listeners\SendMeetingCanceledNotification;
use App\Listeners\SendMeetingReservedNotification;
use App\Listeners\SendQaReplyNotification;
use App\Listeners\SyncChatMembersOnCoachAssignmentChanged;
use App\Listeners\UpdateLastLoginAt;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            UpdateLastLoginAt::class,
        ],
        CertificationCoachAttached::class => [
            SyncChatMembersOnCoachAssignmentChanged::class,
        ],
        CertificationCoachDetached::class => [
            SyncChatMembersOnCoachAssignmentChanged::class,
        ],
        QaReplyPosted::class => [
            SendQaReplyNotification::class,
        ],
        ChatMessagePosted::class => [
            SendChatMessageNotification::class,
        ],
        MeetingReserved::class => [
            SendMeetingReservedNotification::class,
        ],
        MeetingCanceled::class => [
            SendMeetingCanceledNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
