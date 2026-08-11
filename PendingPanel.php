<?php

namespace ScrapyardIO\Tubes\Panels;

use ScrapyardIO\Tubes\Canvas\PanelIC;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Framebuffer as FramebufferContract;
use ScrapyardIO\Tubes\Contracts\Framebuffers\ManagedFramebuffer as ManagedFramebufferContract;
use ScrapyardIO\Tubes\Contracts\Panels\PanelDevice;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

/**
 * Fluent builder for a PanelIC wrap.
 *
 * CPU:
 *   Panel::make()->wrap($ic)->useFramebuffer($pages)->renderer($phpdafruit)->create()
 *   Panel::make()->wrap($ic)->framebuffer('page')->renderer($phpdafruit)->create()
 *
 * Engine (headless FB assumed from renderer):
 *   Panel::make()->wrap($ic)->renderer($metalRenderer)->create()
 */
class PendingPanel
{
    protected ?PanelDevice $device = null;

    protected ?string $circuitProfile = null;

    protected ?FramebufferContract $framebufferInstance = null;

    protected ?Renderer2D $renderer = null;

    /**
     * @param  non-empty-string|null  $framebufferDriver  Managed driver override (page/full)
     */
    public function __construct(
        protected PanelManager $manager,
        protected ?string $framebufferDriver = null,
    ) {}

    public function framebufferDriver(): ?string
    {
        return $this->framebufferDriver;
    }

    /**
     * Override preferred managed framebuffer driver (`page`, `full`, …).
     * Cleared when {@see useFramebuffer()} injects a live instance.
     */
    public function framebuffer(?string $driver): static
    {
        $this->framebufferDriver = is_null($driver) || $driver === ''
            ? null
            : strtolower($driver);
        $this->framebufferInstance = null;

        return $this;
    }

    /**
     * Inject a Managed software framebuffer (CPU lane only).
     * Engine Deferred is never injected — provisioned from renderer().
     */
    public function useFramebuffer(FramebufferContract $framebuffer): static
    {
        if (! $framebuffer instanceof ManagedFramebufferContract) {
            throw new PanelException(
                'useFramebuffer() is for CPU Managed buffers only (got '.$framebuffer::class.'). '
                .'For engine PanelIC pass renderer($engineRenderer) alone.'
            );
        }

        $this->framebufferInstance = $framebuffer;
        $this->framebufferDriver = null;

        return $this;
    }

    /**
     * Bind the drawing engine for this panel (CPU companion or engine Renderer2D).
     */
    public function renderer(Renderer2D $renderer): static
    {
        $this->renderer = $renderer;

        return $this;
    }

    /**
     * Bind a live panel device (already provisioned Circuit IC).
     */
    public function wrap(PanelDevice $device): static
    {
        $this->device = $device;
        $this->circuitProfile = null;

        return $this;
    }

    /**
     * Resolve the device via Circuit::profile($name) at create-time.
     */
    public function circuit(string $profile): static
    {
        $this->circuitProfile = $profile;
        $this->device = null;

        return $this;
    }

    public function create(): PanelIC
    {
        return $this->manager->createFromPending($this);
    }

    /**
     * Alias for {@see create()}.
     */
    public function get(): PanelIC
    {
        return $this->create();
    }

    public function deviceValue(): ?PanelDevice
    {
        return $this->device;
    }

    public function circuitProfileValue(): ?string
    {
        return $this->circuitProfile;
    }

    public function framebufferInstanceValue(): ?FramebufferContract
    {
        return $this->framebufferInstance;
    }

    public function rendererValue(): ?Renderer2D
    {
        return $this->renderer;
    }
}
