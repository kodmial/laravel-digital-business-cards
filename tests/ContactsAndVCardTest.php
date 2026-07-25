<?php

namespace DigitalCardKit\Laravel\Tests;

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
            ->assertSee('Выберите удобный способ получения визитки')
            ->assertSee('Отправить в Telegram')
            ->assertSee('Сохранить в контакты')
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
        $this->assertStringNotContainsString('<h2>Контакты</h2>', $html);
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
