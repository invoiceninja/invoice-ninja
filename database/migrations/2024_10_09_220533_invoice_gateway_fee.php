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
        
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'gateway_fee')) {
                $table->decimal('gateway_fee', 13, 6)->default(0);
            }
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
