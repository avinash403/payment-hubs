<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentGatewaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();

            $table->bigInteger('payment_gateway_type_id')->unsigned()->nullable();
            $table->foreign('payment_gateway_type_id')->references('id')->on('payment_gateways')->onDelete('cascade');

            // user who is creating the payment gateway
            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // app key will be used to figure out the client in "where" statement hence the index,
            // but secret will be just check against the app_key
            $table->string('app_id')->index();
            $table->string('app_secret');
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
        Schema::dropIfExists('payment_gateways');
    }
}
