<?php

namespace Database\Seeders;

use App\Models\PaymentGatewayType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->seedPaymentGatewayTypes();
    }

    /**
     * Seeds payment gateway types
     */
    private function seedPaymentGatewayTypes()
    {
        PaymentGatewayType::updateOrCreate(['name'=>'stripe']);
        PaymentGatewayType::updateOrCreate(['name'=>'paypal']);
    }
}
