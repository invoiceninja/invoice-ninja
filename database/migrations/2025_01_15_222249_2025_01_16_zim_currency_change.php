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
        
        \Illuminate\Support\Facades\Artisan::call('ninja:design-update');

        $currency = \App\Models\Currency::where('code', 'ZWL')->first();

        if($currency){
            $currency->update(['name' => 'Zimbabwe Gold']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
