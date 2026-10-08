<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intranet_app_msgraph_onenote_pages', function (Blueprint $table) {
            $table->string('lightrag_instance', 32)->nullable()->after('page_title');
        });

        DB::table('intranet_app_msgraph_onenote_pages')
            ->whereNull('lightrag_instance')
            ->update(['lightrag_instance' => 'team-meetings']);
    }

    public function down(): void
    {
        Schema::table('intranet_app_msgraph_onenote_pages', function (Blueprint $table) {
            $table->dropColumn('lightrag_instance');
        });
    }
};
