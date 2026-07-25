<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_business_cards')) {
            return;
        }

        $columns = [
            'is_published' => fn (Blueprint $table) => $table->boolean('is_published')->default(false),
            'last_name' => fn (Blueprint $table) => $table->string('last_name')->nullable(),
            'middle_name' => fn (Blueprint $table) => $table->string('middle_name')->nullable(),
            'job_title' => fn (Blueprint $table) => $table->string('job_title')->nullable(),
            'company_name' => fn (Blueprint $table) => $table->string('company_name')->nullable(),
            'avatar' => fn (Blueprint $table) => $table->string('avatar')->nullable(),
            'logo' => fn (Blueprint $table) => $table->string('logo')->nullable(),
            'cover_image' => fn (Blueprint $table) => $table->string('cover_image')->nullable(),
            'headline' => fn (Blueprint $table) => $table->text('headline')->nullable(),
            'about' => fn (Blueprint $table) => $table->text('about')->nullable(),
            'contact_methods' => fn (Blueprint $table) => $table->json('contact_methods')->nullable(),
            'background_color' => fn (Blueprint $table) => $table->string('background_color')->default('#101827'),
            'accent_color' => fn (Blueprint $table) => $table->string('accent_color')->default('#7357ff'),
            'text_color' => fn (Blueprint $table) => $table->string('text_color')->default('#ffffff'),
            'theme_mode' => fn (Blueprint $table) => $table->string('theme_mode')->default('default'),
            'button_style' => fn (Blueprint $table) => $table->string('button_style')->default('rounded'),
            'font_family' => fn (Blueprint $table) => $table->string('font_family')->default('system'),
            'lead_form_enabled' => fn (Blueprint $table) => $table->boolean('lead_form_enabled')->default(true),
            'lead_form_title' => fn (Blueprint $table) => $table->string('lead_form_title')->default('Share your contact details'),
            'lead_form_description' => fn (Blueprint $table) => $table->text('lead_form_description')->nullable(),
            'lead_form_fields' => fn (Blueprint $table) => $table->json('lead_form_fields')->nullable(),
            'lead_notification_emails' => fn (Blueprint $table) => $table->json('lead_notification_emails')->nullable(),
            'lead_send_confirmation' => fn (Blueprint $table) => $table->boolean('lead_send_confirmation')->default(false),
            'lead_confirmation_subject' => fn (Blueprint $table) => $table->string('lead_confirmation_subject')->nullable(),
            'privacy_url' => fn (Blueprint $table) => $table->text('privacy_url')->nullable(),
            'lead_consent_required' => fn (Blueprint $table) => $table->boolean('lead_consent_required')->default(true),
            'meta_title' => fn (Blueprint $table) => $table->string('meta_title')->nullable(),
            'meta_description' => fn (Blueprint $table) => $table->text('meta_description')->nullable(),
        ];

        foreach ($columns as $name => $addColumn) {
            if (! Schema::hasColumn('digital_business_cards', $name)) {
                Schema::table('digital_business_cards', $addColumn);
            }
        }
    }

    public function down(): void
    {
        // Existing card data is intentionally preserved.
    }
};
