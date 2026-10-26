<?php

namespace Tests\Feature;

use App\Core\Middleware\HandleInertiaRequests;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\Plan;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\CrossesPlatformHost;
use Tests\TestCase;

/**
 * The public site (Landing) that Paddle's domain review looks at: home,
 * pricing, the three legal pages and contact. Open to everyone, tenant host
 * only, with the operating entity named on every legal page and a footer
 * linking all of them from the public and auth pages.
 */
class PublicSiteTest extends TestCase
{
    use CrossesPlatformHost, RefreshDatabase;

    private ?string $legalFixtures = null;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://zyncwork.test',
            'site.operator' => [
                'name' => 'Example Operator LLC',
                'address' => '1 Test Street, Testville',
                'email' => 'hello@operator.test',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->legalFixtures) {
            File::deleteDirectory($this->legalFixtures);
        }
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Legal files written by the test, in a folder of its own (deleted in tearDown). */
    private function useLegalFiles(array $files): void
    {
        $this->legalFixtures = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zyncwork-legal-'.uniqid();
        File::makeDirectory($this->legalFixtures);

        foreach ($files as $name => $markdown) {
            File::put($this->legalFixtures.DIRECTORY_SEPARATOR.$name, $markdown);
        }

        config(['site.legal_path' => $this->legalFixtures]);
    }

    /** @return array<string, array{string, string, string}> route name, path, <title> */
    public static function pages(): array
    {
        return [
            'home' => ['landing.index', '/', 'Project management and time tracking for teams'],
            'pricing' => ['landing.pricing', '/pricing', 'Pricing'],
            'terms' => ['landing.terms', '/terms', 'Terms of Service'],
            'privacy' => ['landing.privacy', '/privacy', 'Privacy Policy'],
            'refunds' => ['landing.refunds', '/refund-policy', 'Refund Policy'],
            'contact' => ['landing.contact', '/contact', 'Contact'],
        ];
    }

    private function assertFooterLinks($response): void
    {
        foreach (['landing.pricing', 'landing.terms', 'landing.privacy', 'landing.refunds', 'landing.contact'] as $route) {
            $response->assertSee('href="'.route($route).'"', false);
        }
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_each_page_loads_for_a_guest_with_its_title_and_meta_tags(string $route, string $path, string $title): void
    {
        $response = $this->get($path)->assertOk();

        $this->assertSame(url($path), route($route));
        $response->assertSee('<title>'.e($title).' · '.e(config('app.name')).'</title>', false);
        $response->assertSee('<meta name="description" content="', false);
        $response->assertSee('<link rel="canonical" href="https://zyncwork.test/'.ltrim($path, '/').'">', false);
        $response->assertDontSee('<meta name="robots" content="noindex">', false);
        $this->assertDoesNotMatchRegularExpression('/\bsite\.[a-z_]+\.[a-z_]+/', $response->getContent(), 'A translation key is shown raw.');
        $this->assertFooterLinks($response);
        $response->assertSee('© '.date('Y').' Example Operator LLC', false);
    }

    #[DataProvider('pages')]
    public function test_each_page_is_not_served_on_the_platform_host(string $route, string $path): void
    {
        $this->get($this->platform($path))->assertNotFound();
    }

    public function test_the_starter_kit_pages_are_gone(): void
    {
        $this->get('/features')->assertNotFound();
        $this->get('/documentation')->assertNotFound();
        $this->get('/')->assertDontSee('Feature Kit');
    }

    // ── Who can see them ──────────────────────────────────────────────────

    #[DataProvider('pages')]
    public function test_a_signed_in_user_sees_each_page_with_a_dashboard_link(string $route, string $path): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'admin@acme.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)->get($path)
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false);
    }

    /** EnsureEmailIsVerified lets the public pages through: a new signup can read the terms from the verify screen. */
    #[DataProvider('pages')]
    public function test_an_unverified_user_can_read_each_page(string $route, string $path): void
    {
        $user = User::create(['name' => 'New', 'email' => 'new@acme.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user)->get($path)->assertOk();
        // Everything else is still blocked.
        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    #[DataProvider('pages')]
    public function test_an_inertia_visit_becomes_a_full_page_load(string $route, string $path): void
    {
        // The version the middleware computes (Inertia::getVersion() is empty before a request), so
        // Inertia's own version check doesn't answer with a 409 before the controller runs.
        $version = (string) app(HandleInertiaRequests::class)->version(request());

        $this->get($path, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url($path));
    }

    // ── Footer on the auth pages ──────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function authPages(): array
    {
        return [
            'login' => ['/auth/login'],
            'register' => ['/auth/register'],
            'forgot password' => ['/auth/forgot-password'],
            'reset password' => ['/auth/reset-password/some-token'],
            'invitation' => ['/invitations/unknown-token'],
        ];
    }

    #[DataProvider('authPages')]
    public function test_the_auth_pages_link_every_legal_page(string $path): void
    {
        $response = $this->get($path)->assertOk();

        $this->assertFooterLinks($response);
        $response->assertSee('<meta name="robots" content="noindex">', false);
        $response->assertDontSee('Feature Kit');
    }

    public function test_the_verify_screen_links_every_legal_page(): void
    {
        $user = User::create(['name' => 'New', 'email' => 'new@acme.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->assertFooterLinks($this->actingAs($user)->get(route('verification.notice'))->assertOk());
    }

    public function test_the_platform_console_has_no_footer(): void
    {
        $this->get($this->platform('/login'))
            ->assertOk()
            ->assertDontSee(__('site.footer.terms'))
            ->assertDontSee(route('landing.terms'));
    }

    public function test_signup_says_that_creating_an_account_accepts_the_terms(): void
    {
        $this->get('/auth/register')
            ->assertOk()
            ->assertSee('By creating an account, you agree to the <a href="'.route('landing.terms').'" class="text-primary hover:underline">Terms of Service</a> and the <a href="'.route('landing.privacy').'" class="text-primary hover:underline">Privacy Policy</a>.', false);
    }

    // ── Legal pages ───────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function legalPages(): array
    {
        return ['terms' => ['/terms'], 'privacy' => ['/privacy'], 'refunds' => ['/refund-policy'], 'contact' => ['/contact']];
    }

    #[DataProvider('legalPages')]
    public function test_the_legal_and_contact_pages_name_the_operator(string $path): void
    {
        $this->get($path)
            ->assertOk()
            ->assertSee('Example Operator LLC')
            ->assertSee('1 Test Street, Testville')
            ->assertSee('href="mailto:hello@operator.test"', false);
    }

    public function test_a_placeholder_document_is_marked_as_unpublished(): void
    {
        $this->useLegalFiles([
            'terms.md' => "> **PLACEHOLDER**: replace this file.\n",
            'privacy.md' => "> **PLACEHOLDER**: replace this file.\n",
            'refund-policy.md' => "> **PLACEHOLDER**: replace this file.\n",
        ]);

        foreach (['/terms', '/privacy', '/refund-policy'] as $path) {
            $this->get($path)->assertOk()->assertSee('data-placeholder', false)->assertSee(__('site.legal.placeholder'));
        }
    }

    public function test_a_document_is_rendered_from_its_markdown_file(): void
    {
        $this->useLegalFiles([
            'terms.md' => "# Our terms\n\nThis service is run by :operator_name, :operator_address. Write to :contact_email.\n\n- First rule\n",
            'privacy.md' => "# Privacy\n",
            'refund-policy.md' => "# Refunds\n",
        ]);

        $this->get('/terms')
            ->assertOk()
            ->assertSee('<h1>Our terms</h1>', false)
            // GitHub-flavoured markdown links the address.
            ->assertSee('This service is run by Example Operator LLC, 1 Test Street, Testville. Write to <a href="mailto:hello@operator.test">hello@operator.test</a>.', false)
            ->assertSee('<li>First rule</li>', false)
            ->assertDontSee('data-placeholder', false);

        $this->get('/privacy')->assertSee('<h1>Privacy</h1>', false);
        $this->get('/refund-policy')->assertSee('<h1>Refunds</h1>', false);
    }

    public function test_html_and_unsafe_links_in_a_document_are_not_rendered(): void
    {
        $this->useLegalFiles([
            'terms.md' => "<script>alert('x')</script>\n\n[Click](javascript:alert('x'))\n\n[Safe](https://example.com)\n",
            'privacy.md' => '', 'refund-policy.md' => '',
        ]);

        $this->get('/terms')
            ->assertOk()
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('<a href="https://example.com">Safe</a>', false);
    }

    // ── Pricing ───────────────────────────────────────────────────────────

    /** The page and signup read the trial from the same place, so changing it changes both. */
    public function test_pricing_shows_the_trial_that_signup_gives(): void
    {
        Carbon::setTestNow('2026-10-26 10:00:00');
        Mail::fake();
        config(['billing.trial_days' => 21]);
        Plan::trialPlan()->update(['included_seats' => 7]);

        $this->get('/pricing')
            ->assertOk()
            ->assertSeeInOrder(['data-plan="trial"', '21 days', 'Up to 7 users', 'No card required'], false);
        $this->get('/')->assertSee('Try it free for 21 days, no card required.');

        $this->post('/auth/register', [
            'company_name' => 'Acme Inc', 'name' => 'Jane Founder', 'email' => 'jane@acme.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect();

        $subscription = Tenant::where('name', 'Acme Inc')->sole()->subscription;
        $this->assertSame(7, $subscription->seats);
        $this->assertTrue($subscription->trial_ends_at->equalTo(Carbon::parse('2026-11-16 10:00:00')));
    }

    public function test_pricing_lists_the_active_priced_plans(): void
    {
        Plan::create(['key' => 'annual', 'name' => 'Annual', 'price_amount' => 9600, 'currency' => 'USD', 'interval' => Plan::INTERVAL_YEAR, 'is_trial' => false, 'is_active' => true, 'sort' => 2]);
        Plan::create(['key' => 'retired', 'name' => 'Retired Plan', 'price_amount' => 500, 'currency' => 'USD', 'interval' => Plan::INTERVAL_MONTH, 'is_trial' => false, 'is_active' => false, 'sort' => 3]);
        Plan::create(['key' => 'unpriced', 'name' => 'Unpriced Plan', 'is_trial' => false, 'is_active' => true, 'sort' => 4]);

        $this->get('/pricing')
            ->assertOk()
            ->assertSeeInOrder(['data-plan="standard"', 'Standard', '10.00 USD', 'per seat, per month', 'data-plan="annual"', 'Annual', '96.00 USD', 'per seat, per year'], false)
            ->assertDontSee('Retired Plan')
            ->assertDontSee('Unpriced Plan');
    }

    public function test_pricing_shows_the_billing_note_and_links_the_refund_policy(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee(__('site.pricing.billing_note'))
            ->assertSee('href="'.route('landing.refunds').'"', false);
    }

    // ── site:check ────────────────────────────────────────────────────────

    public function test_site_check_fails_while_anything_is_a_placeholder(): void
    {
        config(['site.operator.address' => '[PLACEHOLDER: registered address]']);
        Plan::create(['key' => 'unpriced', 'name' => 'Unpriced', 'is_trial' => false, 'is_active' => true, 'sort' => 4]);
        $this->useLegalFiles([
            'terms.md' => "> **PLACEHOLDER**: replace this file.\n",
            'privacy.md' => "> **PLACEHOLDER**: replace this file.\n",
            'refund-policy.md' => "> **PLACEHOLDER**: replace this file.\n",
        ]);

        $this->artisan('site:check')
            ->expectsOutputToContain('operator.address is not set')
            ->expectsOutputToContain('terms.md is not finished')
            ->expectsOutputToContain('privacy.md is not finished')
            ->expectsOutputToContain('refund-policy.md is not finished')
            ->expectsOutputToContain('pricing.billing_note')
            ->expectsOutputToContain('Plan [unpriced] is active but has no price.')
            ->doesntExpectOutputToContain('operator.name')
            ->assertFailed();
    }

    public function test_site_check_reports_a_missing_document(): void
    {
        $this->useLegalFiles(['terms.md' => "# Terms\n", 'privacy.md' => "# Privacy\n"]);

        $this->artisan('site:check')
            ->expectsOutputToContain('refund-policy.md is missing.')
            ->doesntExpectOutputToContain('terms.md')
            ->assertFailed();
    }

    /** A drafted document with points still to confirm is not finished either. */
    public function test_a_document_with_points_to_confirm_is_not_finished(): void
    {
        $this->useLegalFiles([
            'terms.md' => "# Terms\n\nBilled monthly. [[CONFIRM: monthly only]]\n",
            'privacy.md' => "# Privacy\n",
            'refund-policy.md' => "# Refunds\n",
        ]);

        $this->get('/terms')->assertOk()->assertSee('data-placeholder', false);
        $this->get('/privacy')->assertOk()->assertDontSee('data-placeholder', false);

        $this->artisan('site:check')
            ->expectsOutputToContain('terms.md is not finished')
            ->doesntExpectOutputToContain('privacy.md')
            ->assertFailed();
    }

    public function test_site_check_passes_once_everything_is_filled_in(): void
    {
        $this->useLegalFiles(['terms.md' => "# Terms\n", 'privacy.md' => "# Privacy\n", 'refund-policy.md' => "# Refunds\n"]);
        app('translator')->addLines(['site.pricing.billing_note' => 'Payments are processed by our reseller.'], 'en');
        // Not offered, so it needs no price.
        Plan::create(['key' => 'retired', 'name' => 'Retired', 'is_trial' => false, 'is_active' => false, 'sort' => 5]);

        $this->artisan('site:check')
            ->expectsOutputToContain('no placeholders left')
            ->assertSuccessful();
    }
}
