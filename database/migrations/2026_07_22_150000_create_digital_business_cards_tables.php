<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_business_cards')) {
            Schema::create('digital_business_cards', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();
                $table->boolean('is_published')->default(false);
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('middle_name')->nullable();
                $table->string('job_title')->nullable();
                $table->string('company_name')->nullable();
                $table->string('avatar')->nullable();
                $table->string('logo')->nullable();
                $table->string('cover_image')->nullable();
                $table->text('headline')->nullable();
                $table->text('about')->nullable();
                $table->json('contact_methods')->nullable();
                $table->string('background_color')->default('#101827');
                $table->string('accent_color')->default('#7357ff');
                $table->string('text_color')->default('#ffffff');
                $table->string('theme_mode')->default('default');
                $table->string('button_style')->default('rounded');
                $table->string('font_family')->default('system');
                $table->boolean('lead_form_enabled')->default(true);
                $table->string('lead_form_title')->default('Share your contact details');
                $table->text('lead_form_description')->nullable();
                $table->json('lead_form_fields')->nullable();
                $table->json('lead_notification_emails')->nullable();
                $table->boolean('lead_send_confirmation')->default(false);
                $table->string('lead_confirmation_subject')->nullable();
                $table->text('privacy_url')->nullable();
                $table->boolean('lead_consent_required')->default(true);
                $table->string('meta_title')->nullable();
                $table->text('meta_description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('digital_business_card_blocks')) {
            Schema::create('digital_business_card_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_business_card_id')->constrained()->cascadeOnDelete();
                $table->string('type');
                $table->string('title')->nullable();
                $table->text('content')->nullable();
                $table->text('url')->nullable();
                $table->string('button_label')->nullable();
                $table->json('data')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
                $table->index(['digital_business_card_id', 'is_enabled', 'sort_order'], 'card_blocks_display_index');
            });
        }

        if (! Schema::hasTable('digital_business_card_leads')) {
            Schema::create('digital_business_card_leads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_business_card_id')->constrained()->cascadeOnDelete();
                $table->string('name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('company')->nullable();
                $table->text('comment')->nullable();
                $table->json('custom_data')->nullable();
                $table->boolean('consent_given')->default(false);
                $table->string('source')->nullable();
                $table->timestamp('submitted_at');
                $table->timestamps();
                $table->index(['digital_business_card_id', 'submitted_at'], 'card_leads_card_submitted_index');
            });
        }

        if (! Schema::hasTable('digital_business_card_events')) {
            Schema::create('digital_business_card_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_business_card_id')->constrained()->cascadeOnDelete();
                $table->foreignId('digital_business_card_block_id')->nullable()->constrained()->nullOnDelete();
                $table->string('type');
                $table->string('visitor_hash', 64)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();
                $table->index(['digital_business_card_id', 'type', 'occurred_at'], 'card_events_card_type_occurred_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_business_card_events');
        Schema::dropIfExists('digital_business_card_leads');
        Schema::dropIfExists('digital_business_card_blocks');
        Schema::dropIfExists('digital_business_cards');
    }
};
