<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GoogleCredentialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * コーチが連携した Google アカウントの OAuth トークン。1 ユーザーにつき 1 件(user_id UNIQUE)。
 * 存在すること自体が「連携中」を表す(`User::googleCredential()` の有無で画面が分岐する)。
 */
class GoogleCredential extends Model
{
    /** @use HasFactory<GoogleCredentialFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'calendar_id',
        'connected_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at->isPast();
    }
}
