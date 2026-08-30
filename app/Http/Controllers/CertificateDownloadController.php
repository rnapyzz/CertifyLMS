<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * 修了証 PDF のダウンロード(ファイル添付形式)。
 *
 * 認可は `CertificatePolicy::download`(受講生本人 / 担当コーチ / 管理者)に委譲する。
 * `active-learning` ミドルウェアは route に付けない(修了証は退会前まで含め永続的にダウンロード可能なため)。
 *
 * `Storage::download()` は disk の実装によって `BinaryFileResponse` または `StreamedResponse` を
 * 返しうる(local disk でもテスト環境の fake disk 等では StreamedResponse になる)。両者は
 * 兄弟クラス(共に `Response` の直接の子)のため、戻り値の型は共通の親である `Response` で受ける。
 */
class CertificateDownloadController extends Controller
{
    public function download(Certificate $certificate): Response
    {
        $this->authorize('download', $certificate);

        if (! Storage::disk('private')->exists($certificate->pdf_path)) {
            abort(404);
        }

        $certificate->loadMissing('certification');
        $filename = $this->downloadFilename($certificate);

        return Storage::disk('private')->download($certificate->pdf_path, $filename);
    }

    private function downloadFilename(Certificate $certificate): string
    {
        $name = $certificate->certification?->name ?? '修了証';
        $safeName = preg_replace('/[\\/:*?"<>|]/u', '_', $name) ?? $name;

        return "修了証_{$safeName}.pdf";
    }
}
