<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            // public reply (visible to customer) vs internal note (agents only)
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
