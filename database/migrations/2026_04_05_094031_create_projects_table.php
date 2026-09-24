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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('owner_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->enum('status', ['active', 'on_hold', 'archived'])->nullable();
            $table->date('deadline')->nullable();
            $table->foreign('department_id')
                ->references('id')
                ->on('departments');

            $table->foreign('owner_id')
                ->references('id')
                ->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
