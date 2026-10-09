<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('acceptance_records', function (Blueprint $table): void {
            $table->char('id', 32)->primary();
            $table->string('marker', 120);
            $table->string('upload_path', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_records');
    }
};
