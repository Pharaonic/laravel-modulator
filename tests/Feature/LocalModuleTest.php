<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\ModulatorServiceProvider;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class LocalModuleTest extends TestCase
{
    public function test_the_provider_merges_its_configuration_under_pharaonic(): void
    {
        $this->assertNotEmpty($this->app->getProviders(ModulatorServiceProvider::class));
        $this->assertIsArray(config('pharaonic.modulator.pools'));
        $this->assertSame([], Modules::all());
    }

    public function test_a_module_with_only_app_autoloads_its_classes(): void
    {
        $this->write('Modules/Catalog/app/Models/Product.php', <<<'PHP'
<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

final class Product extends Model
{
}
PHP);

        $this->reload();

        $this->assertTrue(class_exists('Modules\\Catalog\\Models\\Product'));
        $this->assertSame(
            realpath($this->base.'/Modules/Catalog/app/Models/Product.php'),
            (new \ReflectionClass('Modules\\Catalog\\Models\\Product'))->getFileName()
        );
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Builder::class, \Modules\Catalog\Models\Product::query());
        $this->assertFileDoesNotExist($this->base.'/Modules/Catalog/composer.json');
        $this->assertFileDoesNotExist($this->base.'/Modules/Catalog/module.php');
    }

    public function test_the_conventional_provider_is_registered_and_booted(): void
    {
        $this->writeProvider('Modules/Billing', 'Modules\\Billing', 'Billing');
        $this->directory('Modules/Quiet/app');

        $this->reload();

        $this->assertTrue($this->app->make('Modules\\Billing.registered'));
        $this->assertTrue($this->app->make('Modules\\Billing.booted'));
        $this->assertTrue(Modules::find('Quiet')->isEnabled(), 'A module without a provider is valid.');
    }

    public function test_the_helpers_file_of_an_enabled_module_is_loaded(): void
    {
        $this->directory('Modules/Wallet/app');
        $this->write('Modules/Wallet/helpers.php', "<?php if (! function_exists('wallet_balance')) { function wallet_balance() { return 42; } }");
        $this->directory('Modules/Wallet/Modules/Cards/app');
        $this->write('Modules/Wallet/Modules/Cards/helpers.php', "<?php if (! function_exists('wallet_card')) { function wallet_card() { return 'card'; } }");
        $this->directory('Modules/Vault/app');
        $this->write('Modules/Vault/helpers.php', "<?php function vault_secret() { return 'secret'; }");

        $this->reload(['disabled' => ['Vault']]);

        $this->assertSame(42, wallet_balance());
        $this->assertSame('card', wallet_card(), 'A child module loads its own helpers.');
        $this->assertFalse(function_exists('vault_secret'), 'A disabled module does not load its helpers.');
    }

    public function test_resources_are_loaded_with_laravel_native_namespaces(): void
    {
        $this->directory('Modules/Blog/app');
        $this->write('Modules/Blog/config/feed.php', "<?php return ['size' => 10];");
        $this->write('Modules/Blog/resources/views/post.blade.php', 'Post {{ $title }}');
        $this->write('Modules/Blog/resources/lang/en/messages.php', "<?php return ['hello' => 'Hello from blog'];");
        $this->write('Modules/Blog/resources/lang/fr.json', '{"Welcome": "Bienvenue"}');
        $this->write('Modules/Blog/database/migrations/2026_01_01_000000_create_posts_table.php', '<?php');
        $this->write('Modules/Blog/routes/web.php', "<?php Route::get('blog/{id}', function (\$id) { return 'post '.\$id; })->name('blog.show');");
        $this->write('Modules/Blog/routes/api.php', "<?php Route::get('blog', function () { return ['ok' => true]; })->name('api.blog');");

        $this->reload();

        $this->assertSame(10, config('modules.blog.feed.size'));
        $this->assertSame('Post Hi', view('blog::post', ['title' => 'Hi'])->render());
        $this->assertSame('Hello from blog', trans('blog::messages.hello'));
        $this->assertSame('Bienvenue', trans('Welcome', [], 'fr'));
        $this->assertContains(realpath($this->base.'/Modules/Blog/database/migrations'), array_map('realpath', $this->app->make('migrator')->paths()));

        $this->get('blog/5')->assertOk()->assertSee('post 5');
        $this->getJson('api/blog')->assertOk()->assertJson(['ok' => true]);

        $route = Route::getRoutes()->getByName('blog.show');
        $this->assertSame('Blog', $route->getAction('module'));
        $this->assertContains('web', $route->middleware());
        $this->assertSame('Blog', Route::getRoutes()->getByName('api.blog')->getAction('module'));
        $this->assertSame(url('blog/5'), route('blog.show', 5));
    }

    public function test_a_disabled_module_loads_nothing(): void
    {
        $this->writeProvider('Modules/Ads', 'Modules\\Ads', 'Ads');
        $this->write('Modules/Ads/app/Banner.php', '<?php namespace Modules\\Ads; class Banner {}');
        $this->write('Modules/Ads/routes/web.php', "<?php Route::get('ads', function () { return 'ads'; });");

        $this->reload(['disabled' => ['Ads']]);

        $this->assertFalse(Modules::find('Ads')->isEnabled());
        $this->assertFalse($this->app->bound('Modules\\Ads.registered'));
        $this->assertFalse(class_exists('Modules\\Ads\\Banner'));
        $this->get('ads')->assertNotFound();
    }

    public function test_module_path_helpers_resolve_through_the_registry(): void
    {
        $this->directory('Modules/Shop/app');

        $this->reload();

        $root = realpath($this->base.'/Modules/Shop');
        $this->assertSame($root, module_path('Shop'));
        $this->assertSame($root.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'web.php', module_path('Shop', 'routes/web.php'));
        $this->assertSame($root.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'shop.php', module_config_path('Shop', 'shop.php'));
        $this->assertSame($root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations', module_database_path('Shop', 'migrations'));
        $this->assertSame($root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views', module_resource_path('Shop', 'views'));
        $this->assertFalse(function_exists('modules'), 'The camelCase 1.x helpers are removed.');
        $this->assertFalse(function_exists('studlyToSlug'));
        $this->assertSame(app_path('Modules'), module_path());
    }

    public function test_module_path_rejects_traversal_and_unknown_modules(): void
    {
        foreach (['..', 'Shop/../..', '/etc'] as $id) {
            try {
                module_path($id);
                $this->fail('No exception for '.$id);
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Invalid module identity', $e->getMessage());
            }
        }

        $this->expectExceptionMessage('Module [Ghost] does not exist.');
        module_path('Ghost');
    }
}
