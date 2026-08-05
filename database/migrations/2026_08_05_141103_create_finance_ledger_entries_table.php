<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_ledger_entries', function (Blueprint $table) {
            $table->id();
            /** income | expense */
            $table->string('direction', 16);
            /** iap_sale | ad_revenue | gift_payout | manual | kdv | income_tax_note */
            $table->string('source', 32);
            $table->foreignId('category_id')->nullable()->constrained('finance_expense_categories')->nullOnDelete();
            $table->date('entry_date');
            $table->decimal('amount_try', 12, 2);
            $table->string('currency', 8)->default('TRY');
            $table->string('label', 200)->nullable();
            $table->text('note')->nullable();
            /** multinet | papara | havale | parsela | other */
            $table->string('payout_method', 32)->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['entry_date', 'direction']);
            $table->index(['source', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_entries');
    }
};
