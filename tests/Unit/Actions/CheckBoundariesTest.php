<?php

namespace Pharaonic\Laravel\Modulator\Tests\Unit\Actions;

use Pharaonic\Laravel\Modulator\Actions\CheckBoundaries;
use Pharaonic\Laravel\Modulator\Tests\Unit\FilesystemTestCase;

class CheckBoundariesTest extends FilesystemTestCase
{
    public function test_it_collects_imports_and_qualified_names(): void
    {
        $file = $this->write('a.php', <<<'PHP'
<?php

namespace Modules\Shop\Http;

use Modules\Catalog\Models\Product;
use Modules\Billing\{Invoice, Contracts\Payable as Pay};
use function Modules\Helpers\format;
use const Modules\Helpers\VERSION;

class Controller
{
    use \Modules\Shared\Concerns\Loggable;

    public function show()
    {
        $callback = function () use ($x) {
            return new \Modules\Reports\Report;
        };

        return Models\Cart::query() && Pay\Thing::class;
    }
}
PHP);

        $references = array_keys((new CheckBoundaries)->references($file));
        sort($references);

        $this->assertSame([
            'Modules\\Billing\\Contracts\\Payable',
            'Modules\\Billing\\Contracts\\Payable\\Thing',
            'Modules\\Billing\\Invoice',
            'Modules\\Catalog\\Models\\Product',
            'Modules\\Reports\\Report',
            'Modules\\Shared\\Concerns\\Loggable',
            'Modules\\Shop\\Http\\Models\\Cart',
        ], $references);
    }

    public function test_closure_use_at_the_top_level_is_not_an_import(): void
    {
        $file = $this->write('routes.php', <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

$name = 'x';

Route::get('/', function () use ($name) {
    return \Modules\Catalog\Models\Product::all();
});
PHP);

        $references = (new CheckBoundaries)->references($file);

        $this->assertArrayHasKey('Modules\\Catalog\\Models\\Product', $references);
        $this->assertSame(8, $references['Modules\\Catalog\\Models\\Product']);
    }
}
