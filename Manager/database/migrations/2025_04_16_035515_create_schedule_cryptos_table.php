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
        Schema::create('schedule_cryptos', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('trade_id')->nullable();
            $table->bigInteger('order_id')->nullable();
            $table->bigInteger('chat_id')->nullable();
            $table->string('instruments', 20)->nullable();
            $table->string('tp_mode')->nullable();
            $table->string('market')->nullable();
            $table->string('entry_target')->nullable();
            $table->string('stop_loss')->nullable();
            $table->float('stop_loss_percentage')->nullable();
            $table->float('stop_loss_price')->nullable();
            $table->float('leverage')->default(0);
            $table->string('take_profit1')->nullable();
            $table->string('take_profit2')->nullable();
            $table->string('take_profit3')->nullable();
            $table->string('take_profit4')->nullable();
            $table->string('take_profit5')->nullable();
            $table->string('take_profit6')->nullable();
            $table->string('take_profit7')->nullable();
            $table->string('take_profit8')->nullable();
            $table->string('take_profit9')->nullable();
            $table->string('take_profit10')->nullable();
            $table->string('profit_strategy')->nullable();
            $table->string('partial_profits_tp1')->nullable();
            $table->string('partial_profits_tp2')->nullable();
            $table->string('partial_profits_tp3')->nullable();
            $table->string('partial_profits_tp4')->nullable();
            $table->string('partial_profits_tp5')->nullable();
            $table->string('partial_profits_tp6')->nullable();
            $table->string('partial_profits_tp7')->nullable();
            $table->string('partial_profits_tp8')->nullable();
            $table->string('partial_profits_tp9')->nullable();
            $table->string('partial_profits_tp10')->nullable();
            $table->string('partial_order_ids')->nullable();
            $table->integer('specific_tp')->nullable();
            $table->string('position_size_usdt')->nullable();
            $table->integer('height_tp')->nullable();
            $table->float('height_price')->nullable();
            $table->string('last_alert')->nullable();
            $table->string('provider_id')->nullable();
            $table->integer('qty_step')->nullable(); 
            $table->string('status')->nullable(); 
            $table->string('type')->nullable(); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_cryptos');
    }
};
