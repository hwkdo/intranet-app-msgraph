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
        Schema::create('intranet_app_msgraph_secret_expiry_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('key_id')->unique()->comment('Eindeutige Secret-ID aus Microsoft Graph (passwordCredential.keyId)');
            $table->string('application_id')->nullable()->comment('Entra App Object-ID');
            $table->timestamp('first_warning_sent_at')->nullable();
            $table->timestamp('last_warning_sent_at')->nullable();
            $table->timestamps();

            $table->index('key_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intranet_app_msgraph_secret_expiry_notifications');
    }
};
