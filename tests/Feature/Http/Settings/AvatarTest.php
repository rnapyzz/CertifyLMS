<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_upload_an_avatar(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create(['avatar_url' => null]);
        $file = UploadedFile::fake()->image('avatar.png', 200, 200)->size(500);

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $student->refresh();
        $this->assertNotNull($student->avatar_url);
        $this->assertStringStartsWith('/storage/avatars/', $student->avatar_url);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $student->avatar_url));
    }

    public function test_uploading_a_new_avatar_removes_the_previous_file(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create(['avatar_url' => null]);

        $first = UploadedFile::fake()->image('first.png')->size(100);
        $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $first]);
        $firstPath = str_replace('/storage/', '', $student->fresh()->avatar_url);
        Storage::disk('public')->assertExists($firstPath);

        $second = UploadedFile::fake()->image('second.png')->size(100);
        $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $second]);
        $secondPath = str_replace('/storage/', '', $student->fresh()->avatar_url);

        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $file = UploadedFile::fake()->create('not-an-image.pdf', 100, 'application/pdf');

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $file = UploadedFile::fake()->image('too-big.png')->size(3000);

        $response = $this->actingAs($student)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_can_delete_avatar_and_it_reverts_to_unset(): void
    {
        Storage::fake('public');
        $student = User::factory()->student()->inProgress()->create();
        $file = UploadedFile::fake()->image('avatar.png')->size(100);
        $this->actingAs($student)->post(route('settings.avatar.store'), ['avatar' => $file]);
        $path = str_replace('/storage/', '', $student->fresh()->avatar_url);
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($student)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertNull($student->fresh()->avatar_url);
        Storage::disk('public')->assertMissing($path);
    }
}
