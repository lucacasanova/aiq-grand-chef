<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\CategoriaRepositoryInterface;
use App\Repositories\CategoriaRepository;
use App\Services\CategoriaServiceInterface;
use App\Services\CategoriaService;
use App\Repositories\PedidoRepositoryInterface;
use App\Repositories\PedidoRepository;
use App\Services\PedidoServiceInterface;
use App\Services\PedidoService;
use App\Repositories\ProdutoRepositoryInterface;
use App\Repositories\ProdutoRepository;
use App\Services\ProdutoServiceInterface;
use App\Services\ProdutoService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CategoriaRepositoryInterface::class, CategoriaRepository::class);
        $this->app->bind(CategoriaServiceInterface::class, CategoriaService::class);
        $this->app->bind(PedidoRepositoryInterface::class, PedidoRepository::class);
        $this->app->bind(PedidoServiceInterface::class, PedidoService::class);
        $this->app->bind(ProdutoRepositoryInterface::class, ProdutoRepository::class);
        $this->app->bind(ProdutoServiceInterface::class, ProdutoService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
