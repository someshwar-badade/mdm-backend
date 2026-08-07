<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('device_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('serial_number')->nullable()->index();
            $table->string('imei')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('hardware_manufacturer')->nullable();
            $table->string('hardware_model')->nullable();
            $table->string('os_version')->nullable();
            $table->integer('sdk_version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_identities');
    }
};
