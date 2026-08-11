<?php

namespace ScrapyardIO\Tubes\Panels;

use ScrapyardIO\Tubes\Canvas\PanelIC;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Framebuffer as FramebufferContract;
use ScrapyardIO\Tubes\Contracts\Panels\FullColorDisplay as FullColorDisplayContract;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

/**
 * Tubes canvas wrapping a full-color {@see FullColorDisplayContract} IC.
 */
class FullColorPanel extends PanelIC
{
    public function __construct(
        FullColorDisplayContract $device,
        Renderer2D $renderer,
        FramebufferContract $framebuffer,
    ) {
        parent::__construct($device, $framebuffer, $renderer);
    }

    public function device(): FullColorDisplayContract
    {
        /** @var FullColorDisplayContract $device */
        $device = parent::device();

        return $device;
    }
}
