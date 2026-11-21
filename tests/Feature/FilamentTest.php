<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Pharaonic\Laravel\Modulator\Facades\Modules;
use Pharaonic\Laravel\Modulator\Support\FilamentComponents;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class FilamentTest extends TestCase
{
    /**
     * Filament 4 and later: folders discovered, resources written by Filament's own command.
     */
    private function discovers(): bool
    {
        return FilamentComponents::discovers();
    }

    /**
     * The resource the generator writes for a module model, and its list page.
     *
     * @return array{0: string, 1: string}
     */
    private function resource(string $module, string $model): array
    {
        $plural = Str::pluralStudly($model);
        $resources = "Modules\\{$module}\\Filament\\Resources";

        // Filament 4 and later: Resources/Products/ProductResource, its pages beside it.
        if ($this->discovers()) {
            return ["{$resources}\\{$plural}\\{$model}Resource", "{$resources}\\{$plural}\\Pages\\List{$plural}"];
        }

        return ["{$resources}\\{$model}Resource", "{$resources}\\{$model}Resource\\Pages\\List{$plural}"];
    }

    /**
     * A module with a resource (and its pages), a page and a widget, written by the generator.
     */
    private function shop(string $module = 'Shop', string $model = 'Product'): void
    {
        // Filament 3 declares the view static; Filament 4 and later do not.
        $view = $this->discovers() ? 'protected string' : 'protected static string';
        $this->directory('Modules/'.$module.'/app');
        $this->write('Modules/'.$module.'/app/Models/'.$model.'.php', "<?php namespace Modules\\{$module}\\Models; class {$model} extends \\Illuminate\\Database\\Eloquent\\Model {}");
        $this->write('Modules/'.$module.'/app/Filament/Pages/Reports.php', <<<PHP
<?php

namespace Modules\\{$module}\\Filament\\Pages;

use Filament\\Pages\\Page;

class Reports extends Page
{
    {$view} \$view = 'filament-panels::pages.dashboard';
}
PHP);
        $this->write('Modules/'.$module.'/app/Filament/Widgets/Sales.php', <<<PHP
<?php

namespace Modules\\{$module}\\Filament\\Widgets;

use Filament\\Widgets\\Widget;

class Sales extends Widget
{
    {$view} \$view = 'filament-panels::widgets.account-widget';
}
PHP);
        $this->write('Modules/'.$module.'/app/Filament/App/Pages/Inbox.php', <<<PHP
<?php

namespace Modules\\{$module}\\Filament\\App\\Pages;

use Filament\\Pages\\Page;

class Inbox extends Page
{
    {$view} \$view = 'filament-panels::pages.dashboard';
}
PHP);
        $this->write('Modules/'.$module.'/app/Filament/Pages/Base.php', "<?php namespace Modules\\{$module}\\Filament\\Pages; abstract class Base extends \\Filament\\Pages\\Page {}");
        $this->reload();

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => $module, 'name' => $model]);
        $this->assertSame(0, $status, $output);
        $this->reload();
    }

    public function test_module_classes_join_the_panel_of_their_directory(): void
    {
        $this->requiresPackage('Filament\\Panel', 'filament/filament');
        $this->shop();
        [$resource, $listPage] = $this->resource('Shop', 'Product');

        $admin = Filament::getPanel('admin');
        $this->assertContains($resource, $admin->getResources());
        $this->assertContains('Modules\\Shop\\Filament\\Pages\\Reports', $admin->getPages());
        $this->assertNotContains('Modules\\Shop\\Filament\\Pages\\Base', $admin->getPages(), 'An abstract page is skipped.');
        $this->assertNotContains($listPage, $admin->getPages(), 'Resource pages are not standalone pages.');
        $this->assertContains('Modules\\Shop\\Filament\\Widgets\\Sales', $admin->getWidgets());
        $this->assertNotContains('Modules\\Shop\\Filament\\App\\Pages\\Inbox', $admin->getPages(), 'Filament/App is the "app" panel\'s.');

        $app = Filament::getPanel('app');
        $this->assertContains('Modules\\Shop\\Filament\\App\\Pages\\Inbox', $app->getPages());
        $this->assertNotContains('Modules\\Shop\\Filament\\Pages\\Reports', $app->getPages(), 'Filament/Pages is the default panel\'s.');
        $this->assertNotContains($resource, $app->getResources());

        $routes = array_map(static function ($route): string {
            return (string) $route->getName();
        }, Route::getRoutes()->getRoutes());
        $this->assertContains('filament.admin.resources.products.index', $routes, 'The resource routes exist: the panel had the resource before building them.');
        $this->assertContains('filament.admin.resources.products.edit', $routes);
        $this->assertContains('filament.admin.pages.reports', $routes);
        $this->assertContains('filament.app.pages.inbox', $routes);
    }

    public function test_a_disabled_module_registers_nothing(): void
    {
        $this->requiresPackage('Filament\\Panel', 'filament/filament');
        $this->shop();
        $this->reload(['disabled' => ['Shop']]);

        $this->assertNotContains($this->resource('Shop', 'Product')[0], Filament::getPanel('admin')->getResources());
        $this->assertNotContains('Modules\\Shop\\Filament\\App\\Pages\\Inbox', Filament::getPanel('app')->getPages());
    }

    public function test_nothing_is_registered_without_filament(): void
    {
        $this->directory('Modules/Shop/app');
        $this->reload();

        $app = new Application($this->base);
        FilamentComponents::register($app, Modules::find('Shop'), ['Modules\\Shop\\Filament\\Resources\\ProductResource']);

        $this->assertFalse($app->bound('filament'));
    }

    public function test_the_generator_writes_a_resource_for_the_module_model(): void
    {
        $this->requiresPackage('Filament\\Resources\\Resource', 'filament/filament');

        if ($this->discovers()) {
            $this->markTestSkipped('Filament 4 and later write the resource with their own command.');
        }

        $this->directory('Modules/Blog/app');
        $this->reload();

        $this->assertSame(0, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Post'])[0]);
        $resource = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/PostResource.php');
        $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\Resources;', $resource);
        $this->assertStringContainsString('use Modules\\Blog\\Models\\Post;', $resource);
        $this->assertStringContainsString('protected static ?string $model = Post::class;', $resource);
        $this->assertStringContainsString("'index' => Pages\\ListPosts::route('/'),", $resource);
        $this->assertStringContainsString('public static function getRelations(): array', $resource);
        $this->assertStringContainsString('use Filament\\Forms\\Form;', $resource, 'Filament 3 forms.');
        $this->assertStringContainsString('use Filament\\Tables\\Table;', $resource);
        $this->assertStringContainsString('Tables\\Actions\\BulkActionGroup::make([', $resource);
        $this->assertStringContainsString('protected function getHeaderActions(): array', (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/PostResource/Pages/EditPost.php'));

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

        $this->assertSame(0, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Note', '--panel' => 'app'])[0]);
        $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\App\\Resources;', (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/App/Resources/NoteResource.php'), 'The "app" panel\'s folder.');
        $this->assertSame(1, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Note', '--panel' => 'missing'])[0]);

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Missing', '--generate' => true]);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('does not exist yet', $output);
    }

    public function test_filament_4_and_later_write_the_resource_with_their_own_command(): void
    {
        $this->requiresPackage(FilamentComponents::SCHEMA, 'filament/filament 4 or later');
        $this->directory('Modules/Blog/app');
        $this->write('Modules/Blog/app/Models/Post.php', '<?php namespace Modules\\Blog\\Models; class Post extends \\Illuminate\\Database\\Eloquent\\Model {}');
        $this->reload();

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Post']);
        $this->assertSame(0, $status, $output);
        $resource = (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/Resources/Posts/PostResource.php');
        $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\Resources\\Posts;', $resource);
        $this->assertStringContainsString('use Modules\\Blog\\Models\\Post;', $resource);
        $this->assertFileExists($this->base.'/Modules/Blog/app/Filament/Resources/Posts/Pages/ListPosts.php');

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Post', '--panel' => 'app', '--simple' => true]);
        $this->assertSame(0, $status, $output);
        $this->assertStringContainsString('namespace Modules\\Blog\\Filament\\App\\Resources\\Posts;', (string) file_get_contents($this->base.'/Modules/Blog/app/Filament/App/Resources/Posts/PostResource.php'));
        $this->assertFileExists($this->base.'/Modules/Blog/app/Filament/App/Resources/Posts/Pages/ManagePosts.php');

        $this->reload();
        $this->assertContains('Modules\\Blog\\Filament\\Resources\\Posts\\PostResource', Filament::getPanel('admin')->getResources(), 'The panel discovers the module folder.');
        $this->assertContains('Modules\\Blog\\Filament\\App\\Resources\\Posts\\PostResource', Filament::getPanel('app')->getResources());

        [$status, $output] = $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Note', '--model' => 'App\\Models\\Comment']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('name it [CommentResource]', $output);
        $this->assertSame(1, $this->command('module:make:filament-resource', ['module' => 'Blog', 'name' => 'Note', '--panel' => 'missing'])[0]);
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
