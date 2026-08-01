<?php

namespace DigitalCardKit\Laravel\Tests;

use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\DigitalBusinessCardResource;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\CreateDigitalBusinessCard;
use DigitalCardKit\Laravel\Filament\Resources\DigitalBusinessCards\Pages\EditDigitalBusinessCard;
use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use DigitalCardKit\Laravel\Tests\Fixtures\User;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Livewire\Livewire;

/**
 * Option C: the create page becomes a guided five-step wizard, and the edit
 * page wraps the same tabbed form in a split layout with a live preview of
 * the published card. These tests pin both the wizard's step structure and
 * the split layout's composition, plus the preview blade's two render modes.
 */
class WizardAndSplitLayoutTest extends TestCase
{
    private function createPage(): CreateDigitalBusinessCard
    {
        return new CreateDigitalBusinessCard;
    }

    /**
     * Create a card through the shipped factory so the test honours the
     * package's config-based model indirection (the same PackageTest expects of
     * every model instance). Defaults keep the preview published unless a test
     * overrides it.
     *
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

    private function editPage(DigitalBusinessCard $card): EditDigitalBusinessCard
    {
        $page = new EditDigitalBusinessCard;
        // $record is a public Livewire property on InteractsWithRecord; setting
        // it directly lets form() resolve the record without a full Livewire mount.
        $page->record = $card;

        return $page;
    }

    /** Breadth-first search of a component tree for a field by its name. */
    private function findComponent(array $components, string $name): ?Component
    {
        $queue = $components;

        while ($queue !== []) {
            $component = array_shift($queue);

            if (method_exists($component, 'getName') && $component->getName() === $name) {
                return $component;
            }

            foreach ($this->childComponents($component) as $child) {
                $queue[] = $child;
            }
        }

        return null;
    }

    /** @return array<int, Component> */
    private function childComponents(Component $component): array
    {
        $schema = $component->getChildSchema();

        return $schema !== null ? $schema->getComponents() : $component->getChildComponents();
    }

    /** Build the create page's form once, returning the assembled Wizard with a container attached. */
    private function builtCreateWizard(CreateDigitalBusinessCard $page): Wizard
    {
        $components = $page->form(Schema::make($page))->getComponents();
        $this->assertCount(1, $components);

        $wizard = $components[0];
        assert($wizard instanceof Wizard);

        return $wizard;
    }

    /** @return array<int, Step> the wizard's steps, with their schemas attached to a container. */
    private function wizardSteps(CreateDigitalBusinessCard $page): array
    {
        $steps = $this->builtCreateWizard($page)->getChildSchema()->getComponents();

        return array_values(array_filter(
            $steps,
            static fn (Component $step): bool => $step instanceof Step,
        ));
    }

    public function test_the_resource_form_uses_the_shared_card_tabs(): void
    {
        $components = DigitalBusinessCardResource::form(Schema::make($this->createPage()))->getComponents();

        $this->assertCount(1, $components);
        $this->assertInstanceOf(Tabs::class, $components[0]);
        $this->assertSame(5, count($components[0]->getChildComponents()));
    }

    public function test_the_create_page_renders_a_wizard_with_the_five_tab_steps(): void
    {
        $steps = $this->wizardSteps($this->createPage());

        $this->assertCount(5, $steps);
        $this->assertSame(
            ['Profile', 'Contacts', 'Content blocks', 'Design and SEO', 'Contact collection'],
            array_map(static fn (Step $step): string => $step->getLabel(), $steps),
        );

        foreach ($steps as $step) {
            $this->assertNotEmpty($step->getIcon(), 'Every wizard step carries an icon.');
        }
    }

    public function test_wizard_steps_carry_their_tab_fields(): void
    {
        $steps = $this->wizardSteps($this->createPage());
        $this->assertCount(5, $steps);

        $stepSchema = static function (Step $step): array {
            $schema = $step->getChildSchema();

            return $schema !== null ? $schema->getComponents() : [];
        };

        // Profile step: address and hero identity fields.
        $this->assertNotNull($this->findComponent($stepSchema($steps[0]), 'slug'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[0]), 'first_name'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[0]), 'is_published'));

        // Contacts step: the contact-methods repeater.
        $this->assertNotNull($this->findComponent($stepSchema($steps[1]), 'contact_methods'));

        // Blocks step: the relationship-backed blocks repeater.
        $this->assertNotNull($this->findComponent($stepSchema($steps[2]), 'blocks'));

        // Design step: theme, button style and SEO. The colour pickers are
        // conditionally visible (theme_mode = custom), so they are absent
        // from the rendered tree when the default theme is selected — only the
        // always-present fields are asserted here.
        $this->assertNotNull($this->findComponent($stepSchema($steps[3]), 'theme_mode'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[3]), 'button_style'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[3]), 'font_family'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[3]), 'meta_title'));

        // Leads step: the lead form toggles and notification addresses.
        $this->assertNotNull($this->findComponent($stepSchema($steps[4]), 'lead_form_enabled'));
        $this->assertNotNull($this->findComponent($stepSchema($steps[4]), 'lead_notification_emails'));
    }

    public function test_the_create_page_accepts_skippable_steps_so_optional_content_is_not_blocking(): void
    {
        $reflection = new \ReflectionMethod(CreateDigitalBusinessCard::class, 'hasSkippableSteps');

        $this->assertTrue($reflection->invoke(new CreateDigitalBusinessCard));
    }

    public function test_the_create_page_uses_the_wide_form_width(): void
    {
        $reflection = new \ReflectionMethod(CreateDigitalBusinessCard::class, 'getFormMaxWidth');

        $this->assertSame('7xl', $reflection->invoke($this->createPage()));
    }

    public function test_the_edit_page_uses_the_wide_form_width(): void
    {
        $page = $this->editPage($this->makeCard(['slug' => 'edit-width']));
        $reflection = new \ReflectionMethod(EditDigitalBusinessCard::class, 'getFormMaxWidth');

        $this->assertSame('7xl', $reflection->invoke($page));
    }

    public function test_the_edit_page_exposes_open_and_delete_header_actions(): void
    {
        $page = $this->editPage($this->makeCard(['slug' => 'edit-actions', 'is_published' => true]));
        $reflection = new \ReflectionMethod(EditDigitalBusinessCard::class, 'getHeaderActions');

        /** @var array<int, Action> $actions */
        $actions = $reflection->invoke($page);

        $this->assertCount(2, $actions);
        $this->assertSame(
            ['open', 'delete'],
            array_map(static fn ($action): string => $action->getName(), $actions),
        );

        // The "open" header action points at the public card URL.
        $open = $actions[0];
        $this->assertTrue($open->shouldOpenUrlInNewTab());
    }

    public function test_the_edit_page_wraps_the_form_in_a_split_layout_with_a_live_preview(): void
    {
        $card = $this->makeCard(['slug' => 'edit-card', 'is_published' => true]);
        $page = $this->editPage($card);
        $schema = $page->form(Schema::make($page));
        $components = $schema->getComponents();

        // The top-level schema is a single Grid (the split layout container).
        $this->assertCount(1, $components);
        $grid = $components[0];
        $this->assertTrue(method_exists($grid, 'getChildSchema'), 'The split layout is a grid container.');

        $split = $grid->getChildSchema()->getComponents();

        // The form column: the shared tabbed card form.
        $this->assertInstanceOf(Tabs::class, $split[0]);
        $this->assertSame(5, count($split[0]->getChildComponents()));

        // The preview column: the live-preview view, fed the card record.
        $this->assertCount(2, $split);
        $preview = $split[1];
        $this->assertSame(
            'digital-business-cards::filament.components.live-preview',
            $preview->getView(),
        );

        $data = $preview->evaluate($preview->getViewData());
        $this->assertSame($card->getKey(), $data['card']->getKey());
    }

    public function test_the_live_preview_renders_an_iframe_of_the_public_card_when_published(): void
    {
        $this->makeCard(['slug' => 'preview-published', 'is_published' => true]);

        $html = view('digital-business-cards::filament.components.live-preview', [
            'card' => DigitalBusinessCard::query()->where('slug', 'preview-published')->firstOrFail(),
        ])->render();

        // The iframe points at the public card route, which embeds the slug.
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString('preview-published', $html);
        $this->assertStringContainsString('src=', $html);
        $this->assertStringNotContainsString('Publish the card', $html);
        // The sandbox isolates our own card while letting its scripts/forms run.
        $this->assertStringContainsString('allow-same-origin', $html);
        $this->assertStringContainsString('allow-forms', $html);
    }

    public function test_the_live_preview_shows_an_unpublished_notice_instead_of_an_iframe(): void
    {
        $this->makeCard(['slug' => 'preview-draft', 'is_published' => false]);

        $html = view('digital-business-cards::filament.components.live-preview', [
            'card' => DigitalBusinessCard::query()->where('slug', 'preview-draft')->firstOrFail(),
        ])->render();

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('Publish the card to see its published version', $html);
    }

    public function test_the_live_preview_follows_the_application_locale(): void
    {
        $this->makeCard(['slug' => 'preview-locale', 'is_published' => false]);

        $this->app->setLocale('ru');

        $html = view('digital-business-cards::filament.components.live-preview', [
            'card' => DigitalBusinessCard::query()->where('slug', 'preview-locale')->firstOrFail(),
        ])->render();

        $this->assertStringContainsString('Опубликованная версия', $html);
        $this->assertStringContainsString('Опубликуйте визитку, чтобы увидеть её опубликованную версию', $html);
        $this->assertStringNotContainsString('digital-business-cards::admin', $html);
    }

    public function test_the_public_card_route_still_renders_unchanged_after_the_admin_form_changes(): void
    {
        // The admin wizard/split layout only reshapes the Filament form; the
        // public card reads the same model columns, so this guards against
        // accidental coupling between the two rendering surfaces. A card with
        // contacts and content blocks exercises the public surfaces the admin
        // form edits.
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


    public function test_the_create_page_uses_a_wizard_with_the_expected_steps(): void
    {
        $steps = $this->wizardSteps($this->createPage());

        $this->assertCount(5, $steps);
        $this->assertSame(
            ['Profile', 'Contacts', 'Content blocks', 'Design and SEO', 'Contact collection'],
            array_map(static fn (Step $step): string => $step->getLabel(), $steps),
        );

        foreach ($steps as $step) {
            $this->assertNotEmpty($step->getIcon(), 'Every wizard step carries an icon.');
        }
    }

    public function test_the_edit_page_exposes_a_split_layout_with_a_live_preview_component(): void
    {
        $card = $this->makeCard(['slug' => 'edit-split', 'is_published' => true]);

        $page = $this->editPage($card);
        $schema = $page->form(Schema::make($page));
        $components = $schema->getComponents();

        $this->assertCount(1, $components);
        $grid = $components[0];
        $this->assertTrue(method_exists($grid, 'getChildSchema'));

        $split = $grid->getChildSchema()->getComponents();

        $this->assertInstanceOf(Tabs::class, $split[0]);
        $this->assertSame(5, count($split[0]->getChildComponents()));

        $this->assertCount(2, $split);
        $preview = $split[1];
        $this->assertSame(
            'digital-business-cards::filament.components.live-preview',
            $preview->getView(),
        );

        $data = $preview->evaluate($preview->getViewData());
        $this->assertSame($card->getKey(), $data['card']->getKey());
        $this->assertSame(0, $data['previewVersion']);
    }

    public function test_editing_updates_fields_on_save_and_bumps_the_preview_version(): void
    {
        $card = $this->makeCard(['slug' => 'edit-target']);

        $page = $this->editPage($card);
        $this->assertSame(0, $page->previewVersion);

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
        $this->assertSame(1, $page->previewVersion);
    }
}
