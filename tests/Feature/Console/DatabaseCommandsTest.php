<?php

namespace Pharaonic\Laravel\Modulator\Tests\Feature\Console;

use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Modulator\Tests\TestCase;

class DatabaseCommandsTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration('Modules/Shop/database/migrations', '2020_01_01_000000', 'create_shop_products_table', 'shop_products');
        $this->migration('Modules/Blog/database/migrations', '2020_01_01_000001', 'create_blog_posts_table', 'blog_posts');
        $this->migration('database/migrations', '2020_01_01_000002', 'create_app_settings_table', 'app_settings');
        $this->directory('Modules/Shop/app');
        $this->directory('Modules/Blog/app');
        $this->write('Modules/Shop/database/seeders/DatabaseSeeder.php', <<<'PHP'
<?php

namespace Modules\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_products')->insert(['name' => 'seeded']);
    }
}
PHP);

        $this->reload();
    }

    /**
     * Laravel 6 migrations are named classes: every test gets its own names.
     */
    private function migration(string $directory, string $date, string $name, string $table): void
    {
        $name .= '_'.strtolower((string) preg_replace('/[^A-Za-z]/', '', $this->name()));
        $class = \Illuminate\Support\Str::studly($name);

        $this->write($directory.'/'.$date.'_'.$name.'.php', <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

class {$class} extends Migration
{
    public function up()
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->increments('id');
            \$table->string('name')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('{$table}');
    }
}
PHP);
    }

    public function test_module_migrations_never_touch_other_tables(): void
    {
        $this->assertSame(0, $this->command('module:migrate', ['module' => 'Shop'])[0]);
        $this->assertTrue(Schema::hasTable('shop_products'));
        $this->assertFalse(Schema::hasTable('blog_posts'));
        $this->assertFalse(Schema::hasTable('app_settings'));

        // The application and Blog migrate together: one shared batch.
        $this->command('migrate', ['--path' => [$this->base.'/database/migrations', $this->base.'/Modules/Blog/database/migrations'], '--realpath' => true]);
        $this->assertTrue(Schema::hasTable('app_settings'));

        $this->assertSame(0, $this->command('module:migrate:rollback', ['module' => 'Blog'])[0]);
        $this->assertFalse(Schema::hasTable('blog_posts'));
        $this->assertTrue(Schema::hasTable('app_settings'), 'Rolling back a module keeps the other migrations of its batch.');
        $this->assertTrue(Schema::hasTable('shop_products'));

        [$status, $output] = $this->command('module:migrate:status', ['module' => 'Shop']);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('create_shop_products_table', $output);
        $this->assertStringNotContainsString('create_app_settings_table', $output);

        $this->assertSame(0, $this->command('module:migrate:reset', ['module' => 'Shop'])[0]);
        $this->assertFalse(Schema::hasTable('shop_products'));
        $this->assertTrue(Schema::hasTable('app_settings'));
    }

    public function test_refresh_reruns_and_seeds_only_the_module(): void
    {
        $this->command('module:migrate', ['module' => 'Shop']);
        $this->command('module:migrate', ['module' => 'Blog']);

        [$status, $output] = $this->command('module:migrate:refresh', ['module' => 'Shop', '--seed' => true]);

        $this->assertSame(0, $status, $output);
        $this->assertSame(['seeded'], \DB::table('shop_products')->pluck('name')->all());
        $this->assertTrue(Schema::hasTable('blog_posts'));
    }

    public function test_seeder_names_the_seeder_and_implies_seeding(): void
    {
        $this->write('Modules/Shop/database/seeders/DemoSeeder.php', <<<'PHP'
<?php

namespace Modules\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('shop_products')->insert(['name' => 'demo']);
    }
}
PHP);
        $this->reload();
        $this->command('module:migrate', ['module' => 'Shop']);

        [$status, $output] = $this->command('module:migrate:refresh', ['module' => 'Shop', '--seeder' => 'DemoSeeder']);

        $this->assertSame(0, $status, $output);
        $this->assertSame(['demo'], \DB::table('shop_products')->pluck('name')->all());

        [$status, $output] = $this->command('module:migrate:refresh', ['module' => 'Shop', '--seeder' => 'MissingSeeder']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('does not exist', $output);
    }

    public function test_fresh_is_refused_and_drops_nothing(): void
    {
        $this->command('module:migrate', ['module' => 'Shop']);

        [$status, $output] = $this->command('module:migrate:fresh', ['module' => 'Shop', '--force' => true]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('drops every table', $output);
        $this->assertTrue(Schema::hasTable('shop_products'));
    }

    public function test_seed_runs_the_module_seeder(): void
    {
        $this->command('module:migrate', ['module' => 'Shop']);

        $this->assertSame(0, $this->command('module:seed', ['module' => 'Shop'])[0]);
        $this->assertSame(1, \DB::table('shop_products')->count());

        [$status, $output] = $this->command('module:seed', ['module' => 'Shop', '--class' => 'MissingSeeder']);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('Modules\\Shop\\Database\\Seeders\\MissingSeeder', $output);
    }

    public function test_a_module_is_required_in_automation(): void
    {
        [$status, $output] = $this->command('module:migrate');

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Give the module identity', $output);
    }
}
