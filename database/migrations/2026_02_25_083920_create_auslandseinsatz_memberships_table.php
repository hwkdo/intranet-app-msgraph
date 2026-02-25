<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auslandseinsatz_memberships', function (Blueprint $table) {
            $table->id();
            $table->string('upn');
            $table->string('user_display_name');
            $table->string('added_by_upn');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();

            $table->index('upn');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auslandseinsatz_memberships');
    }
};
