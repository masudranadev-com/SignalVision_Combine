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
        Schema::create('signal_formats', function (Blueprint $table) {
            $table->id();
            $table->string("group_id")->nullable();
            $table->string("format_name")->nullable();
            $table->text("format_formula")->nullable();
            $table->text("format_demo")->nullable();
            $table->integer("short")->default(100);
            $table->string("logo")->nullable();
            $table->string("type")->nullable();
            $table->text("features")->nullable();
            $table->text("group_link")->nullable();
            $table->string("status")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signal_formats');
    }
};
