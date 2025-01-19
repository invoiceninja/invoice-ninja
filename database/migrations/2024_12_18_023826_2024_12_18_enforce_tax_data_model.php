<?php

use App\Utils\Ninja;
use App\Models\Company;
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
        if(Ninja::isSelfHost()){

            
                Company::query()->cursor()->each(function ($company) {
                    $company->tax_data = new \App\DataMapper\Tax\TaxModel($company->tax_data);
                    $company->save();
                });

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
