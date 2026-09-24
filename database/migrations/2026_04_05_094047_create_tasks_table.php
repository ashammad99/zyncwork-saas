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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('parent_task_id')->nullable();
            $table->unsignedBigInteger('depends_on_task_id')->nullable();

            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();

            $table->enum('status', ['backlog', 'todo', 'in_progress', 'in_review', 'completed'])->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->nullable();
            $table->enum('impact', ['low', 'medium', 'high'])->nullable();
            $table->enum('effort', ['low', 'medium', 'high'])->nullable();

            $table->decimal('estimated_hours', 5, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_recurring')->nullable();
            $table->string('recurrence_rule', 255)->nullable();
            $table->date('recurrence_ends_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects');

            $table->foreign('assignee_id')
                ->references('id')
                ->on('users');

            $table->foreign('created_by')
                ->references('id')
                ->on('users');

            $table->foreign('parent_task_id')
                ->references('id')
                ->on('tasks');

            $table->foreign('depends_on_task_id')
                ->references('id')
                ->on('tasks');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
