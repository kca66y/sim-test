<?php

use App\Models\SimCard;
use App\Models\SimGroup;
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
        Schema::create('sim_card_group', static function (Blueprint $table) {
            $table->foreignIdFor(SimCard::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(SimGroup::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->primary(['sim_card_id', 'sim_group_id']);

            $table->index(['sim_group_id', 'sim_card_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sim_card_group');
    }
};
