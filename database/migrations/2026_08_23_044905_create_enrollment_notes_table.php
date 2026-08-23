<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            // コーチメモは受講登録に従属する業務記録のため、他の enrollment_id 参照(restrictOnDelete)と
            // 異なり cascadeOnDelete とする(親の受講登録が削除された場合、配下のメモも一覧から除外される)。
            $table->foreignUlid('enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_notes');
    }
};
