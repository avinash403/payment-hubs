<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('payment_gateway_id')->unsigned();
            $table->foreign('payment_gateway_id')->references('id')->on('payments')->onDelete('cascade');
            $table->string('session_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('amount');
            $table->string('currency')->default('usd');
            $table->string('status', 30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
