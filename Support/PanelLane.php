<?php

namespace ScrapyardIO\Tubes\Panels\Support;

use ScrapyardIO\Tubes\Contracts\Framebuffers\DeferredFramebuffer as DeferredFramebufferContract;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Framebuffer as FramebufferContract;
use ScrapyardIO\Tubes\Contracts\Framebuffers\ManagedFramebuffer as ManagedFramebufferContract;
use ScrapyardIO\Tubes\Contracts\Panels\PanelDevice;
use ScrapyardIO\Tubes\Contracts\Rendering\ProvisionsHeadlessFramebuffer;
use ScrapyardIO\Tubes\Panels\PanelException;
use ScrapyardIO\Tubes\Panels\PendingPanel;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

/**
 * Resolve CPU vs engine PanelIC framebuffer lanes and reject cross-wiring.
 */
final class PanelLane
{
    /**
     * @return array{0: FramebufferContract, 1: 'cpu'|'engine'}
     */
    public static function resolve(PanelDevice $device, Renderer2D $renderer, PendingPanel $pending): array
    {
        $injected = $pending->framebufferInstanceValue();
        $managedDriver = $pending->framebufferDriver();
        $hasManagedRequest = ! is_null($injected) || (! is_null($managedDriver) && $managedDriver !== '');

        if ($renderer instanceof ProvisionsHeadlessFramebuffer) {
            if ($hasManagedRequest) {
                throw new PanelException(
                    'Engine PanelIC factories take renderer() only — do not pass useFramebuffer() / framebuffer(driver). '
                    .'Headless Deferred is provisioned from the engine renderer. '
                    .'Managed FB + engine renderer is invalid; CPU Managed + phpdafruit is the software lane.'
                );
            }

            $framebuffer = $renderer->provisionHeadlessFramebuffer(
                $device->width(),
                $device->height(),
            );

            if (! $framebuffer instanceof DeferredFramebufferContract) {
                throw new PanelException(
                    $renderer::class.'::provisionHeadlessFramebuffer() must return a DeferredFramebuffer.'
                );
            }

            if (! $framebuffer->isHeadless()) {
                throw new PanelException(
                    $renderer::class.' provisioned a window-attached framebuffer — PanelIC requires headless Deferred.'
                );
            }

            return [$framebuffer, 'engine'];
        }

        // CPU / software renderer lane
        if (is_null($injected) && (is_null($managedDriver) || $managedDriver === '')) {
            throw new PanelException(
                'CPU PanelIC factories require useFramebuffer($managed) or framebuffer(\'page\'|\'full\') '
                .'plus renderer($cpuRenderer). Monochrome CPU → page only; FullColor CPU → never page.'
            );
        }

        if (! is_null($injected)) {
            if (! $injected instanceof ManagedFramebufferContract) {
                throw new PanelException(
                    'CPU PanelIC useFramebuffer() accepts Managed framebuffers only (got '.$injected::class.'). '
                    .'Engine Deferred is provisioned from renderer() — do not inject it.'
                );
            }

            PreferredManagedFramebuffer::assertCompatible($device, $injected);

            return [$injected, 'cpu'];
        }

        return [
            PreferredManagedFramebuffer::for($device, $managedDriver),
            'cpu',
        ];
    }
}
