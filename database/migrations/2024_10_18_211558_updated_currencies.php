<?php

use App\Models\Currency;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        Model::unguard();

        $currencies = [
            ['id' => 124, 'name' => 'Bermudian Dollar', 'code' => 'BMD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 125, 'name' => 'Central African CFA Franc', 'code' => 'XAF', 'symbol' => 'Fr', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 126, 'name' => 'Congolese Franc', 'code' => 'CDF', 'symbol' => 'Fr', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 127, 'name' => 'Djiboutian Franc', 'code' => 'DJF', 'symbol' => 'Fr', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 128, 'name' => 'Eritrean Nakfa', 'code' => 'ERN', 'symbol' => 'Nfk', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 129, 'name' => 'Falkland Islands Pound', 'code' => 'FKP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 130, 'name' => 'Guinean Franc', 'code' => 'GNF', 'symbol' => 'Fr', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => ''],
            ['id' => 131, 'name' => 'Iraqi Dinar', 'code' => 'IQD', 'symbol' => 'ع.د', 'precision' => '3', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 132, 'name' => 'Lesotho Loti', 'code' => 'LSL', 'symbol' => 'L', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 133, 'name' => 'Mongolian Tugrik', 'code' => 'MNT', 'symbol' => '₮', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 134, 'name' => 'Seychellois Rupee', 'code' => 'SCR', 'symbol' => '₨', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 135, 'name' => 'Solomon Islands Dollar', 'code' => 'SBD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 136, 'name' => 'Somali Shilling', 'code' => 'SOS', 'symbol' => 'Sh', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 137, 'name' => 'South Sudanese Pound', 'code' => 'SSP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 138, 'name' => 'Sudanese Pound', 'code' => 'SDG', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 139, 'name' => 'Tajikistani Somoni', 'code' => 'TJS', 'symbol' => 'ЅM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 140, 'name' => 'Turkmenistani Manat', 'code' => 'TMT', 'symbol' => 'T', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 141, 'name' => 'Uzbekistani Som', 'code' => 'UZS', 'symbol' => 'so\'m', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
        ];

        foreach ($currencies as $currency) {
            $record = Currency::where('code', $currency['code'])->first();
            if (!$record) {
                Currency::create($currency);
            }
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
