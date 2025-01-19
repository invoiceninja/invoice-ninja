<?php

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Model::unguard();

        $currencies = [
            ['id' => 105, 'name' => 'Gambia Dalasi', 'code' => 'GMD', 'symbol' => 'D', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 106, 'name' => 'Paraguayan Guarani', 'code' => 'PYG', 'symbol' => '₲', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 107, 'name' => 'Malawi Kwacha', 'code' => 'MWK', 'symbol' => 'MK', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 108, 'name' => 'Zimbabwean Gold', 'code' => 'ZWL', 'symbol' => 'Z$', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 109, 'name' => 'Cambodian Riel', 'code' => 'KHR', 'symbol' => '៛', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 110, 'name' => 'Vanuatu Vatu', 'code' => 'VUV', 'symbol' => 'VT', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],

        ];

        foreach ($currencies as $currency) {
            $record = Currency::query()->whereCode($currency['code'])->first();
            if ($record) {
                $record->name = $currency['name'];
                $record->symbol = $currency['symbol'];
                $record->precision = $currency['precision'];
                $record->thousand_separator = $currency['thousand_separator'];
                $record->decimal_separator = $currency['decimal_separator'];
                if (isset($currency['swap_currency_symbol'])) {
                    $record->swap_currency_symbol = $currency['swap_currency_symbol'];
                }
                $record->save();
            } else {
                Currency::create($currency);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
};
