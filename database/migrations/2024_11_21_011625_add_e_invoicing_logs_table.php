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
        Schema::create('e_invoicing_logs', function (Blueprint $table){
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedInteger('legal_entity_id')->index();
            $table->string('license_key')->nullable();
            $table->string('direction')->default('sent');
            $table->text('notes')->nullable();
            $table->integer('counter')->default(0);
            $table->softDeletes('deleted_at', 6);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
