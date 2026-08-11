<?php

namespace ScrapyardIO\Tubes\Panels;

use GeneralPurposeIO\Core\MagicAliases\Circuit;
use ScrapyardIO\Tubes\Canvas\PanelIC;
use ScrapyardIO\Tubes\Contracts\Panels\FullColorDisplay as FullColorDisplayContract;
use ScrapyardIO\Tubes\Contracts\Panels\MonochromeDisplay as MonochromeDisplayContract;
use ScrapyardIO\Tubes\Contracts\Panels\PanelDevice;
use ScrapyardIO\Tubes\Contracts\Panels\PanelFactory;
use ScrapyardIO\Tubes\Contracts\Rendering\ProvisionsHeadlessFramebuffer;
use ScrapyardIO\Tubes\Core\Support\CanvasProfiles;
use ScrapyardIO\Tubes\Panels\Support\PanelLane;
use ScrapyardIO\Tubes\Rendering\Renderer2D;
use Throwable;

class PanelManager implements PanelFactory
{
    public function driver(?string $driver = null): PendingPanel
    {
        $normalized = is_null($driver) || $driver === ''
            ? null
            : strtolower($driver);

        return new PendingPanel($this, $normalized);
    }

    public function make(?string $driver = null): PendingPanel
    {
        return $this->driver($driver);
    }

    public function profile(string $name): PanelIC
    {
        try {
            $definition = CanvasProfiles::panel($name);
        } catch (Throwable $exception) {
            throw new PanelException($exception->getMessage(), previous: $exception);
        }

        $circuit = $definition['circuit'] ?? null;
        if (! is_string($circuit) || $circuit === '') {
            throw new PanelException(
                "Panel profile [{$name}] must define a non-empty circuit key (circuits.php profile name)."
            );
        }

        $rendererClass = $definition['renderer'] ?? null;
        if (! is_string($rendererClass) || $rendererClass === '') {
            throw new PanelException(
                "Panel profile [{$name}] must define a renderer class-string (tubes Renderer2D companion)."
            );
        }

        if (! class_exists($rendererClass)) {
            throw new PanelException(
                "Panel profile [{$name}] renderer [{$rendererClass}] is not loadable."
            );
        }

        $renderer = new $rendererClass;
        if (! $renderer instanceof Renderer2D) {
            throw new PanelException(
                "Panel profile [{$name}] renderer [{$rendererClass}] must extend ".Renderer2D::class.'.'
            );
        }

        $framebuffer = $definition['framebuffer'] ?? null;
        if (! is_null($framebuffer) && (! is_string($framebuffer) || $framebuffer === '')) {
            throw new PanelException(
                "Panel profile [{$name}] framebuffer must be a non-empty managed driver string when set (page|full|dirty)."
            );
        }

        if ($renderer instanceof ProvisionsHeadlessFramebuffer && is_string($framebuffer)) {
            throw new PanelException(
                "Panel profile [{$name}] uses an engine renderer — omit framebuffer (headless Deferred is provisioned)."
            );
        }

        if (! $renderer instanceof ProvisionsHeadlessFramebuffer && ! is_string($framebuffer)) {
            throw new PanelException(
                "Panel profile [{$name}] CPU renderer requires framebuffer: 'page'|'full'|'dirty' (Managed) "
                .'in addition to renderer.'
            );
        }

        $pending = $this->driver(is_string($framebuffer) ? $framebuffer : null)
            ->circuit($circuit)
            ->renderer($renderer);

        return $pending->create();
    }

    public function wrap(PanelDevice $ic, Renderer2D $renderer, ?string $framebufferDriver = null): PanelIC
    {
        return $this->driver($framebufferDriver)->wrap($ic)->renderer($renderer)->create();
    }

    public function createFromPending(PendingPanel $pending): PanelIC
    {
        $device = $pending->deviceValue();

        if (is_null($device)) {
            $circuitProfile = $pending->circuitProfileValue();
            if (is_null($circuitProfile) || $circuitProfile === '') {
                throw new PanelException(
                    'Panel build requires wrap($ic) or circuit($circuitsProfile).'
                );
            }
            $device = $this->resolveCircuitProfile($circuitProfile);
        }

        $renderer = $pending->rendererValue();
        if (is_null($renderer)) {
            throw new PanelException(
                'Panel build requires renderer($renderer2D). '
                .'CPU: useFramebuffer($managed) + phpdafruit (or peer). '
                .'Engine: renderer($metalRenderer) only — headless FB is assumed.'
            );
        }

        [$framebuffer] = PanelLane::resolve($device, $renderer, $pending);

        if ($device instanceof MonochromeDisplayContract) {
            return new MonochromePanel($device, $renderer, $framebuffer);
        }

        if ($device instanceof FullColorDisplayContract) {
            return new FullColorPanel($device, $renderer, $framebuffer);
        }

        throw new PanelException(
            'Panel IC ['.$device::class.'] must implement '
            .MonochromeDisplayContract::class.' or '.FullColorDisplayContract::class.'.'
        );
    }

    protected function resolveCircuitProfile(string $profile): PanelDevice
    {
        if (! class_exists(Circuit::class)) {
            throw new PanelException(
                'Circuit MagicAlias is unavailable. Require scrapyard-io/gpio-framework to resolve panel circuit profiles.'
            );
        }

        try {
            $ic = Circuit::profile($profile);
        } catch (Throwable $exception) {
            throw new PanelException(
                "Unable to resolve circuit profile [{$profile}]: ".$exception->getMessage(),
                previous: $exception,
            );
        }

        if (! $ic instanceof PanelDevice) {
            throw new PanelException(
                "Circuit profile [{$profile}] resolved ".$ic::class
                .' which does not implement '.PanelDevice::class.'.'
            );
        }

        return $ic;
    }
}
