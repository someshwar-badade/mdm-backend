<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('enrollment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->string('wifi_password')->nullable();
            $table->string('wifi_security_type')->nullable();
            $table->string('android_package_name')->default('com.brick.mdm');
            $table->string('android_checksum')->nullable();
            $table->string('android_download_url')->nullable();
            $table->json('android_extras')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_profiles');
    }
};
