<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('elastic_search_migrations_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('batch');
            $table->string('index');
            $table->string('migrations')->index('elastic_search_migrations_logs_migration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elastic_search_migrations_logs');
    }

};
