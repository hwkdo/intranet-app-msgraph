<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auslandseinsatz_memberships', function (Blueprint $table) {
            $table->date('starts_at')->nullable()->after('added_by_upn');
            $table->date('ends_at')->nullable()->after('starts_at');
            $table->string('azure_user_id')->nullable()->after('ends_at');
            $table->timestamp('activated_at')->nullable()->after('azure_user_id');
        });

        DB::table('auslandseinsatz_memberships')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                $createdAt = $row->created_at ?? now()->toDateTimeString();
                $startsAt = date('Y-m-d', strtotime($createdAt));
                $endsAt = isset($row->expires_at) && $row->expires_at
                    ? date('Y-m-d', strtotime($row->expires_at))
                    : date('Y-m-d', strtotime($createdAt.' +1 year'));

                DB::table('auslandseinsatz_memberships')->where('id', $row->id)->update([
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'activated_at' => $row->removed_at === null ? $createdAt : null,
                ]);
            });

        Schema::table('auslandseinsatz_memberships', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auslandseinsatz_memberships', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('added_by_upn');
        });

        DB::table('auslandseinsatz_memberships')->orderBy('id')->each(function (object $row): void {
            DB::table('auslandseinsatz_memberships')->where('id', $row->id)->update([
                'expires_at' => $row->ends_at ? $row->ends_at.' 23:59:59' : null,
            ]);
        });

        Schema::table('auslandseinsatz_memberships', function (Blueprint $table) {
            $table->dropColumn(['starts_at', 'ends_at', 'azure_user_id', 'activated_at']);
        });
    }
};
