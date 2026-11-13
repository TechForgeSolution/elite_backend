<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elite_users', function (Blueprint $table) {
            $table->boolean('whatsapp_verified')->default(true);
            $table->string('whatsapp_otp_id')->nullable();
            $table->timestamp('whatsapp_otp_expires_at')->nullable();
            $table->string('whatsapp_otp_purpose', 32)->nullable();
        });

        Schema::table('packs', function (Blueprint $table) {
            $table->string('banner_path')->nullable();
        });

        Schema::create('dashboard_advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('banner_path');
            $table->string('redirect_url');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_advertisements');

        Schema::table('packs', function (Blueprint $table) {
            $table->dropColumn('banner_path');
        });

        Schema::table('elite_users', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_verified',
                'whatsapp_otp_id',
                'whatsapp_otp_expires_at',
                'whatsapp_otp_purpose',
            ]);
        });
    }
};
