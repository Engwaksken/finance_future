<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PageSlide;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitePresentationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function slideData(array $overrides = []): array
    {
        return array_replace([
            'page' => 'home', 'title' => 'Home highlight', 'background_type' => 'color',
            'background_color' => '#123456', 'text_color' => '#ffffff', 'sort_order' => 0, 'is_active' => 1,
        ], $overrides);
    }

    public function test_settings_tabs_save_independently_and_preserve_login_credentials(): void
    {
        $admin = $this->admin();
        $password = $admin->password;
        SiteSetting::put('backup_enabled', true, 'backup');
        SiteSetting::put('maintenance_enabled', true, 'maintenance');
        $this->actingAs($admin);

        foreach (['general', 'branding', 'backup', 'maintenance', 'slides'] as $tab) {
            $this->get(route('admin.settings.index', ['tab' => $tab]))->assertOk()->assertSee('Page Slides');
        }
        $this->put(route('admin.settings.update'), [
            'section' => 'general', 'site_name' => 'My Finance Site', 'site_tagline' => 'New tagline',
        ])->assertRedirect(route('admin.settings.index', ['tab' => 'general']));

        $this->assertSame('1', SiteSetting::valueOf('backup_enabled'));
        $this->assertSame('1', SiteSetting::valueOf('maintenance_enabled'));
        $this->assertSame($password, $admin->fresh()->password);
        $this->put(route('admin.settings.update'), ['section' => 'maintenance', 'maintenance_enabled' => 0])->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('My Finance Site')->assertSee('New tagline');
        $this->assertSame('1', SiteSetting::valueOf('backup_enabled'));
    }

    public function test_branding_uploads_render_and_replaced_or_removed_files_are_deleted(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->put(route('admin.settings.update'), [
            'section' => 'branding', 'site_logo' => UploadedFile::fake()->image('logo.png'),
            'site_favicon' => UploadedFile::fake()->image('favicon.png'),
        ])->assertSessionHasNoErrors();
        $oldLogo = SiteSetting::valueOf('site_logo');
        $favicon = SiteSetting::valueOf('site_favicon');
        Storage::disk('public')->assertExists($oldLogo);
        $this->get('/')->assertSee('storage/'.$oldLogo)->assertSee('storage/'.$favicon);
        $this->put(route('admin.settings.update'), [
            'section' => 'branding', 'site_logo' => UploadedFile::fake()->image('new-logo.png'), 'remove_site_favicon' => 1,
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($oldLogo);
        Storage::disk('public')->assertMissing($favicon);
        Storage::disk('public')->assertExists(SiteSetting::valueOf('site_logo'));
    }

    public function test_category_visuals_render_on_home_and_topic_pages_and_image_replacement_is_safe(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->post(route('admin.categories.store'), [
            'name' => 'Visual Topic', 'display_type' => 'image', 'is_active' => 1,
            'featured_image' => UploadedFile::fake()->image('topic.png'),
        ])->assertSessionHasNoErrors();
        $category = Category::where('slug', 'visual-topic')->firstOrFail();
        $path = $category->featured_image;
        Storage::disk('public')->assertExists($path);
        $this->get('/')->assertSee('storage/'.$path);
        $this->get(route('categories.show', $category->slug))->assertSee('storage/'.$path);
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Frontend Display');

        $this->put(route('admin.categories.update', $category), [
            'name' => 'Visual Topic', 'display_type' => 'image', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($path, $category->fresh()->featured_image);
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Visual Topic', 'display_type' => 'image', 'is_active' => 1,
            'featured_image' => UploadedFile::fake()->image('replacement.png'),
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $newPath = $category->fresh()->featured_image;
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Visual Topic', 'display_type' => 'icon', 'icon' => 'fas fa-coins', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('fas fa-coins');
        $this->get(route('categories.show', $category->slug))->assertSee('fas fa-coins');
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Visual Topic', 'display_type' => 'number', 'display_number' => 42, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('category-number')->assertSee('42');
        $this->delete(route('admin.categories.destroy', $category))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($newPath);
    }

    public function test_invalid_category_icons_and_missing_images_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.categories.store'), ['name' => 'Missing', 'display_type' => 'image'])
            ->assertSessionHasErrors('featured_image');
        $this->post(route('admin.categories.store'), ['name' => 'Unsafe', 'display_type' => 'icon', 'icon' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('icon');
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_slides_are_scoped_to_pages_and_categories_and_ordered_without_hidden_content(): void
    {
        $this->actingAs($this->admin());
        $category = Category::create(['name' => 'Finance', 'slug' => 'finance', 'is_active' => true]);
        $other = Category::create(['name' => 'Technology', 'slug' => 'technology', 'is_active' => true]);
        foreach ([
            $this->slideData(['title' => 'Second highlight', 'sort_order' => 2]),
            $this->slideData(['title' => 'First highlight', 'sort_order' => 1]),
            $this->slideData(['title' => 'Hidden highlight', 'is_active' => 0]),
            $this->slideData(['page' => 'about', 'title' => 'About highlight']),
            $this->slideData(['page' => 'categories.show', 'title' => 'Every category']),
            $this->slideData(['page' => 'categories.show', 'category_id' => $category->id, 'title' => 'Finance only']),
        ] as $data) {
            $this->post(route('admin.settings.slides.store'), $data)->assertSessionHasNoErrors();
        }
        $this->get('/')->assertOk()->assertSeeInOrder(['First highlight', 'Second highlight'])
            ->assertDontSee('Hidden highlight')->assertDontSee('About highlight')->assertDontSee('Where Finance Meets the Future');
        $this->get('/about')->assertSee('About highlight')->assertDontSee('First highlight');
        $this->get(route('categories.show', $category->slug))->assertSee('Every category')->assertSee('Finance only');
        $this->get(route('categories.show', $other->slug))->assertSee('Every category')->assertDontSee('Finance only');
        $this->get(route('admin.settings.index', ['tab' => 'slides']))->assertOk()->assertSee('Edit Page Slide');
    }

    public function test_slide_media_type_changes_require_uploads_and_clean_up_old_files(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $this->post(route('admin.settings.slides.store'), $this->slideData([
            'background_type' => 'image', 'media' => UploadedFile::fake()->image('background.png'),
        ]))->assertSessionHasNoErrors();
        $slide = PageSlide::firstOrFail();
        $imagePath = $slide->media_path;
        Storage::disk('public')->assertExists($imagePath);
        $this->get('/')->assertSee('storage/'.$imagePath);
        $this->put(route('admin.settings.slides.update', $slide), $this->slideData(['background_type' => 'video']))
            ->assertSessionHasErrors('media');
        $this->assertSame($imagePath, $slide->fresh()->media_path);
        $this->put(route('admin.settings.slides.update', $slide), $this->slideData([
            'background_type' => 'video', 'media' => UploadedFile::fake()->create('background.mp4', 100, 'video/mp4'),
        ]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($imagePath);
        $videoPath = $slide->fresh()->media_path;
        Storage::disk('public')->assertExists($videoPath);
        $this->get('/')->assertSee('<video', false)->assertSee('storage/'.$videoPath);
        $this->put(route('admin.settings.slides.update', $slide), $this->slideData())->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($videoPath);
        $this->assertNull($slide->fresh()->media_path);
        $this->delete(route('admin.settings.slides.destroy', $slide))->assertRedirect();
        $this->assertDatabaseCount('page_slides', 0);
        $this->get('/')->assertSee('Where Finance Meets the Future');
    }

    public function test_slide_validation_rejects_unsafe_links_colors_and_invalid_targets(): void
    {
        $this->actingAs($this->admin());
        foreach (['javascript:alert(1)', '//example.com', '/\\example.com'] as $url) {
            $this->post(route('admin.settings.slides.store'), $this->slideData(['button_label' => 'Click', 'button_url' => $url]))
                ->assertSessionHasErrors('button_url');
        }
        $this->post(route('admin.settings.slides.store'), $this->slideData(['background_color' => 'red;display:none']))
            ->assertSessionHasErrors('background_color');
        $this->post(route('admin.settings.slides.store'), $this->slideData(['page' => 'admin.dashboard']))
            ->assertSessionHasErrors('page');
        $this->post(route('admin.settings.slides.store'), $this->slideData(['background_type' => 'image']))
            ->assertSessionHasErrors('media');
        $this->assertDatabaseCount('page_slides', 0);
        foreach (['/articles', 'https://example.com/news'] as $url) {
            $this->post(route('admin.settings.slides.store'), $this->slideData(['button_label' => 'Explore', 'button_url' => $url]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_slide_management_requires_settings_permission(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $slide = PageSlide::create($this->slideData());
        $this->post(route('admin.settings.slides.store'), $this->slideData())->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'editor', 'status' => 'active']));
        $this->post(route('admin.settings.slides.store'), $this->slideData())->assertForbidden();
        $this->put(route('admin.settings.slides.update', $slide), $this->slideData())->assertForbidden();
        $this->delete(route('admin.settings.slides.destroy', $slide))->assertForbidden();
        $this->assertDatabaseCount('page_slides', 1);
    }
}
