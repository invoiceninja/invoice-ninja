<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class CurrenciesSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        // http://www.localeplanet.com/icu/currency.html
        $currencies = [
            ['id' => 1, 'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 2, 'name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 3, 'name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 4, 'name' => 'South African Rand', 'code' => 'ZAR', 'symbol' => 'R', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 5, 'name' => 'Danish Krone', 'code' => 'DKK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 6, 'name' => 'Israeli Shekel', 'code' => 'ILS', 'symbol' => 'NIS ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 7, 'name' => 'Swedish Krona', 'code' => 'SEK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 8, 'name' => 'Kenyan Shilling', 'code' => 'KES', 'symbol' => 'KSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 9, 'name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 10, 'name' => 'Philippine Peso', 'code' => 'PHP', 'symbol' => 'P ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 11, 'name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 12, 'name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 13, 'name' => 'Singapore Dollar', 'code' => 'SGD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 14, 'name' => 'Norske Kroner', 'code' => 'NOK', 'symbol' => 'kr', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 15, 'name' => 'New Zealand Dollar', 'code' => 'NZD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 16, 'name' => 'Vietnamese Dong', 'code' => 'VND', 'symbol' => '₫', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 17, 'name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF', 'precision' => '2', 'thousand_separator' => '\'', 'decimal_separator' => '.'],
            ['id' => 18, 'name' => 'Guatemalan Quetzal', 'code' => 'GTQ', 'symbol' => 'Q', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 19, 'name' => 'Malaysian Ringgit', 'code' => 'MYR', 'symbol' => 'RM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 20, 'name' => 'Brazilian Real', 'code' => 'BRL', 'symbol' => 'R$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 21, 'name' => 'Thai Baht', 'code' => 'THB', 'symbol' => '฿', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 22, 'name' => 'Nigerian Naira', 'code' => 'NGN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 23, 'name' => 'Argentine Peso', 'code' => 'ARS', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 24, 'name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => 'Tk', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 25, 'name' => 'United Arab Emirates Dirham', 'code' => 'AED', 'symbol' => 'DH ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 26, 'name' => 'Hong Kong Dollar', 'code' => 'HKD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 27, 'name' => 'Indonesian Rupiah', 'code' => 'IDR', 'symbol' => 'Rp', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 28, 'name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 29, 'name' => 'Egyptian Pound', 'code' => 'EGP', 'symbol' => 'E£', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 30, 'name' => 'Colombian Peso', 'code' => 'COP', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 31, 'name' => 'West African Franc', 'code' => 'XOF', 'symbol' => 'CFA ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 32, 'name' => 'Chinese Renminbi', 'code' => 'CNY', 'symbol' => 'RMB ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 33, 'name' => 'Rwandan Franc', 'code' => 'RWF', 'symbol' => 'RF ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 34, 'name' => 'Tanzanian Shilling', 'code' => 'TZS', 'symbol' => 'TSh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 35, 'name' => 'Netherlands Antillean Guilder', 'code' => 'ANG', 'symbol' => '', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 36, 'name' => 'Trinidad and Tobago Dollar', 'code' => 'TTD', 'symbol' => 'TT$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 37, 'name' => 'East Caribbean Dollar', 'code' => 'XCD', 'symbol' => 'EC$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 38, 'name' => 'Ghanaian Cedi', 'code' => 'GHS', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 39, 'name' => 'Bulgarian Lev', 'code' => 'BGN', 'symbol' => 'лв', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => '.'],
            ['id' => 40, 'name' => 'Aruban Florin', 'code' => 'AWG', 'symbol' => 'Afl. ', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => '.'],
            ['id' => 41, 'name' => 'Turkish Lira', 'code' => 'TRY', 'symbol' => 'TL ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 42, 'name' => 'Romanian New Leu', 'code' => 'RON', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 43, 'name' => 'Croatian Kuna', 'code' => 'HRK', 'symbol' => 'kn', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 44, 'name' => 'Saudi Riyal', 'code' => 'SAR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 45, 'name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 46, 'name' => 'Maldivian Rufiyaa', 'code' => 'MVR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 47, 'name' => 'Costa Rican Colón', 'code' => 'CRC', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 48, 'name' => 'Pakistani Rupee', 'code' => 'PKR', 'symbol' => 'Rs ', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 49, 'name' => 'Polish Zloty', 'code' => 'PLN', 'symbol' => 'zł', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 50, 'name' => 'Sri Lankan Rupee', 'code' => 'LKR', 'symbol' => 'LKR', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.', 'swap_currency_symbol' => true],
            ['id' => 51, 'name' => 'Czech Koruna', 'code' => 'CZK', 'symbol' => 'Kč', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 52, 'name' => 'Uruguayan Peso', 'code' => 'UYU', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 53, 'name' => 'Namibian Dollar', 'code' => 'NAD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 54, 'name' => 'Tunisian Dinar', 'code' => 'TND', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 55, 'name' => 'Russian Ruble', 'code' => 'RUB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 56, 'name' => 'Mozambican Metical', 'code' => 'MZN', 'symbol' => 'MT', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 57, 'name' => 'Omani Rial', 'code' => 'OMR', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 58, 'name' => 'Ukrainian Hryvnia', 'code' => 'UAH', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 59, 'name' => 'Macanese Pataca', 'code' => 'MOP', 'symbol' => 'MOP$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 60, 'name' => 'Taiwan New Dollar', 'code' => 'TWD', 'symbol' => 'NT$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 61, 'name' => 'Dominican Peso', 'code' => 'DOP', 'symbol' => 'RD$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 62, 'name' => 'Chilean Peso', 'code' => 'CLP', 'symbol' => '$', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 63, 'name' => 'Icelandic Króna', 'code' => 'ISK', 'symbol' => 'kr', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 64, 'name' => 'Papua New Guinean Kina', 'code' => 'PGK', 'symbol' => 'K', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 65, 'name' => 'Jordanian Dinar', 'code' => 'JOD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 66, 'name' => 'Myanmar Kyat', 'code' => 'MMK', 'symbol' => 'K', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 67, 'name' => 'Peruvian Sol', 'code' => 'PEN', 'symbol' => 'S/ ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 68, 'name' => 'Botswana Pula', 'code' => 'BWP', 'symbol' => 'P', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 69, 'name' => 'Hungarian Forint', 'code' => 'HUF', 'symbol' => 'Ft', 'precision' => '0', 'thousand_separator' => '.', 'decimal_separator' => ',', 'swap_currency_symbol' => true],
            ['id' => 70, 'name' => 'Ugandan Shilling', 'code' => 'UGX', 'symbol' => 'USh ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 71, 'name' => 'Barbadian Dollar', 'code' => 'BBD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 72, 'name' => 'Brunei Dollar', 'code' => 'BND', 'symbol' => 'B$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 73, 'name' => 'Georgian Lari', 'code' => 'GEL', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ' ', 'decimal_separator' => ','],
            ['id' => 74, 'name' => 'Qatari Riyal', 'code' => 'QAR', 'symbol' => 'QR', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 75, 'name' => 'Honduran Lempira', 'code' => 'HNL', 'symbol' => 'L', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 76, 'name' => 'Surinamese Dollar', 'code' => 'SRD', 'symbol' => 'SRD', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 77, 'name' => 'Bahraini Dinar', 'code' => 'BHD', 'symbol' => 'BD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 78, 'name' => 'Venezuelan Bolivars', 'code' => 'VES', 'symbol' => 'Bs.', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 79, 'name' => 'South Korean Won', 'code' => 'KRW', 'symbol' => 'W ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 80, 'name' => 'Moroccan Dirham', 'code' => 'MAD', 'symbol' => 'MAD ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 81, 'name' => 'Jamaican Dollar', 'code' => 'JMD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 82, 'name' => 'Angolan Kwanza', 'code' => 'AOA', 'symbol' => 'Kz', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 83, 'name' => 'Haitian Gourde', 'code' => 'HTG', 'symbol' => 'G', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 84, 'name' => 'Zambian Kwacha', 'code' => 'ZMW', 'symbol' => 'ZK', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 85, 'name' => 'Nepalese Rupee', 'code' => 'NPR', 'symbol' => 'Rs. ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 86, 'name' => 'CFP Franc', 'code' => 'XPF', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'], // precision should be zero
            ['id' => 87, 'name' => 'Mauritian Rupee', 'code' => 'MUR', 'symbol' => 'Rs', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 88, 'name' => 'Cape Verdean Escudo', 'code' => 'CVE', 'symbol' => '', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => '$'],
            ['id' => 89, 'name' => 'Kuwaiti Dinar', 'code' => 'KWD', 'symbol' => 'KD', 'precision' => '3', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 90, 'name' => 'Algerian Dinar', 'code' => 'DZD', 'symbol' => 'DA', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 91, 'name' => 'Macedonian Denar', 'code' => 'MKD', 'symbol' => 'ден', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 92, 'name' => 'Fijian Dollar', 'code' => 'FJD', 'symbol' => 'FJ$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 93, 'name' => 'Bolivian Boliviano', 'code' => 'BOB', 'symbol' => 'Bs', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 94, 'name' => 'Albanian Lek', 'code' => 'ALL', 'symbol' => 'L ', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 95, 'name' => 'Serbian Dinar', 'code' => 'RSD', 'symbol' => 'din', 'precision' => '2', 'thousand_separator' => '.', 'decimal_separator' => ','],
            ['id' => 96, 'name' => 'Lebanese Pound', 'code' => 'LBP', 'symbol' => 'LL ', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 97, 'name' => 'Armenian Dram', 'code' => 'AMD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 98, 'name' => 'Azerbaijan Manat', 'code' => 'AZN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 99, 'name' => 'Bosnia and Herzegovina Convertible Mark', 'code' => 'BAM', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 100, 'name' => 'Belarusian Ruble', 'code' => 'BYN', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 101, 'name' => 'Gibraltar Pound', 'code' => 'GIP', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 102, 'name' => 'Moldovan Leu', 'code' => 'MDL', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 103, 'name' => 'Kazakhstani Tenge', 'code' => 'KZT', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 104, 'name' => 'Ethiopian Birr', 'code' => 'ETB', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 105, 'name' => 'Gambia Dalasi', 'code' => 'GMD', 'symbol' => 'D', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 106, 'name' => 'Paraguayan Guarani', 'code' => 'PYG', 'symbol' => '₲', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 107, 'name' => 'Malawi Kwacha', 'code' => 'MWK', 'symbol' => 'MK', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 108, 'name' => 'Zimbabwean Dollar', 'code' => 'ZWL', 'symbol' => 'Z$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 109, 'name' => 'Cambodian Riel', 'code' => 'KHR', 'symbol' => '៛', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 110, 'name' => 'Vanuatu Vatu', 'code' => 'VUV', 'symbol' => 'VT', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 111, 'name' => 'Cuban Peso', 'code' => 'CUP', 'symbol' => '₱', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 112, 'name' => 'Cayman Island Dollar', 'code' => 'KYD', 'symbol' => '', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 113, 'name' => 'Swazi lilangeni', 'code' => 'SZL', 'symbol' => 'E', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 114, 'name' => 'BZ Dollar', 'code' => 'BZD', 'symbol' => '$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 115, 'name' => 'Libyan Dinar', 'code' => 'LYD', 'symbol' => 'LD', 'precision' => '3', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 116, 'name' => 'Silver Troy Ounce', 'code' => 'XAG', 'symbol' => 'XAG', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 117, 'name' => 'Gold Troy Ounce', 'code' => 'XAU', 'symbol' => 'XAU', 'precision' => '3', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 118, 'name' => 'Nicaraguan Córdoba', 'code' => 'NIO', 'symbol' => 'C$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 119, 'name' => 'Malagasy ariary', 'code' => 'MGA', 'symbol' => 'Ar', 'precision' => '0', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 120, 'name' => "Tongan Pa anga", 'code' => 'TOP', 'symbol' => 'T$', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 121, 'name' => "Lao kip", 'code' => 'LAK', 'symbol' => '₭', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 122, 'name' => "Bhutan Ngultrum", 'code' => 'BTN', 'symbol' => 'Nu', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
            ['id' => 123, 'name' => "Mauritanian Ouguiya", 'code' => 'MRU', 'symbol' => 'UM', 'precision' => '2', 'thousand_separator' => ',', 'decimal_separator' => '.'],
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
            $record = Currency::whereCode($currency['code'])->first();
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
}
