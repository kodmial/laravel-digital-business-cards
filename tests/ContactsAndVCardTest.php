<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Support\ContactChannelRegistry;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesCards;
use Sabre\VObject\Reader;

class ContactsAndVCardTest extends TestCase
{
    use CreatesCards;

    public function test_all_contact_channels_render_with_normalized_links_and_brand_assets(): void
    {
        $card = $this->createCard(['contact_methods' => [
            ['type' => 'phone', 'label' => 'Phone', 'value' => '+7 999 123 45 67'],
            ['type' => 'phone', 'label' => 'International', 'value' => '+44 20 7946 0958'],
            ['type' => 'email', 'label' => 'Email', 'value' => 'alex@example.test'],
            ['type' => 'telegram', 'label' => 'Telegram', 'value' => '@alex_example'],
            ['type' => 'max', 'label' => 'MAX', 'value' => 'https://max.ru/alex'],
            ['type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => '+12025550123'],
            ['type' => 'website', 'label' => 'Website', 'value' => 'example.test'],
            ['type' => 'address', 'label' => 'Office', 'value' => 'Example Street 1'],
            ['type' => 'link', 'label' => 'Other', 'value' => 'https://link.example.test'],
        ]]);

        $this->assertSame('https://t.me/alex_example', $card->fresh()->contact_methods[3]['value']);
        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('+7 (999) 123-45-67')
            ->assertSee('+44 20 7946 0958')
            ->assertSee('href="https://example.test"', false)
            ->assertSee('<strong>example.test</strong>', false)
            ->assertSee('aria-label="Website: https://example.test"', false)
            ->assertSee('https://www.google.com/maps/search/', false)
            ->assertSee('data-brand-icon="telegram"', false)
            ->assertSee('fill="#2AABEE"', false)
            ->assertSee('data-brand-icon="max"', false)
            ->assertSee('id="max-brand-gradient"', false)
            ->assertSee('data-modal="save"', false)
            ->assertSee('data-modal="exchange"', false)
            ->assertSee('Choose how you would like to receive the card')
            ->assertSee('Send via Telegram')
            ->assertSee('Save to contacts')
            ->assertDontSee('>https://example.test<', false);
    }

    public function test_contacts_keep_admin_order_and_only_adjacent_social_contacts_share_rows(): void
    {
        $this->createCard(['contact_methods' => [
            ['type' => 'phone', 'label' => 'First', 'value' => '+12025550100'],
            ['type' => 'telegram', 'label' => 'Second', 'value' => '@second'],
            ['type' => 'email', 'label' => 'Third', 'value' => 'third@example.test'],
            ['type' => 'max', 'label' => 'Fourth', 'value' => 'https://max.ru/fourth'],
            ['type' => 'whatsapp', 'label' => 'Fifth', 'value' => '+12025550101'],
        ]]);

        $html = $this->get('/card/example-card')->assertOk()->getContent();
        $this->assertTrue(
            strpos($html, 'First') < strpos($html, 'Second')
            && strpos($html, 'Second') < strpos($html, 'Third')
            && strpos($html, 'Third') < strpos($html, 'Fourth')
            && strpos($html, 'Fourth') < strpos($html, 'Fifth'),
        );
        $this->assertSame(1, substr_count($html, 'digital-card-social-row--single'));
        $this->assertSame(1, substr_count($html, 'digital-card-social-row--multiple'));
        $this->assertStringContainsString('--social-count: 2', $html);
        $this->assertStringNotContainsString('<h2>Contacts</h2>', $html);
    }

    public function test_dangerous_contact_schemes_never_reach_a_rendered_link(): void
    {
        $this->createCard(['contact_methods' => [
            ['type' => 'unknown', 'label' => 'Script', 'value' => 'javascript:alert(1)'],
            ['type' => 'link', 'label' => 'Data', 'value' => 'data:text/html,<script>alert(1)</script>'],
            ['type' => 'unknown', 'label' => 'Vbs', 'value' => 'VBScript:msgbox(1)'],
            ['type' => 'phone', 'label' => 'Phone', 'value' => '+12025550123'],
        ]]);

        $html = $this->get('/card/example-card')->assertOk()->getContent();

        $this->assertStringNotContainsString('javascript:', strtolower($html));
        $this->assertStringNotContainsString('vbscript:', strtolower($html));
        $this->assertStringNotContainsString('data:text/html', strtolower($html));
        $this->assertStringNotContainsString('Script', $html);
        $this->assertStringContainsString('href="tel:+12025550123"', $html);
    }

    public function test_href_only_emits_http_tel_and_mailto_schemes(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,x', 'vbscript:x', 'file:///etc/passwd'] as $value) {
            $this->assertSame('', ContactChannelRegistry::href(['type' => 'unknown', 'value' => $value]));
            $this->assertSame('', ContactChannelRegistry::href(['type' => 'link', 'value' => $value]));
        }

        $this->assertSame('', ContactChannelRegistry::href(['type' => 'phone', 'value' => '   ']));
        $this->assertSame('tel:+12025550123', ContactChannelRegistry::href(['type' => 'phone', 'value' => '+1 202 555 0123']));
        $this->assertSame('mailto:alex@example.test', ContactChannelRegistry::href(['type' => 'email', 'value' => 'alex@example.test']));
        $this->assertSame('https://example.test/path', ContactChannelRegistry::href(['type' => 'website', 'value' => 'example.test/path']));
        $this->assertSame('https://wa.me/12025550123', ContactChannelRegistry::href(['type' => 'whatsapp', 'value' => '+1 (202) 555-0123']));
    }

    public function test_short_service_numbers_still_produce_a_tel_link(): void
    {
        // parse_url() reads "tel:112" as host:port and reports no scheme, so
        // the allowlist must not be built on it.
        foreach (['112', '911', '8-800', '65536'] as $value) {
            $this->assertStringStartsWith(
                'tel:',
                ContactChannelRegistry::href(['type' => 'phone', 'value' => $value]),
                "A phone contact of [{$value}] should still be dialable.",
            );
        }

        $this->createCard(['contact_methods' => [
            ['type' => 'phone', 'label' => 'Emergency', 'value' => '112'],
        ]]);

        $this->get('/card/example-card')->assertOk()->assertSee('href="tel:112"', false);
    }

    public function test_group_merges_only_consecutive_messengers(): void
    {
        $groups = ContactChannelRegistry::group([
            ['type' => 'phone', 'value' => '+12025550100'],
            ['type' => 'telegram', 'value' => 'https://t.me/a'],
            ['type' => 'max', 'value' => 'https://max.ru/b'],
            ['type' => 'email', 'value' => 'c@example.test'],
            ['type' => 'whatsapp', 'value' => '+12025550101'],
        ]);

        $this->assertSame(['contact', 'social', 'contact', 'social'], array_column($groups, 'type'));
        $this->assertCount(2, $groups[1]['items']);
        $this->assertCount(1, $groups[3]['items']);
        $this->assertSame([], ContactChannelRegistry::group([]));
    }

    public function test_vcard_is_valid_and_contains_identity_contacts_url_and_address(): void
    {
        $this->createCard();
        $response = $this->get('/card/example-card/contact.vcf')
            ->assertOk()
            ->assertHeader('content-type', 'text/vcard; charset=utf-8')
            ->assertDownload('alex-taylor-morgan.vcf');

        $vcard = Reader::read($response->getContent());
        $this->assertSame('Alex Taylor Morgan', (string) $vcard->FN);
        $this->assertSame('Morgan', $vcard->N->getParts()[0]);
        $this->assertSame('Alex', $vcard->N->getParts()[1]);
        $this->assertSame('Taylor', $vcard->N->getParts()[2]);
        $this->assertSame('+12025550123', (string) $vcard->TEL);
        $this->assertSame('alex@example.test', (string) $vcard->EMAIL);
        $this->assertSame('Example Studio', (string) $vcard->ORG);
        $this->assertSame('Product designer', (string) $vcard->TITLE);
        $this->assertStringContainsString('https://example.test', $vcard->serialize());
        $this->assertStringContainsString('Example Street 1', $vcard->serialize());
    }

    public function test_vcard_visibility_empty_contacts_and_filename_fallbacks_are_safe(): void
    {
        $this->createCard(['slug' => 'private', 'is_published' => false]);
        $this->get('/card/private/contact.vcf')->assertNotFound();

        $this->createCard([
            'slug' => 'anonymous',
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'contact_methods' => [],
        ]);
        $response = $this->get('/card/anonymous/contact.vcf')->assertOk()->assertDownload('contact.vcf');
        Reader::read($response->getContent());
    }
}
