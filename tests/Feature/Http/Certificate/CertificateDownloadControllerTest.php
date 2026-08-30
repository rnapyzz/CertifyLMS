<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certificate;

use App\Enums\UserStatus;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

/**
 * 修了証 PDF ダウンロードの認可(受講生本人 / 担当コーチ / 管理者)を検証する。
 */
class CertificateDownloadControllerTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    private function createCertificateWithFile(): Certificate
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create(['pdf_path' => 'certificates/dl-test.pdf']);
        Storage::disk('private')->put($certificate->pdf_path, '%PDF-1.4 fake content');

        return $certificate;
    }

    public function test_owner_student_can_download(): void
    {
        $certificate = $this->createCertificateWithFile();

        $this->actingAs($certificate->user)
            ->get(route('certificates.download', $certificate))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_other_student_cannot_download(): void
    {
        $certificate = $this->createCertificateWithFile();
        $other = User::factory()->student()->create();

        $this->actingAs($other)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_download(): void
    {
        $certificate = $this->createCertificateWithFile();
        $certificate->loadMissing('certification');
        $coach = User::factory()->coach()->create();
        $this->assignCoach($coach, $certificate->certification);

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertOk();
    }

    public function test_unassigned_coach_cannot_download(): void
    {
        $certificate = $this->createCertificateWithFile();
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_admin_can_download_any_certificate(): void
    {
        $certificate = $this->createCertificateWithFile();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('certificates.download', $certificate))
            ->assertOk();
    }

    public function test_returns_404_when_pdf_file_missing_on_disk(): void
    {
        Storage::fake('private');
        $certificate = Certificate::factory()->create(['pdf_path' => 'certificates/missing.pdf']);

        $this->actingAs($certificate->user)
            ->get(route('certificates.download', $certificate))
            ->assertNotFound();
    }

    public function test_graduated_student_can_still_download_own_certificate(): void
    {
        $certificate = $this->createCertificateWithFile();
        $certificate->user->update(['status' => UserStatus::Graduated->value]);

        $this->actingAs($certificate->user->fresh())
            ->get(route('certificates.download', $certificate))
            ->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $certificate = $this->createCertificateWithFile();

        $this->get(route('certificates.download', $certificate))
            ->assertRedirect(route('login'));
    }
}
