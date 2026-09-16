<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('royalty_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_units');
            // A null upper bound means "this tier has no ceiling".
            $table->unsignedInteger('max_units')->nullable();
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            // One tier per starting point, per release.
            $table->unique(['release_id', 'min_units']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('royalty_rules');
    }
};
