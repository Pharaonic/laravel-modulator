<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class MakeOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->directory('Modules/Shop/app');
        $this->reload();
    }

    private function file(string $path): string
    {
        $file = $this->base.'/Modules/Shop/'.$path;
        $this->assertFileExists($file);
        token_get_all((string) file_get_contents($file), TOKEN_PARSE);

        return (string) file_get_contents($file);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function make(string $type, array $parameters): string
    {
        [$status, $output] = $this->command('module:make:'.$type, ['module' => 'Shop'] + $parameters);
        $this->assertSame(0, $status, $type.': '.$output);

        return $output;
    }

    public function test_every_laravel_make_command_has_a_module_command(): void
    {
        $laravel = ['cache-table', 'cast', 'channel', 'class', 'command', 'component', 'config', 'controller', 'event', 'exception', 'factory', 'interface', 'job', 'job-middleware', 'listener', 'mail', 'middleware', 'migration', 'model', 'notification', 'notifications-table', 'observer', 'policy', 'provider', 'queue-batches-table', 'queue-failed-table', 'queue-table', 'request', 'resource', 'rule', 'scope', 'seeder', 'session-table', 'test', 'trait', 'view'];
        $commands = Artisan::all();

        foreach ($laravel as $type) {
            $this->assertArrayHasKey('module:make:'.$type, $commands);
        }
    }

    public function test_published_stubs_are_used_without_configuration(): void
    {
        $this->assertSame(0, $this->command('vendor:publish', ['--tag' => 'modulator-stubs'])[0]);
        $this->assertFileExists($this->base.'/stubs/modulator/controller.stub');
        $this->assertFileExists($this->base.'/stubs/modulator/module/provider.stub');

        $this->write('stubs/modulator/service.stub', "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} // published\n{\n}\n");
        $this->make('service', ['name' => 'Pricing']);
        $this->assertStringContainsString('// published', $this->file('app/Services/Pricing.php'));

        $this->reload(['generation' => ['pool' => 'local', 'stubs' => $this->base.'/elsewhere']]);
        $this->make('service', ['name' => 'Billing']);
        $this->assertStringNotContainsString('// published', $this->file('app/Services/Billing.php'), 'A configured directory wins over stubs/modulator.');
    }

    public function test_pest_is_refused_when_it_is_not_installed(): void
    {
        foreach ([['test', ['name' => 'PriceTest', '--pest' => true]], ['controller', ['name' => 'PriceController', '--pest' => true]]] as [$type, $parameters]) {
            [$status, $output] = $this->command('module:make:'.$type, ['module' => 'Shop'] + $parameters);

            $this->assertSame(1, $status, $type.': '.$output);
            $this->assertStringContainsString('--pest needs Pest', $output);
        }

        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/tests/Feature/PriceTest.php');
        $this->assertFileDoesNotExist($this->base.'/Modules/Shop/app/Http/Controllers/PriceController.php');

        $this->make('test', ['name' => 'StockTest']);
        $this->assertStringContainsString('extends TestCase', $this->file('tests/Feature/StockTest.php'), 'Without Pest, PHPUnit is the default.');
    }

    public function test_pest_is_the_default_when_it_is_installed_and_phpunit_wins_when_asked(): void
    {
        $this->write('vendor/bin/pest', '<?php');

        $this->make('test', ['name' => 'PriceTest']);
        $feature = $this->file('tests/Feature/PriceTest.php');
        $this->assertStringContainsString("test('example'", $feature);
        $this->assertStringContainsString('uses(Tests\\TestCase::class);', $feature, 'A module feature test is outside tests/, so it binds the TestCase itself.');

        $this->make('test', ['name' => 'MathTest', '--unit' => true]);
        $unit = $this->file('tests/Unit/MathTest.php');
        $this->assertStringContainsString("test('example'", $unit);
        $this->assertStringNotContainsString('uses(', $unit);

        $this->make('test', ['name' => 'StockTest', '--phpunit' => true]);
        $this->assertStringContainsString('extends TestCase', $this->file('tests/Feature/StockTest.php'));
    }

    public function test_controller_options(): void
    {
        $this->make('controller', ['name' => 'Admin/ProductController', '--model' => 'Product', '--requests' => true]);
        $controller = $this->file('app/Http/Controllers/Admin/ProductController.php');
        $this->assertStringContainsString('namespace Modules\\Shop\\Http\\Controllers\\Admin;', $controller);
        $this->assertStringContainsString('use Modules\\Shop\\Http\\Requests\\StoreProductRequest;', $controller);
        $this->assertStringContainsString('public function store(StoreProductRequest $request)', $controller);
        $this->assertStringContainsString('public function update(UpdateProductRequest $request, Product $product)', $controller);
        $this->file('app/Http/Requests/StoreProductRequest.php');
        $this->file('app/Http/Requests/UpdateProductRequest.php');

        $this->make('controller', ['name' => 'CommentController', '--parent' => 'Post', '--api' => true]);
        $nested = $this->file('app/Http/Controllers/CommentController.php');
        $this->assertStringContainsString('public function index(Post $post)', $nested);
        $this->assertStringContainsString('public function show(Post $post, Comment $comment)', $nested);
        $this->assertStringNotContainsString('function create(', $nested);

        $this->make('controller', ['name' => 'PingController', '--invokable' => true, '--test' => true, '--phpunit' => true]);
        $this->assertStringContainsString('__invoke', $this->file('app/Http/Controllers/PingController.php'));
        $this->assertStringContainsString('namespace Modules\\Shop\\Tests\\Feature\\Http\\Controllers;', $this->file('tests/Feature/Http/Controllers/PingControllerTest.php'));

        $this->write('stubs/modulator/controller.admin.stub', "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} // admin\n{\n}\n");
        $this->reload(['generation' => ['pool' => 'local', 'stubs' => $this->base.'/stubs/modulator']]);
        $this->make('controller', ['name' => 'PanelController', '--type' => 'admin']);
        $this->assertStringContainsString('// admin', $this->file('app/Http/Controllers/PanelController.php'));
        $this->assertSame(1, $this->command('module:make:controller', ['module' => 'Shop', 'name' => 'X', '--type' => 'missing'])[0]);

        $this->make('controller', ['name' => 'ProfileController', '--singleton' => true, '--creatable' => true]);
        $profile = $this->file('app/Http/Controllers/ProfileController.php');
        $this->assertStringContainsString('public function show()', $profile);
        $this->assertStringNotContainsString('abort(404)', $profile);

        $this->make('controller', ['name' => 'Api/SettingsController', '--singleton' => true, '--api' => true]);
        $settings = $this->file('app/Http/Controllers/Api/SettingsController.php');
        $this->assertStringContainsString('abort(404);', $settings, 'A singleton that is not creatable refuses create and store.');
        $this->assertStringNotContainsString('public function edit(', $settings);
    }

    public function test_model_options(): void
    {
        $this->make('model', ['name' => 'RoleUser', '--pivot' => true]);
        $this->assertStringContainsString('extends Pivot', $this->file('app/Models/RoleUser.php'));

        $this->make('model', ['name' => 'Taggable', '--morph-pivot' => true]);
        $this->assertStringContainsString('extends MorphPivot', $this->file('app/Models/Taggable.php'));

        $this->make('model', ['name' => 'Order', '--api' => true, '--requests' => true]);
        $controller = $this->file('app/Http/Controllers/OrderController.php');
        $this->assertStringContainsString('StoreOrderRequest $request', $controller);
        $this->assertStringNotContainsString('function edit(', $controller);
        $this->file('app/Http/Requests/UpdateOrderRequest.php');

        $this->assertSame(1, $this->command('module:make:model', ['module' => 'Shop', 'name' => 'Both', '--pivot' => true, '--morph-pivot' => true])[0]);
    }

    public function test_class_level_options(): void
    {
        $this->make('class', ['name' => 'Pricer', '--invokable' => true]);
        $this->assertStringContainsString('__invoke', $this->file('app/Pricer.php'));

        $this->make('exception', ['name' => 'Failed', '--render' => true, '--report' => true]);
        $exception = $this->file('app/Exceptions/Failed.php');
        $this->assertStringContainsString('function render(', $exception);
        $this->assertStringContainsString('function report(', $exception);

        $this->make('rule', ['name' => 'Required', '--implicit' => true]);
        $this->assertMatchesRegularExpression('/ImplicitRule|\$implicit = true/', $this->file('app/Rules/Required.php'));

        $this->make('policy', ['name' => 'ProductPolicy', '--model' => 'Product', '--guard' => 'web']);
        $this->assertStringContainsString('public function update(', $this->file('app/Policies/ProductPolicy.php'));

        $this->make('job-middleware', ['name' => 'RateLimited', '--test' => true]);
        $this->assertStringContainsString('handle(object $job, Closure $next)', $this->file('app/Jobs/Middleware/RateLimited.php'));
        $this->file('tests/Feature/Jobs/Middleware/RateLimitedTest.php');

        $this->make('listener', ['name' => 'SendReceipt', '--event' => 'OrderPlaced', '--queued' => true]);
        $this->assertStringContainsString('handle(OrderPlaced $event)', $this->file('app/Listeners/SendReceipt.php'));

        $this->make('resource', ['name' => 'Products', '--collection' => true]);
        $this->assertStringContainsString('extends ResourceCollection', $this->file('app/Http/Resources/Products.php'));
    }

    public function test_mail_notification_and_view_templates(): void
    {
        $this->make('mail', ['name' => 'OrderShipped', '--markdown' => 'mail.orders.shipped']);
        $this->assertStringContainsString("markdown('shop::mail.orders.shipped')", $this->file('app/Mail/OrderShipped.php'));
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/mail/orders/shipped.blade.php');

        $this->make('mail', ['name' => 'Welcome', '--view' => null]);
        $this->assertStringContainsString("view('shop::mail.welcome')", $this->file('app/Mail/Welcome.php'));
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/mail/welcome.blade.php');

        $this->make('notification', ['name' => 'InvoicePaid', '--markdown' => null]);
        $this->assertStringContainsString("markdown('shop::mail.invoice-paid')", $this->file('app/Notifications/InvoicePaid.php'));

        $this->make('view', ['name' => 'products.index', '--test' => true]);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/products/index.blade.php');
        $test = $this->file('tests/Feature/View/ProductsIndexTest.php');
        $this->assertStringContainsString("view('shop::products.index'", $test);
        // $this->view() (InteractsWithViews) only exists from Laravel 8: the test renders through view() on every line.
        $this->assertStringNotContainsString('$this->view(', $test);
        $this->assertStringContainsString('->render()', $test);

        $this->make('view', ['name' => 'emails/plain', '--extension' => 'txt']);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/emails/plain.txt');

        $this->assertSame(1, $this->command('module:make:view', ['module' => 'Shop', 'name' => '../escape'])[0]);
        $this->assertSame(1, $this->command('module:make:config', ['module' => 'Shop', 'name' => '../escape'])[0]);
        $this->make('config', ['name' => 'billing']);
        $this->assertStringContainsString("config('modules.shop.billing')", $this->file('config/billing.php'));
    }

    public function test_casts_work_on_a_module_model(): void
    {
        $this->make('cast', ['name' => 'Upper']);
        $this->make('cast', ['name' => 'Trimmed', '--inbound' => true]);
        $this->assertStringContainsString('implements CastsInboundAttributes', $this->file('app/Casts/Trimmed.php'));

        $cast = str_replace('return $value;', 'return strtoupper((string) $value);', $this->file('app/Casts/Upper.php'));
        $this->write('Modules/Shop/app/Casts/Upper.php', $cast);
        $this->write('Modules/Shop/app/Models/Label.php', <<<'PHP'
<?php

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Shop\Casts\Upper;

class Label extends Model
{
    protected $casts = ['name' => Upper::class];
}
PHP);
        $this->reload();

        $label = new \Modules\Shop\Models\Label();
        $label->name = 'sale';
        $this->assertSame('SALE', $label->name);
    }

    public function test_components_render_from_the_module(): void
    {
        $this->make('component', ['name' => 'Alert', '--view' => true]);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/components/alert.blade.php');
        $this->assertDirectoryDoesNotExist($this->base.'/Modules/Shop/app/View');

        $this->make('component', ['name' => 'Badge', '--inline' => true]);
        $this->assertStringContainsString("<<<'blade'", $this->file('app/View/Components/Badge.php'));

        $this->make('component', ['name' => 'Forms/Input']);
        $this->assertFileExists($this->base.'/Modules/Shop/resources/views/components/forms/input.blade.php');
        $this->write('Modules/Shop/resources/views/components/forms/input.blade.php', '<input name="shop">');
        $this->reload();

        $class = 'Modules\\Shop\\View\\Components\\Forms\\Input';
        $this->assertSame('<input name="shop">', trim((string) (new $class())->render()->render()));
    }

    public function test_table_migrations_match_the_framework_tables(): void
    {
        $this->app['config']->set('database.default', 'testing');
        $types = ['cache-table' => 'cache', 'notifications-table' => 'notifications', 'queue-batches-table' => 'job_batches', 'queue-failed-table' => 'failed_jobs', 'queue-table' => 'jobs', 'session-table' => 'sessions'];

        foreach (array_keys($types) as $type) {
            $this->make($type, []);
        }

        [$status, $output] = $this->command('module:make:queue-table', ['module' => 'Shop']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $output);

        foreach (glob($this->base.'/Modules/Shop/database/migrations/*.php') ?: [] as $file) {
            token_get_all((string) file_get_contents($file), TOKEN_PARSE);
        }

        $this->reload();
        $this->app['config']->set('database.default', 'testing');
        $this->assertSame(0, $this->command('module:migrate', ['module' => 'Shop'])[0]);

        foreach ($types as $table) {
            $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable($table), $table);
        }
    }

    public function test_a_batched_job_runs_in_a_batch(): void
    {
        $this->app['config']->set('database.default', 'testing');
        $this->make('queue-batches-table', []);
        $this->make('job', ['name' => 'ImportRow', '--batched' => true]);
        $job = $this->file('app/Jobs/ImportRow.php');
        $this->assertStringContainsString('use Batchable,', $job);
        $this->write('Modules/Shop/app/Jobs/ImportRow.php', str_replace("        //\n    }", "        cache()->increment('imported');\n    }", $job));

        $this->reload();
        $this->app['config']->set('database.default', 'testing');
        $this->app['config']->set('queue.default', 'sync');
        $this->assertSame(0, $this->command('module:migrate', ['module' => 'Shop'])[0]);

        $batch = \Illuminate\Support\Facades\Bus::batch([new \Modules\Shop\Jobs\ImportRow(), new \Modules\Shop\Jobs\ImportRow()])->dispatch();

        $this->assertSame(2, cache('imported'));
        $this->assertTrue(\Illuminate\Support\Facades\Bus::findBatch($batch->id)->finished());
    }

    public function test_a_singleton_controller_serves_its_singleton_routes(): void
    {
        $this->make('controller', ['name' => 'ProfileController', '--singleton' => true, '--creatable' => true]);
        $this->make('controller', ['name' => 'AvatarController', '--singleton' => true]);
        $this->reload();

        \Illuminate\Support\Facades\Route::singleton('profile', 'Modules\\Shop\\Http\\Controllers\\ProfileController')->creatable();
        \Illuminate\Support\Facades\Route::singleton('avatar', 'Modules\\Shop\\Http\\Controllers\\AvatarController')->creatable();

        $this->get('/profile')->assertOk();
        $this->get('/profile/create')->assertOk();
        $this->get('/avatar')->assertOk();
        $this->get('/avatar/create')->assertNotFound();
    }
}
