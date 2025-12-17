<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();

            // Email type and direction
            $table->enum('type', ['sent', 'received', 'draft', 'template'])->default('sent');

            // Basic email fields
            $table->string('from_email')->index();
            $table->string('from_name')->nullable();
            $table->string('to_email')->index();
            $table->json('cc')->nullable(); // Carbon copy emails
            $table->json('bcc')->nullable(); // Blind carbon copy emails
            $table->string('subject');
            $table->longText('body'); // HTML body
            $table->longText('plain_body')->nullable(); // Plain text version
            $table->json('attachments')->nullable(); // File attachments info

            // Status tracking
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // Retry logic
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);

            // Gmail API integration
            $table->string('gmail_message_id')->nullable()->unique();
            $table->string('gmail_thread_id')->nullable()->index();
            $table->string('in_reply_to')->nullable(); // For threading replies
            $table->text('references')->nullable(); // Email references header

            // Related models (polymorphic)
            $table->unsignedBigInteger('related_booking_id')->nullable()->index();
            $table->string('related_model_type')->nullable();
            $table->unsignedBigInteger('related_model_id')->nullable();
            $table->index(['related_model_type', 'related_model_id']);

            // Additional metadata
            $table->json('tags')->nullable(); // For categorization
            $table->integer('priority')->default(1); // 1=high, 2=medium, 3=low
            $table->timestamp('scheduled_at')->nullable(); // For scheduled emails
            $table->timestamp('processed_at')->nullable(); // When job was processed

            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['from_email', 'created_at']);
            $table->index(['to_email', 'created_at']);
            $table->index(['related_booking_id', 'status']);

            // Foreign keys
            $table->foreign('related_booking_id')->references('id')->on('bookings')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
