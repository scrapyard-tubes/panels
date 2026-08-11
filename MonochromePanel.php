<?php

namespace ScrapyardIO\Tubes\Panels;

use ScrapyardIO\Tubes\Canvas\PanelIC;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Framebuffer as FramebufferContract;
use ScrapyardIO\Tubes\Contracts\Panels\MonochromeDisplay as MonochromeDisplayContract;
use ScrapyardIO\Tubes\Rendering\Renderer2D;

/**
 * Tubes canvas wrapping a monochrome {@see MonochromeDisplayContract} IC.
 */
class MonochromePanel extends PanelIC
{
    public function __construct(
        MonochromeDisplayContract $device,
        Renderer2D $renderer,
        FramebufferContract $framebuffer,
    ) {
        parent::__construct($device, $framebuffer, $renderer);
    }

    public function device(): MonochromeDisplayContract
    {
        /** @var MonochromeDisplayContract $device */
        $device = parent::device();

        return $device;
    }
}
