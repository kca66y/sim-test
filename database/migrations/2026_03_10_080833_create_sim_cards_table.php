<?php

use App\Models\Contract;
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
        Schema::create('sim_cards', static function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(Contract::class)
                ->constrained()
                ->restrictOnDelete();

            $table->string('number', 20);
            $table->timestamps();

            $table->index(['contract_id', 'id']);
            $table->index('number');
            $table->index('contract_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sim_cards');
    }
};
