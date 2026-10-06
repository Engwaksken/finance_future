<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(12, 0));
        $this->actingAs(User::factory()->create(['role' => 'super_admin', 'status' => 'active']));
    }

    private function subscriber(string $name, string $date, string $status = 'active'): NewsletterSubscriber
    {
        return NewsletterSubscriber::create([
            'name' => $name, 'email' => strtolower($name).'@example.com', 'status' => $status, 'subscribed_at' => $date,
        ]);
    }

    public function test_subscriber_calendar_filters_include_both_custom_endpoints_and_legacy_records(): void
    {
        $this->subscriber('DayStart', '2026-10-06 00:00:00');
        $this->subscriber('DayEnd', '2026-10-06 23:59:59', 'unsubscribed');
        $this->subscriber('WeekStart', '2026-10-05 00:00:00');
        $this->subscriber('MonthStart', '2026-10-01 00:00:00');
        $this->subscriber('PreviousMonth', '2026-09-30 23:59:59');
        $this->subscriber('NextDay', '2026-10-07 00:00:00');
        NewsletterSubscriber::forceCreate(['name' => 'Legacy', 'email' => 'legacy@example.com', 'status' => 'active', 'created_at' => '2026-10-06 09:00:00']);

        foreach (['daily' => 3, 'weekly' => 5, 'monthly' => 6, 'all' => 7] as $period => $count) {
            $this->get(route('admin.subscribers.index', ['period' => $period]))->assertOk()
                ->assertViewHas('subscribers', fn ($rows) => $rows->total() === $count)
                ->assertViewHas('stats', fn ($stats) => $stats['Total Subscribers'] === $count);
        }
        $this->get(route('admin.subscribers.index', ['period' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-06']))
            ->assertOk()->assertSee('DayStart')->assertSee('DayEnd')->assertSee('WeekStart')->assertSee('Legacy')
            ->assertDontSee('NextDay')->assertDontSee('PreviousMonth')->assertDontSee('MonthStart');
        $this->get(route('admin.subscribers.index', ['period' => 'daily', 'status' => 'unsubscribed', 'q' => 'day']))
            ->assertViewHas('stats', fn ($stats) => $stats['Total Subscribers'] === 1 && $stats['Active Subscribers'] === 0)
            ->assertSee('DayEnd')->assertDontSee('DayStart');
    }

    public function test_period_validation_rejects_invalid_or_reversed_dates_on_each_report(): void
    {
        foreach (['articles', 'users', 'subscribers', 'audit-logs'] as $page) {
            $route = 'admin.'.$page.'.index';
            $this->get(route($route, ['period' => 'custom']))->assertSessionHasErrors(['from', 'to']);
            $this->get(route($route, ['period' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-05']))->assertSessionHasErrors('to');
            $this->get(route($route, ['period' => 'custom', 'from' => '2026-02-30', 'to' => '2026-10-06']))->assertSessionHasErrors('from');
            $this->get(route($route, ['period' => 'invalid']))->assertSessionHasErrors('period');
        }
    }

    public function test_article_and_user_statistics_follow_filters_not_pagination(): void
    {
        $author = auth()->user();
        for ($i = 0; $i < 25; $i++) {
            Article::forceCreate([
                'author_id' => $author->id, 'title' => 'Report '.$i, 'slug' => 'report-'.$i, 'body' => 'Text',
                'status' => $i < 20 ? 'published' : 'draft', 'views_count' => 10,
                'created_at' => '2026-10-06 10:00:00',
            ]);
        }
        Article::forceCreate(['author_id' => $author->id, 'title' => 'Report old', 'slug' => 'old', 'body' => 'Text', 'status' => 'draft', 'created_at' => '2026-09-01']);
        $trashed = Article::forceCreate(['author_id' => $author->id, 'title' => 'Report trashed', 'slug' => 'trashed', 'body' => 'Text', 'status' => 'draft', 'created_at' => now()]);
        $trashed->delete();
        $this->get(route('admin.articles.index', ['period' => 'daily', 'q' => 'Report']))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats === ['Total Articles' => 25, 'Published' => 20, 'Drafts' => 5, 'Total Views' => 250])
            ->assertViewHas('articles', fn ($rows) => $rows->total() === 25 && $rows->count() === 20);
        $this->get(route('admin.articles.index', ['period' => 'daily', 'status' => 'draft']))
            ->assertViewHas('stats', fn ($stats) => $stats['Total Articles'] === 5 && $stats['Published'] === 0);

        User::factory()->create(['name' => 'Report active', 'status' => 'active', 'created_at' => '2026-10-06 00:00:00']);
        User::factory()->unverified()->create(['name' => 'Report inactive', 'status' => 'inactive', 'created_at' => '2026-10-06 23:59:59']);
        User::factory()->create(['name' => 'Report previous', 'created_at' => '2026-09-30 23:59:59']);
        $this->get(route('admin.users.index', ['period' => 'daily', 'q' => 'Report']))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats === ['Total Users' => 2, 'Active Users' => 1, 'Inactive Users' => 1, 'Verified Emails' => 1]);
    }

    public function test_audit_logs_capture_actor_role_changes_and_redact_passwords(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $role = Role::where('slug', 'editor')->firstOrFail();
        $this->post(route('admin.users.store'), [
            'name' => 'Audited User', 'email' => 'audited@example.com', 'status' => 'active',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password', 'roles' => [$role->id],
        ])->assertSessionHasNoErrors();
        $user = User::where('email', 'audited@example.com')->firstOrFail();
        $log = AuditLog::firstOrFail();
        $this->assertSame(auth()->id(), $log->actor_id);
        $this->assertSame('created', $log->action);
        $this->assertSame('User', $log->subject_type);
        $this->assertSame([$role->id], $log->changes['role_ids']['after']);
        $this->assertSame(['redacted' => true], $log->changes['password']);
        $this->assertStringNotContainsString('secret-password', $log->toJson());
        $this->assertStringNotContainsString($user->password, $log->toJson());

        $this->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'status' => 'active', 'roles' => [],
        ])->assertSessionHasNoErrors();
        $updated = AuditLog::latest('id')->firstOrFail();
        $this->assertSame('updated', $updated->action);
        $this->assertSame([$role->id], $updated->changes['role_ids']['before']);
        $this->assertSame([], $updated->changes['role_ids']['after']);
        $this->put(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'status' => 'active',
            'password' => 'new-secret-password', 'password_confirmation' => 'new-secret-password',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-secret-password', $user->fresh()->password));
        $this->assertSame(['redacted' => true], AuditLog::latest('id')->first()->changes['password']);
        $this->get(route('admin.audit-logs.index'))->assertOk()->assertSee('Audited User')->assertDontSee('new-secret-password');
    }

    public function test_failed_or_unchanged_mutations_and_read_requests_do_not_create_audit_events(): void
    {
        $this->post(route('admin.users.store'), ['name' => 'Invalid'])->assertSessionHasErrors();
        $this->delete(route('admin.users.destroy', auth()->user()))->assertSessionHas('error');
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.audit-logs.index'))->assertOk();
        $this->assertDatabaseCount('audit_logs', 0);
        $this->put(route('admin.settings.update'), ['section' => 'general', 'site_name' => 'Audited site'])->assertSessionHasNoErrors();
        $log = AuditLog::firstOrFail();
        $this->assertSame('Settings', $log->subject_type);
        $this->assertSame('Audited site', $log->changes['site_name']['after']);
        $this->put(route('admin.settings.update'), ['section' => 'general', 'site_name' => 'Audited site'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_article_soft_deletes_are_audited_and_body_content_is_not_stored(): void
    {
        $this->post(route('admin.articles.store'), ['title' => 'Audit Article', 'status' => 'draft', 'body' => '<p>Private long-form content</p>'])->assertSessionHasNoErrors();
        $article = Article::firstOrFail();
        $this->assertSame(['redacted' => true], AuditLog::firstOrFail()->changes['body']);
        $this->assertStringNotContainsString('Private long-form content', AuditLog::first()->toJson());
        $this->delete(route('admin.articles.destroy', $article))->assertSessionHasNoErrors();
        $log = AuditLog::latest('id')->firstOrFail();
        $this->assertSame('deleted', $log->action);
        $this->assertSame('Audit Article', $log->changes['title']['before']);
        $this->assertSoftDeleted($article);
    }

    public function test_audit_period_actor_and_action_filters_and_historical_actor_names(): void
    {
        $actor = User::factory()->create(['name' => 'Historical Admin']);
        foreach (['2026-10-06 23:59:59' => 'created', '2026-10-05 12:00:00' => 'updated', '2026-09-30 12:00:00' => 'deleted'] as $date => $action) {
            AuditLog::forceCreate([
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action,
                'subject_type' => 'Category', 'subject_id' => '123', 'subject_label' => 'Historical Category',
                'route' => 'admin.categories.store', 'changes' => ['name' => ['before' => null, 'after' => 'Category']], 'created_at' => $date,
            ]);
        }
        foreach (['daily' => 1, 'weekly' => 2, 'monthly' => 2, 'all' => 3] as $period => $count) {
            $this->get(route('admin.audit-logs.index', ['period' => $period]))->assertOk()
                ->assertViewHas('stats', fn ($stats) => $stats['Audit Events'] === $count);
        }
        $this->get(route('admin.audit-logs.index', ['period' => 'daily', 'actor_id' => $actor->id, 'subject_type' => 'Category', 'action' => 'created']))
            ->assertOk()->assertViewHas('logs', fn ($rows) => $rows->total() === 1)
            ->assertViewHas('stats', fn ($stats) => $stats['Audit Events'] === 1 && $stats['Created'] === 1);
        $actor->delete();
        $this->assertNull(AuditLog::first()->actor_id);
        $this->get(route('admin.audit-logs.index', ['q' => 'Historical Admin']))->assertOk()->assertSee('Historical Admin')
            ->assertViewHas('logs', fn ($rows) => $rows->total() === 3);
    }

    public function test_reporting_permissions_protect_subscribers_and_audit_history(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $this->actingAs(User::factory()->create(['role' => 'editor', 'status' => 'active']));
        $this->get(route('admin.subscribers.index'))->assertForbidden();
        $this->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->get(route('admin.articles.index'))->assertOk()->assertDontSee(route('admin.subscribers.index'))->assertDontSee(route('admin.audit-logs.index'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'status' => 'active']));
        $this->get(route('admin.subscribers.index'))->assertOk();
        $this->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_repeated_active_subscriptions_preserve_dates_and_reactivation_updates_them(): void
    {
        $subscriber = $this->subscriber('Existing', '2026-09-01 12:00:00');
        $this->post(route('newsletter.subscribe'), ['email' => $subscriber->email])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-01 12:00:00', $subscriber->fresh()->subscribed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Existing', $subscriber->fresh()->name);
        $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()->subDay()]);
        $this->post(route('newsletter.subscribe'), ['email' => $subscriber->email])->assertSessionHasNoErrors();
        $this->assertTrue($subscriber->fresh()->subscribed_at->equalTo(now()));
        $this->assertNull($subscriber->fresh()->unsubscribed_at);
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }
}
