<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        $currency = Currency::find(142);

        if(!$currency){
            Currency::create([
                'id' => 142,
                'name' => 'Samoan Tala',
                'code' => 'WST',
                'symbol' => '$',
                'precision' => '2',
                'thousand_separator' => ',',
                'decimal_separator' => '.',
            ]);
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
