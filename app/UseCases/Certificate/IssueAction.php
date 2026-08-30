<?php

declare(strict_types=1);

namespace App\UseCases\Certificate;

use App\Enums\EnrollmentStatus;
use App\Exceptions\Certification\CertificateAlreadyIssuedException;
use App\Exceptions\Certification\CertificatePdfGenerationException;
use App\Exceptions\Certification\EnrollmentNotPassedException;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Services\CertificatePdfService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 修了証を発行するユースケース。受講生自己発火型の修了処理 `\App\UseCases\Enrollment\ReceiveCertificateAction` から呼び出される。
 *
 * 業務分岐:
 * - Enrollment が `status=passed` + `passed_at != null` でない: EnrollmentNotPassedException（409）
 * - 同一 Enrollment に対する二重呼出: CertificateAlreadyIssuedException（409、事前 lockForUpdate + exists で検出）
 *
 * 修了証レコードの INSERT と PDF 生成(`CertificatePdfService`)は同じ `DB::transaction()` 内で行う。
 * PDF 生成に失敗すると例外が transaction を抜けてロールバックし(呼出元 `ReceiveCertificateAction` の
 * 外側 transaction も含めて)、Enrollment のステータス遷移ごと巻き戻る
 * (「PDF 生成に失敗した場合、修了証は発行されていない状態に保つ」)。
 */
final class IssueAction
{
    public function __construct(private readonly CertificatePdfService $pdf) {}

    /**
     * @throws EnrollmentNotPassedException 受講登録が修了状態ではない
     * @throws CertificateAlreadyIssuedException 同一 Enrollment で修了証が既発行
     * @throws CertificatePdfGenerationException PDF の生成または保存に失敗した
     */
    public function __invoke(Enrollment $enrollment): Certificate
    {
        if ($enrollment->status !== EnrollmentStatus::Passed || $enrollment->passed_at === null) {
            throw new EnrollmentNotPassedException;
        }

        return DB::transaction(function () use ($enrollment) {
            // 二重発行ガード: lockForUpdate で同時呼出を直列化し、enrollment_id UNIQUE 違反を例外メッセージ判別ではなく事前 SELECT で確定検出する
            $existing = Certificate::query()
                ->where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new CertificateAlreadyIssuedException;
            }

            $certificate = Certificate::create([
                'user_id' => $enrollment->user_id,
                'enrollment_id' => $enrollment->id,
                'certification_id' => $enrollment->certification_id,
                'pdf_path' => 'certificates/'.Str::ulid().'.pdf',
                'issued_at' => now(),
            ]);

            $this->pdf->generate($certificate);

            return $certificate;
        });
    }
}
