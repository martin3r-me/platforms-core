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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('mollie_customer_id')->nullable()->after('personal_team');
            $table->string('mollie_payment_method_id')->nullable()->after('mollie_customer_id');
            $table->string('payment_method_last_4')->nullable()->after('mollie_payment_method_id');
            $table->string('payment_method_brand')->nullable()->after('payment_method_last_4');
            $table->timestamp('payment_method_expires_at')->nullable()->after('payment_method_brand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'mollie_customer_id',
                'mollie_payment_method_id', 
                'payment_method_last_4',
                'payment_method_brand',
                'payment_method_expires_at'
            ]);
        });
    }
};
