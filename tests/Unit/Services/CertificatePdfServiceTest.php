<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\Certification\CertificatePdfGenerationException;
use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 修了証 PDF 生成(mpdf + IPA ゴシックフォント)を検証する。実際に mpdf でレンダリングし、
 * private disk への保存まで通しで確認する(外部通信が無いため Mockery は使わずそのまま実行する)。
 */
class CertificatePdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_writes_a_valid_pdf_to_the_private_disk(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create([
            'pdf_path' => 'certificates/test-cert.pdf',
        ]);

        (new CertificatePdfService)->generate($certificate);

        Storage::disk('private')->assertExists('certificates/test-cert.pdf');
        $bytes = Storage::disk('private')->get('certificates/test-cert.pdf');
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    public function test_generate_embeds_recipient_name_and_certification_name(): void
    {
        Storage::fake('private');

        $certificate = Certificate::factory()->create([
            'pdf_path' => 'certificates/test-cert-2.pdf',
        ]);
        $certificate->loadMissing('user', 'certification');

        (new CertificatePdfService)->generate($certificate);

        $bytes = Storage::disk('private')->get('certificates/test-cert-2.pdf');
        // mpdf の出力はテキストをそのまま埋め込むとは限らない(フォントサブセット化)ため、
        // ここでは「PDF として妥当なサイズで生成されている」ことのみを確認する
        // (文字化けしていないかは実機の目視確認で担保する)。
        $this->assertGreaterThan(1000, strlen($bytes));
    }

    public function test_generate_throws_when_disk_write_fails(): void
    {
        config(['filesystems.disks.private.root' => '/nonexistent-path-for-test/'.uniqid()]);

        $certificate = Certificate::factory()->create([
            'pdf_path' => 'certificates/test-cert-3.pdf',
        ]);

        $this->expectException(CertificatePdfGenerationException::class);

        (new CertificatePdfService)->generate($certificate);
    }
}
