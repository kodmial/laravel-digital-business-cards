<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Tests\Concerns\CreatesAdminRecords;

class LeadExportTest extends TestCase
{
    use CreatesAdminRecords;

    public function test_export_downloads_utf8_csv_with_header_standard_values_and_consent(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card);

        $response = $this->get('/admin/digital-business-card-leads-export')
            ->assertOk()
            ->assertDownload();
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Визитка;Имя;Телефон;Email;Компания;Комментарий', $content);
        $this->assertStringContainsString('Taylor Smith', $content);
        $this->assertStringContainsString('Да', $content);
    }

    public function test_export_neutralizes_formulas_and_serializes_nested_custom_data(): void
    {
        $card = $this->createAdminCard();
        $this->createAdminLead($card, [
            'name' => '=HYPERLINK("https://example.test")',
            'company' => '+SUM(1,1)',
            'custom_data' => ['nested' => ['safe' => true]],
        ]);

        $content = $this->get('/admin/digital-business-card-leads-export')->streamedContent();

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

        $content = $this->get('/admin/digital-business-card-leads-export?card_id='.$first->getKey())->streamedContent();
        $this->assertStringContainsString('First lead', $content);
        $this->assertStringNotContainsString('Second lead', $content);

        $empty = $this->get('/admin/digital-business-card-leads-export?card_id=99999')->streamedContent();
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

        $content = $this->get(
            '/admin/digital-business-card-leads-export?date_from='
            .now()->subDay()->toDateString()
            .'&date_to='
            .now()->toDateString()
        )->streamedContent();

        $this->assertStringContainsString('Lead in range', $content);
        $this->assertStringContainsString('telegram: @example', $content);
        $this->assertStringNotContainsString('Earlier lead', $content);
    }
}
