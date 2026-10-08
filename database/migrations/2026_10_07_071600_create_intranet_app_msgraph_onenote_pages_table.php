<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_msgraph_onenote_pages', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 16);
            $table->string('owner_id', 128);
            $table->string('notebook_id', 400);
            $table->string('notebook_name');
            $table->string('section_id', 400);
            $table->string('section_name');
            $table->string('page_id', 400)->unique('iam_onenote_page_uq');
            $table->string('page_title');
            $table->string('lightrag_doc_id')->nullable();
            $table->string('track_id')->nullable();
            $table->string('status', 32);
            $table->text('error_message')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_msgraph_onenote_pages');
    }
};
