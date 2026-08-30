<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Certification\CertificatePdfGenerationException;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Throwable;

/**
 * 修了証 PDF(resources/views/certificates/pdf.blade.php)を mpdf でレンダリングし、
 * private disk(`storage/app/private`)へ保存する。
 *
 * 日本語表記が要件のため、IPA ゴシック(`resources/fonts/ipag.ttf`、IPA Font License v1.0)を
 * デフォルトフォントとして登録する。mpdf 本体にはバンドルされていない
 * (デプロイ環境の OS フォントに依存させないよう、フォント自体をリポジトリに同梱している)。
 *
 * `final` 不採用: `App\UseCases\Certificate\IssueAction` が「PDF 生成失敗時に発行全体を
 * ロールバックする」ことをテストする際、`Mockery::mock` で意図的に例外を投げさせる必要があるため
 * (`GoogleCalendarService` 等と同じ理由。外部 API ではなくローカル処理だが、失敗パスを
 * 呼出元でテストする目的は同じ)。
 */
class CertificatePdfService
{
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing('user', 'certification');

        $html = view('certificates.pdf', ['certificate' => $certificate])->render();

        try {
            $pdfBytes = $this->render($html);
        } catch (MpdfException|Throwable $e) {
            throw new CertificatePdfGenerationException('修了証 PDF の生成に失敗しました。', previous: $e);
        }

        try {
            $stored = Storage::disk('private')->put($certificate->pdf_path, $pdfBytes);
        } catch (Throwable $e) {
            throw new CertificatePdfGenerationException('修了証 PDF の保存に失敗しました。', previous: $e);
        }

        if ($stored === false) {
            throw new CertificatePdfGenerationException('修了証 PDF の保存に失敗しました。');
        }
    }

    private function render(string $html): string
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'ipagothic' => ['R' => 'ipag.ttf'],
            ],
            'default_font' => 'ipagothic',
        ]);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }
}
