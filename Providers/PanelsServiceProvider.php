<?php

namespace ScrapyardIO\Tubes\Panels\Providers;

use Fabricate\NutsAndBolts\Contracts\DeferrableProvider;
use Fabricate\NutsAndBolts\ServiceProvider;
use ScrapyardIO\Tubes\Contracts\Panels\PanelFactory;
use ScrapyardIO\Tubes\Panels\PanelManager;

class PanelsServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->container->singleton('panel', fn () => new PanelManager);

        $this->container->singleton(PanelManager::class, fn ($app) => $app->make('panel'));
        $this->container->singleton(PanelFactory::class, fn ($app) => $app->make('panel'));
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            'panel',
            PanelManager::class,
            PanelFactory::class,
        ];
    }
}
