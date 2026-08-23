<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_dispatch_an_all_students_announcement(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => 'メンテナンスのお知らせ',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => 1,
        ]);
        Notification::assertSentTo($student, AdminAnnouncementNotification::class);
    }

    public function test_admin_can_dispatch_a_certification_scoped_announcement(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '資格お知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => '資格お知らせ',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ]);
    }

    public function test_admin_can_dispatch_a_user_scoped_announcement(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別お知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('announcements', [
            'title' => '個別お知らせ',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $student->id,
        ]);
        Notification::assertSentTo($student, AdminAnnouncementNotification::class);
    }

    public function test_title_and_body_are_required(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ]);

        $response->assertSessionHasErrors(['title', 'body']);
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_certification_target_requires_target_certification_id(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::Certification->value,
        ]);

        $response->assertSessionHasErrors('target_certification_id');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_user_target_requires_target_user_id(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
        ]);

        $response->assertSessionHasErrors('target_user_id');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_all_students_target_rejects_extraneous_certification_id(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => $certification->id,
        ]);

        $response->assertSessionHasErrors('target_certification_id');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_target_user_id_must_be_a_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $coach->id,
        ]);

        $response->assertSessionHasErrors('target_user_id');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_coach_and_student_are_forbidden(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $payload = [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ];

        $this->actingAs($coach)->post(route('admin.announcements.store'), $payload)->assertForbidden();
        $this->actingAs($student)->post(route('admin.announcements.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('announcements', 0);
    }
}
