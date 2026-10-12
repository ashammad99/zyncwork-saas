<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant admin's request for a full export of their organisation's data
     * (a ZIP of CSVs, built by a queued job). Tenant-owned.
     *
     * status: queued | running | ready | failed | expired (a string, constants
     * on TenantExport). file_path is relative to the private local disk and is
     * cleared once the file is deleted (7 days after it is ready).
     *
     * requested_by gets a composite (requested_by, tenant_id) foreign key, as
     * enforce_same_tenant_links does for every link between tenant-owned rows,
     * so an export can never point at another tenant's user.
     */
    public function up(): void
    {
        Schema::create('tenant_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by');
            $table->string('status', 20);
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('row_counts')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->foreign(['requested_by', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_exports');
    }
};
