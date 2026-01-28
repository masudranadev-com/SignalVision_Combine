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
        Schema::create('partial_profit_templates', function (Blueprint $table) {
            $table->id();
            $table->string("user_id")->nullabl();
            $table->string("name")->nullabl();
            $table->string("tp1")->nullabl();
            $table->string("tp2")->nullabl();
            $table->string("tp3")->nullabl();
            $table->string("tp4")->nullabl();
            $table->string("tp5")->nullabl();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partial_profit_templates');
    }
};
