<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use RuntimeException;
use Throwable;

/**
 * 修了証 PDF の生成(mpdf レンダリング)または保存(private disk への書き込み)に失敗した場合の例外。
 *
 * `App\UseCases\Certificate\IssueAction` の `DB::transaction()` 内で投げることで、
 * 呼出元(`ReceiveCertificateAction` の外側の transaction も含む)を丸ごとロールバックさせ、
 * 「PDF 生成に失敗した場合、修了証は発行されていない状態に保つ」を満たす。
 */
final class CertificatePdfGenerationException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
