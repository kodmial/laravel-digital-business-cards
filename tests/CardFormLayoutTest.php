<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\CreateDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\EditDigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Support\Config;
use DigitalCardKit\Laravel\Tests\Concerns\CreatesAdminRecords;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/**
 * The card create/edit UI is a single full-width form: the create page shows
 * every section stacked, and the edit page wraps the same sections in a split
 * layout beside the published-card preview. These tests pin that layout, the
 * full-width sections, the collapsed secondary groups, and the preview blade's
 * two render modes.
 */
class CardFormLayoutTest extends TestCase
{
    use CreatesAdminRecords;

    /** Build an unpersisted create-page instance for form introspection. */
    private function createPage(): CreateDigitalBusinessCard
    {
        return new CreateDigitalBusinessCard;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeCard(array $attributes = []): DigitalBusinessCard
    {
        $card = DigitalBusinessCard::factory()->create(array_merge([
            'slug' => 'preview-card',
            'is_published' => true,
            'first_name' => 'Preview',
            'last_name' => 'Card',
        ], $attributes));
        assert($card instanceof DigitalBusinessCard);

        return $card;
    }

    /** Build an edit-page instance with the given record set for form introspection. */
    private function editPage(DigitalBusinessCard $card): EditDigitalBusinessCard
    {
        $page = new EditDigitalBusinessCard;
        // $record is a public Livewire property on InteractsWithRecord; setting
        // it directly lets form() resolve the record without a full Livewire mount.
        $page->record = $card;

        return $page;
    }

    /** @return array<int, Component> */
    private function childComponents(Component $component): array
    {
        $schema = $component->getChildSchema();

        if ($schema !== null) {
            return $schema->getComponents();
        }

        return $component->getChildComponents();
    }

    /** @return array<int, Section> the top-level form sections. */
    private function createSections(): array
    {
        $components = DigitalBusinessCardResource::form(Schema::make($this->createPage()))->getComponents();

        return array_values(array_filter(
            $components,
            static fn (Component $component): bool => $component instanceof Section,
        ));
    }

    /** Every top-level form component is a full-width section. */
    public function test_the_resource_form_is_a_single_full_width_layout(): void
    {
        $components = DigitalBusinessCardResource::form(Schema::make($this->createPage()))->getComponents();

        $this->assertNotEmpty($components);
        foreach ($components as $component) {
            $this->assertInstanceOf(Section::class, $component);
            $this->assertSame(['default' => 'full'], $component->getColumnSpan());
        }
    }

    /** All eight expected sections are present and translate to real labels. */
    public function test_the_form_contains_the_expected_sections(): void
    {
        $labels = array_map(static fn (Section $section): string => (string) $section->getHeading(), $this->createSections());

        foreach ([
            'sections.address',
            'sections.hero',
            'sections.contacts',
            'sections.blocks',
            'sections.appearance',
            'sections.seo',
            'sections.lead_form',
            'sections.lead_fields',
        ] as $key) {
            $rawKey = 'digital-business-cards::admin.cards.'.$key;
            $label = DigitalBusinessCardResource::translate($key);

            // The section label must not fall back to the raw translation key —
            // that would mean the key was deleted/renamed and translate() silently
            // returned the key. The translation must actually resolve.
            $this->assertNotSame($key, $label, "Section label for [{$key}] resolved to its raw key.");
            $this->assertNotSame($rawKey, $label, "Section label for [{$key}] resolved to its raw namespaced key.");

            $this->assertContains($label, $labels);
        }
    }

    /** The key fields (slug, name, contacts, blocks, theme, lead) are reachable. */
    public function test_the_form_exposes_the_key_fields(): void
    {
        $queue = DigitalBusinessCardResource::form(Schema::make($this->createPage()))->getComponents();
        $found = [];

        while ($queue !== []) {
            $component = array_shift($queue);

            if (method_exists($component, 'getName')) {
                $name = $component->getName();
                foreach (['slug', 'first_name', 'contact_methods', 'blocks', 'theme_mode', 'lead_form_enabled'] as $needle) {
                    if ($name === $needle) {
                        $found[$needle] = true;
                    }
                }
            }

            foreach ($this->childComponents($component) as $child) {
                $queue[] = $child;
            }
        }

        foreach (['slug', 'first_name', 'contact_methods', 'blocks', 'theme_mode', 'lead_form_enabled'] as $needle) {
            $this->assertArrayHasKey($needle, $found, "Field [{$needle}] should be present in the card form.");
        }
    }

    /** Address/hero stay open; contacts, appearance, and lead groups collapse. */
    public function test_the_identity_sections_stay_open_while_secondary_groups_collapse(): void
    {
        $byLabel = [];
        foreach ($this->createSections() as $section) {
            $byLabel[(string) $section->getHeading()] = $section;
        }

        $open = DigitalBusinessCardResource::translate('sections.address');
        $hero = DigitalBusinessCardResource::translate('sections.hero');
        $contacts = DigitalBusinessCardResource::translate('sections.contacts');
        $appearance = DigitalBusinessCardResource::translate('sections.appearance');
        $leadForm = DigitalBusinessCardResource::translate('sections.lead_form');

        $this->assertFalse($byLabel[$open]->isCollapsible());
        $this->assertFalse($byLabel[$hero]->isCollapsible());
        $this->assertTrue($byLabel[$contacts]->isCollapsible());
        $this->assertTrue($byLabel[$appearance]->isCollapsible());
        $this->assertTrue($byLabel[$leadForm]->isCollapsible());
    }

    /** Create page form max width resolves to 7xl. */
    public function test_the_create_page_uses_the_wide_form_width(): void
    {
        $reflection = new \ReflectionMethod(CreateDigitalBusinessCard::class, 'getFormMaxWidth');

        $this->assertSame('7xl', $reflection->invoke($this->createPage()));
    }

    /** Edit page form max width resolves to 7xl. */
    public function test_the_edit_page_uses_the_wide_form_width(): void
    {
        $page = $this->editPage($this->makeCard(['slug' => 'edit-width']));
        $reflection = new \ReflectionMethod(EditDigitalBusinessCard::class, 'getFormMaxWidth');

        $this->assertSame('7xl', $reflection->invoke($page));
    }

    /** Edit header exposes preview, open (new tab), and delete actions. */
    public function test_the_edit_page_exposes_open_and_delete_header_actions(): void
    {
        $page = $this->editPage($this->makeCard(['slug' => 'edit-actions', 'is_published' => true]));
        $reflection = new \ReflectionMethod(EditDigitalBusinessCard::class, 'getHeaderActions');

        /** @var array<int, Action> $actions */
        $actions = $reflection->invoke($page);

        $this->assertCount(3, $actions);
        $this->assertSame(
            ['preview', 'open', 'delete'],
            array_map(static fn ($action): string => $action->getName(), $actions),
        );

        // The "open" header action points at the public card URL.
        $open = null;
        foreach ($actions as $action) {
            if ($action->getName() === 'open') {
                $open = $action;
            }
        }
        $this->assertNotNull($open);
        $this->assertTrue($open->shouldOpenUrlInNewTab());
    }

    /** Edit form mirrors create: one full-width column of sections. */
    public function test_the_edit_page_uses_the_same_full_width_form_as_create(): void
    {
        $card = $this->makeCard(['slug' => 'edit-card', 'is_published' => true]);
        $page = $this->editPage($card);
        $components = $page->form(Schema::make($page))->getComponents();

        // The edit form now mirrors the create form: one full-width column of
        // sections, with no side-by-side split layout.
        $this->assertNotEmpty($components);
        foreach ($components as $component) {
            $this->assertInstanceOf(Section::class, $component);
            $this->assertSame(['default' => 'full'], $component->getColumnSpan());
        }
    }

    /** Both create and edit pages expose the preview header action. */
    public function test_both_pages_expose_a_preview_header_action(): void
    {
        $edit = $this->editPage($this->makeCard(['slug' => 'preview-edit', 'is_published' => true]));
        $editActions = (new \ReflectionMethod(EditDigitalBusinessCard::class, 'getHeaderActions'))->invoke($edit);
        $this->assertContains('preview', array_map(static fn ($action): string => $action->getName(), $editActions));

        $createActions = (new \ReflectionMethod(CreateDigitalBusinessCard::class, 'getHeaderActions'))->invoke($this->createPage());
        $this->assertContains('preview', array_map(static fn ($action): string => $action->getName(), $createActions));
    }

    /** Preview blade renders locally (no iframe / no public route) with content. */
    public function test_the_card_preview_renders_locally_without_opening_the_public_route(): void
    {
        $card = $this->makeCard([
            'slug' => 'preview-local',
            'first_name' => 'Preview',
            'last_name' => 'Local',
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
            ],
        ]);
        $card->blocks()->create(['type' => 'text', 'title' => 'Bio', 'content' => 'Preview body', 'is_enabled' => true]);

        $html = view('digital-business-cards::filament.components.card-preview', [
            'card' => $card->load('blocks'),
        ])->render();

        $this->assertStringContainsString('digital-card-shell', $html);
        $this->assertStringContainsString('Preview Local', $html);
        $this->assertStringContainsString('tel:', $html);
        // The preview is a local render, never an iframe into the public route.
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('/card/', $html);
    }

    /** buildPreviewCard coerces empty upload arrays to null for preview. */
    public function test_preview_card_is_built_from_form_state_with_empty_media_arrays(): void
    {
        // Regression guard: Filament's file-upload fields leave empty arrays in
        // the live form state (avatar/logo/cover_image = []). Those media columns
        // are ?string, so an array passed into storageUrl() would throw a
        // TypeError when the preview modal opens. buildPreviewCard() must coerce
        // empty arrays to null.
        $page = $this->editPage($this->makeCard(['slug' => 'preview-empty-media', 'is_published' => true]));

        $method = (new \ReflectionClass(EditDigitalBusinessCard::class))
            ->getMethod('buildPreviewCard');
        $method->setAccessible(true);

        /** @var Model $preview */
        $preview = $method->invoke($page, [
            'first_name' => 'Test',
            'last_name' => 'User',
            'slug' => 'preview-empty-media',
            'is_published' => true,
            'avatar' => [],
            'logo' => [],
            'cover_image' => [],
            'blocks' => [['type' => 'text', 'title' => 'T', 'content' => 'C', 'is_enabled' => true]],
        ]);

        $this->assertInstanceOf(Config::cardModel(), $preview);
        $this->assertNull($preview->getAttribute('avatar'));
        $this->assertNull($preview->getAttribute('logo'));
        $this->assertNull($preview->getAttribute('cover_image'));
        $this->assertSame('Test', $preview->getAttribute('first_name'));
        $this->assertCount(1, $preview->getRelation('blocks'));
        $this->assertTrue($preview->getRelation('blocks')->first()->getAttribute('is_enabled'));
    }

    /** Live preview mount + render survives block media arrays (no TypeError). */
    public function test_preview_action_mounts_without_error_when_blocks_hold_media_arrays(): void
    {
        // Real path: mount the live EditDigitalBusinessCard Livewire component and
        // open the preview modal. Filament's file-upload fields leave empty arrays
        // in the block's data (data.media = [] / data.gallery = [[], ...]) instead
        // of strings, which used to crash storageUrl() when the modal rendered.
        $admin = $this->createAdminUser();
        $card = $this->makeCard(['slug' => 'preview-blocks', 'is_published' => true]);
        $card->blocks()->create([
            'type' => 'gallery',
            'title' => 'Shots',
            'data' => ['media' => [], 'gallery' => [[], []]],
            'is_enabled' => true,
        ]);
        $card->blocks()->create([
            'type' => 'file',
            'title' => 'Doc',
            'data' => ['media' => [[], []]],
            'is_enabled' => true,
        ]);

        // The live mount must not error when the form state carries those upload
        // arrays. (The modal shell opens here; its body is filled on a later
        // JS-driven request, so this alone does not exercise the blade.)
        Livewire::actingAs($admin)
            ->test(EditDigitalBusinessCard::class, ['record' => $card->getRouteKey()])
            // @phpstan-ignore-next-line
            ->mountAction('preview')
            ->assertOk();

        // Render guard: build the preview card from the same block data through
        // the real buildPreviewCard() path and render the card-preview fragment
        // directly. This is the point where storageUrl() meets the media arrays,
        // so it proves the coercion reaches the view without a fatal and that the
        // block headings are actually emitted into the rendered HTML.
        $page = $this->editPage($card);
        $builder = (new \ReflectionClass(EditDigitalBusinessCard::class))->getMethod('buildPreviewCard');
        $builder->setAccessible(true);

        /** @var Model $preview */
        $preview = $builder->invoke($page, [
            'first_name' => 'Preview',
            'last_name' => 'Card',
            'slug' => 'preview-blocks',
            'is_published' => true,
            'blocks' => [
                ['type' => 'gallery', 'title' => 'Shots', 'data' => ['media' => [], 'gallery' => [[], []]], 'is_enabled' => true],
                ['type' => 'file', 'title' => 'Doc', 'data' => ['media' => [[], []]], 'is_enabled' => true],
            ],
        ]);

        $html = view('digital-business-cards::filament.components.card-preview', ['card' => $preview])->render();

        $this->assertStringContainsString('digital-card-shell', $html);
        $this->assertStringContainsString('Shots', $html);
        $this->assertStringContainsString('Doc', $html);
        $this->assertStringNotContainsString('TypeError', $html);
    }

    /** Public card page still renders unchanged by the admin form reshaping. */
    public function test_the_public_card_route_still_renders_unchanged_after_the_admin_form_changes(): void
    {
        // The admin form only reshapes the Filament form; the public card reads
        // the same model columns, so this guards against accidental coupling
        // between the two rendering surfaces. A card with contacts and content
        // blocks exercises the public surfaces the admin form edits.
        $card = $this->makeCard([
            'slug' => 'example-card',
            'first_name' => 'Alex',
            'middle_name' => 'Taylor',
            'last_name' => 'Morgan',
            'job_title' => 'Product designer',
            'company_name' => 'Example Studio',
            'headline' => 'Building useful digital products',
            'about' => 'Independent product design practice.',
            'contact_methods' => [
                ['type' => 'phone', 'label' => 'Phone', 'value' => '+1 202 555 0123'],
                ['type' => 'email', 'label' => 'Email', 'value' => 'alex@example.test'],
                ['type' => 'telegram', 'label' => 'Telegram', 'value' => '@alex_example'],
            ],
        ]);
        $card->blocks()->create(['type' => 'text', 'title' => 'Biography', 'content' => 'Full profile story', 'is_enabled' => true]);
        $card->blocks()->create(['type' => 'link', 'title' => 'Portfolio', 'url' => 'https://example.test/portfolio', 'button_label' => 'Open portfolio', 'is_enabled' => true]);
        $card->blocks()->create(['type' => 'text', 'title' => 'Hidden', 'content' => 'Must not appear', 'is_enabled' => false]);

        $this->get('/card/example-card')
            ->assertOk()
            ->assertSee('Alex Taylor Morgan')
            ->assertSee('Save to contacts')
            ->assertSee('Product designer')
            ->assertSee('Example Studio')
            ->assertSee('Biography')
            ->assertSee('Full profile story')
            ->assertSee('Open portfolio')
            ->assertDontSee('Must not appear');
    }

    /** handleRecordUpdate applies the edited fields and recomputes full name. */
    public function test_editing_updates_fields_on_save(): void
    {
        $card = $this->makeCard(['slug' => 'edit-target']);

        $page = $this->editPage($card);

        // Simulate a save by calling the same handler Filament uses.
        $method = (new \ReflectionClass(EditDigitalBusinessCard::class))
            ->getMethod('handleRecordUpdate');
        $method->setAccessible(true);

        $updated = $method->invoke($page, $card, [
            'slug' => 'edit-target',
            'is_published' => true,
            'first_name' => 'Edit',
            'last_name' => 'Target',
        ]);

        $this->assertSame('Edit Target', $updated->getAttributeValue('full_name'));
    }

    /**
     * Typing the name auto-fills the slug (until the editor claims it), so the
     * afterStateUpdated callbacks must be able to resolve Str::slug. This pins
     * that the required import is present and that an explicit slug wins.
     */
    public function test_auto_slug_is_generated_from_name_and_not_overwritten_when_set(): void
    {
        $admin = $this->createAdminUser();

        // Scenario A: typing the first name auto-fills the slug. The blank-slug
        // guard means the first field typed wins, so the realistic auto-fill is
        // 'john' — the key point is that Str::slug resolves without a fatal.
        Livewire::actingAs($admin)
            ->test(CreateDigitalBusinessCard::class)
            ->set('data.first_name', 'John')
            ->assertSet('data.slug', 'john');

        // Scenario A (combine): when the slug is still blank and both names are
        // present, the callback combines them into 'john-doe'.
        Livewire::actingAs($admin)
            ->test(CreateDigitalBusinessCard::class)
            ->set('data.last_name', 'Doe')
            ->set('data.slug', '')
            ->set('data.first_name', 'John')
            ->assertSet('data.slug', 'john-doe');

        // Scenario B: an explicitly set slug is preserved when the name changes.
        Livewire::actingAs($admin)
            ->test(CreateDigitalBusinessCard::class)
            ->set('data.slug', 'custom')
            ->set('data.first_name', 'John')
            ->assertSet('data.slug', 'custom');
    }
}
