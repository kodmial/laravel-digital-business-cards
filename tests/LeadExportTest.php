<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesAdminRecords;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeadExportTest extends TestCase
{
    use CreatesAdminRecords;

    private const PATH = '/admin/digital-business-card-leads-export';

    public function test_export_downloads_utf8_csv_with_header_standard_values_and_consent(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card);

        $response = $this->actingAs($this->createAdminUser())
            ->get(self::PATH)
            ->assertOk()
            ->assertDownload();
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Card;Name;Phone;Email;Company;Comment', $content);
        $this->assertStringContainsString('Taylor Smith', $content);
        $this->assertStringContainsString('Yes', $content);
    }

    public function test_export_header_follows_the_application_locale(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card);
        $this->app->setLocale('ru');

        $content = $this->actingAs($this->createAdminUser())->get(self::PATH)->streamedContent();

        $this->assertStringContainsString('Визитка;Имя;Телефон;Email;Компания;Комментарий', $content);
        $this->assertStringContainsString('Да', $content);
    }

    public function test_export_is_denied_to_guests_even_without_route_middleware(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card);

        $this->assertSame([], config('digital-business-cards.lead_export.middleware'));
        $this->get(self::PATH)->assertForbidden();
    }

    public function test_a_host_defined_ability_replaces_the_packaged_default(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card);
        Gate::define('digital-business-cards.export-leads', static fn ($user = null): bool => false);

        $this->actingAs($this->createAdminUser())->get(self::PATH)->assertForbidden();
    }

    public function test_a_panel_on_its_own_auth_guard_is_still_allowed_to_export(): void
    {
        config(['auth.guards.panel' => ['driver' => 'session', 'provider' => 'users']]);
        Filament::getPanel('admin')->authGuard('panel');
        $card = $this->createAdminCard();
        $this->createAdminLead($card);

        // Deliberately not actingAs(): that calls Auth::shouldUse() and makes
        // the guard the default one, which is exactly what a real request on a
        // custom panel guard does not do. Gate would otherwise see no user.
        Auth::guard('panel')->setUser($this->createAdminUser());

        $this->assertNull(Auth::user());
        $this->get(self::PATH)->assertOk();
    }

    public function test_export_rejects_malformed_filters(): void
    {
        $this->createAdminLead($this->createAdminCard());
        $user = $this->createAdminUser();

        $this->actingAs($user)->get(self::PATH.'?card_id=not-a-number')->assertSessionHasErrors('card_id');
        $this->actingAs($user)->get(self::PATH.'?date_from=not-a-date')->assertSessionHasErrors('date_from');
        $this->actingAs($user)
            ->get(self::PATH.'?date_from=2026-07-20&date_to=2026-07-10')
            ->assertSessionHasErrors('date_to');
    }

    public function test_export_neutralizes_formulas_and_serializes_nested_custom_data(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card, [
            'name' => '=HYPERLINK("https://example.test")',
            'company' => '+SUM(1,1)',
            'custom_data' => ['nested' => ['safe' => true]],
        ]);

        $content = $this->actingAs($this->createAdminUser())->get(self::PATH)->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+SUM", $content);
        $this->assertStringContainsString('nested: {', $content);
    }

    public function test_export_filters_by_card_and_returns_only_a_header_when_nothing_matches(): void
    {
        $first = $this->createAdminCard(['slug' => 'first-card']);
        $second = $this->createAdminCard(['slug' => 'second-card']);
        $this->createAdminLead($first, ['name' => 'First lead']);
        $this->createAdminLead($second, ['name' => 'Second lead']);
        $user = $this->createAdminUser();

        $content = $this->actingAs($user)->get(self::PATH.'?card_id='.$first->getKey())->streamedContent();
        $this->assertStringContainsString('First lead', $content);
        $this->assertStringNotContainsString('Second lead', $content);

        $empty = $this->actingAs($user)->get(self::PATH.'?card_id=99999')->streamedContent();
        $lines = array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $empty)));
        $this->assertCount(1, $lines);
    }

    public function test_export_filters_an_inclusive_date_range_and_includes_custom_data(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card, [
            'name' => 'Earlier lead',
            'submitted_at' => now()->subDays(2),
            'consent_given' => false,
        ]);
        $this->createAdminLead($card, [
            'name' => 'Lead in range',
            'custom_data' => ['telegram' => '@example'],
            'submitted_at' => now()->subDay(),
        ]);

        $content = $this->actingAs($this->createAdminUser())->get(
            self::PATH.'?date_from='
            .now()->subDay()->toDateString()
            .'&date_to='
            .now()->toDateString()
        )->streamedContent();

        $this->assertStringContainsString('Lead in range', $content);
        $this->assertStringContainsString('telegram: @example', $content);
        $this->assertStringNotContainsString('Earlier lead', $content);
    }

    public function test_a_lead_submitted_late_on_the_closing_date_is_still_exported(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card, [
            'name' => 'Late lead',
            'submitted_at' => now()->setTime(23, 45),
        ]);

        $content = $this->actingAs($this->createAdminUser())->get(
            self::PATH.'?date_to='.now()->toDateString()
        )->streamedContent();

        $this->assertStringContainsString('Late lead', $content);
    }
}
