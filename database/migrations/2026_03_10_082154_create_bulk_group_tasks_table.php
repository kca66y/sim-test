<?php

use App\Models\Contract;
use App\Models\SimGroup;
use App\Models\User;
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
        Schema::create('bulk_group_tasks', static function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignIdFor(Contract::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(SimGroup::class)
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignIdFor(User::class, 'created_by')
                ->constrained()
                ->restrictOnDelete();

            $table->string('status');

            $table->unsignedBigInteger('total_count')->default(0);
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);

            $table->string('payload_path')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulk_group_tasks');
    }
};
