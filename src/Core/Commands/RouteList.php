<?php

namespace Pharaonic\Laravel\Modulator\Core\Commands;

use Illuminate\Support\Facades\Artisan;
use Pharaonic\Laravel\Modulator\Core\Command;

class RouteList extends Command
{
    protected $description  = 'Get routes list of a specific module.';
    protected $signature    = 'module:routes {module}
                                {--c|compact : Only show method, URI and action columns}
                                {--r|reverse : Reverse the ordering of the routes}
                                {--json : Output the route list as JSON}
                                {--columns= : Columns to include in the route table (multiple values allowed)}
                                {--method= : Filter the routes by method}
                                {--sort= : The column (precedence, domain, method, uri, name, action, middleware) to sort by [default: "uri"]}';


    public function exec()
    {
        $command = "route:list --name=" . $this->slug;

        if ($this->option('compact')) $command .= ' --compact';
        if ($this->option('reverse')) $command .= ' --reverse';
        if ($this->option('json')) $command .= ' --json';
        if ($columns = $this->option('columns')) $command .= ' --columns=' . $columns;
        if ($method = $this->option('method')) $command .= ' --method=' . $method;
        if ($sort = $this->option('sort')) $command .= ' --sort=' . $sort;

        return Artisan::call($command, [], $this->getOutput());
    }
}
