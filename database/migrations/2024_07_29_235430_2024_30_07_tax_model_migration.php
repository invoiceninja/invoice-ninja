<?php

use App\Models\Company;
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
        Company::whereNotNull('tax_data')
                ->cursor()
                ->each(function($company){

                    if($company->tax_data?->version == 'alpha')
                    {

                        $company->update(['tax_data' => new \App\DataMapper\Tax\TaxModel($company->tax_data)]);
                        
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