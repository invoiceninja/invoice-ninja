<?php

use App\Utils\Ninja;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if(Ninja::isSelfHost())
        {        
            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasColumn('companies', 'e_invoicing_token')) {
                    $table->dropColumn('e_invoicing_token');
                }
            });
        }
        
        if (!Schema::hasColumn('accounts', 'e_invoicing_token')) {

            Schema::table('accounts', function (Blueprint $table) {
                
                $table->string('e_invoicing_token')->nullable();
            });

        }

    }
};
