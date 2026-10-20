<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_msgraph_onenote_pages', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('lightrag_instance');
            $table->timestamp('source_modified_at')->nullable()->after('content_hash');
        });
    }

    public function down(): void
    {
        Schema::table('intranet_app_msgraph_onenote_pages', function (Blueprint $table) {
            $table->dropColumn(['content_hash', 'source_modified_at']);
        });
    }
};
