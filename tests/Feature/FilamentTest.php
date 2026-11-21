<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\FilamentComponents;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class FilamentTest extends TestCase
{
    /**
     * A module with a resource (and its pages), a page and a widget, written by the generator.
     */
    private function shop(string $module = 'Shop', string $model = 'Product'): void
    {
        $this->directory('Modules/'.$module.'/app');
        $this->write('Modules/'.$module.'/app/Models/'.$model.'.php', "<?php namespace Modules\\{$module}\\Models; class {$model} extends \\Illuminate\\Database\\Eloquent\\Model {}");
        $this->write('Modules/'.$module.'/app/Filament/Pages/Reports.php', <<<PHP
<?php

namespace Modules\\{$module}\\Filament\\Pages;

use Filament\\Pages\\Page;

class Reports extends Page
{
    protected static string \$view = 'filament::pages.dashboard';
}
PHP);
        $this->write('Modules/'.$module.'/app/Filament/Widgets/Sales.php', <<<PHP
<?php

namespace Modules\\{$module}\\Filament\\Widgets;

use Filament\\Widgets\\Widget;

class Sales extends Widget
{
    protected static string \$view = 'filament::widgets.account-widget';
}
PHP);
        $this->write('Modules/'.$module.'/app/Filament/Pages/Base.php', "<?php namespace Modules\\{$module}\\Filament\\Pages; abstract class Base extends \\Filament\\Pages\\Page {}");
        $this->reload();

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => $module, 'name' => $model]);
        $this->assertSame(0, $status, $output);
        $this->reload();
    }

    public function test_module_resources_pages_and_widgets_are_registered(): void
    {
        $this->requiresPackage('Filament\\Resources\\Resource', 'filament/filament');
        $this->shop();

        $this->assertContains('Modules\\Shop\\Filament\\Resources\\ProductResource', Filament::getResources());
        $this->assertContains('Modules\\Shop\\Filament\\Pages\\Reports', Filament::getPages());
        $this->assertNotContains('Modules\\Shop\\Filament\\Pages\\Base', Filament::getPages(), 'An abstract page is skipped.');
        $this->assertNotContains('Modules\\Shop\\Filament\\Resources\\ProductResource\\Pages\\ListProducts', Filament::getPages(), 'Resource pages are not standalone pages.');
        $this->assertContains('Modules\\Shop\\Filament\\Widgets\\Sales', Filament::getWidgets());

        $aliases = app('livewire')->getComponentAliases();
        $this->assertContains('Modules\\Shop\\Filament\\Resources\\ProductResource\\Pages\\ListProducts', $aliases);
        $this->assertContains('Modules\\Shop\\Filament\\Resources\\ProductResource\\Pages\\EditProduct', $aliases);
        $this->assertContains('Modules\\Shop\\Filament\\Pages\\Reports', $aliases);
        $this->assertContains('Modules\\Shop\\Filament\\Widgets\\Sales', $aliases);

        $routes = array_map(static function ($route): string {
            return (string) $route->getName();
        }, Route::getRoutes()->getRoutes());
        $this->assertContains('filament.resources.products.index', $routes, 'The resource routes exist: Filament saw the resource before building them.');
        $this->assertContains('filament.resources.products.edit', $routes);
    }

    public function test_a_disabled_module_registers_nothing(): void
    {
        $this->requiresPackage('Filament\\Resources\\Resource', 'filament/filament');
        $this->shop();
        $this->reload(['disabled' => ['Shop']]);

        $this->assertNotContains('Modules\\Shop\\Filament\\Resources\\ProductResource', Filament::getResources());
        $this->assertNotContains('Modules\\Shop\\Filament\\Pages\\Reports', Filament::getPages());
    }

    public function test_nothing_is_registered_without_filament(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $app = new Application($this->base);
        FilamentComponents::register($app, Modules::find('Shop'), ['Modules\\Shop\\Filament\\Resources\\ProductResource']);
        FilamentComponents::boot($app, Modules::find('Shop'), ['Modules\\Shop\\Filament\\Resources\\ProductResource']);

        $this->assertFalse($app->bound('filament'));
    }

    public function test_the_generator_writes_a_resource_for_the_module_model(): void
    {
        $this->requiresPackage('Filament\\Resources\\Resource', 'filament/filament');
        $this->directory('Modules/Blog/app');
        $this->reload();

        $this->assertSame(0, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Post'])[0]);
        $resource = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/PostResource.php');
        $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\Resources;', $resource);
        $this->assertStringContainsString('use Modules\\Blog\\Models\\Post;', $resource);
        $this->assertStringContainsString('protected static ?string $model = Post::class;', $resource);
        $this->assertStringContainsString("'index' => Pages\\ListPosts::route('/'),", $resource);
        $this->assertStringContainsString('public static function getRelations(): array', $resource);

        foreach (['ListPosts', 'CreatePost', 'EditPost'] as $page) {
            $contents = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/PostResource/Pages/'.$page.'.php');
            $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\Resources\\PostResource\\Pages;', $contents);
            $this->assertStringContainsString('use Modules\\Blog\\Filament\\Resources\\PostResource;', $contents);
            token_get_all($contents, TOKEN_PARSE);
        }

        $this->assertSame(0, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Admin/TagResource', '--simple' => true, '--soft-deletes' => true])[0]);
        $tags = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/Admin/TagResource.php');
        $this->assertStringContainsString('use Modules\\Blog\\Models\\Admin\\Tag;', $tags, 'Admin/Tag is the module model Models\\Admin\\Tag, as in Filament.');
        $this->assertStringContainsString('Pages\\ManageTags::route', $tags);
        $this->assertStringContainsString('Tables\\Filters\\TrashedFilter::make()', $tags);
        $this->assertStringContainsString('SoftDeletingScope::class', $tags);
        $this->assertStringNotContainsString('getRelations', $tags);
        $this->assertFileExists($this->base.'/Modules/Blog/app/Filament/Resources/Admin/TagResource/Pages/ManageTags.php');
        token_get_all($tags, TOKEN_PARSE);

        $this->assertSame(0, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Comment', '--view' => true, '--model' => 'App\\Models\\Comment'])[0]);
        $comments = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/CommentResource.php');
        $this->assertStringContainsString('use App\\Models\\Comment;', $comments);
        $this->assertStringContainsString("'view' => Pages\\ViewComment::route('/{record}'),", $comments);
        $this->assertFileExists($this->base.'/Modules/Blog/app/Filament/Resources/CommentResource/Pages/ViewComment.php');

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Missing', '--generate' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('does not exist yet', $output);
    }

    public function test_without_filament_the_generator_refuses_and_writes_nothing(): void
    {
        $this->withoutPackage('Filament\\Resources\\Resource', 'filament/filament');
        $this->directory('Modules/Blog/app');
        $this->reload();

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Post']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('composer require filament/filament', $output);
        $this->assertFileDoesNotExist($this->base.'/Modules/Blog/app/Filament/Resources/PostResource.php');
    }
}
