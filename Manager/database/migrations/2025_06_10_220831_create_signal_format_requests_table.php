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
        Schema::create('signal_format_requests', function (Blueprint $table) {
            $table->id();
            $table->text("group_name")->nullable();
            $table->text("group_link")->nullable();
            $table->text("sample_signal")->nullable();
            $table->text("email")->nullable();
            $table->text("status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_format_requests');
    }
};
