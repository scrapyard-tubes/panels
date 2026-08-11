<?php

namespace ScrapyardIO\Tubes\Panels\Support;

use ScrapyardIO\Tubes\Contracts\Core\SupportsPartialRefresh;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\FramebufferDriver;
use ScrapyardIO\Tubes\Contracts\Framebuffers\Enums\PixelFormat;
use ScrapyardIO\Tubes\Contracts\Framebuffers\FormatSpec;
use ScrapyardIO\Tubes\Contracts\Framebuffers\ManagedFramebuffer as ManagedFramebufferContract;
use ScrapyardIO\Tubes\Contracts\Panels\FullColorDisplay as FullColorDisplayContract;
use ScrapyardIO\Tubes\Contracts\Panels\MonochromeDisplay as MonochromeDisplayContract;
use ScrapyardIO\Tubes\Contracts\Panels\PanelDevice;
use ScrapyardIO\Tubes\Framebuffers\DirtyRegionsBuffer;
use ScrapyardIO\Tubes\Framebuffers\FullFramebuffer;
use ScrapyardIO\Tubes\Framebuffers\PageSegmentBuffer;
use ScrapyardIO\Tubes\Panels\PanelException;

/**
 * Pick a CPU-managed framebuffer for a panel IC from contract + FormatSpec (or an override).
 *
 * Monochrome CPU lane → page buffers only (IC FormatSpec host).
 * Full-color CPU lane → never page; prefer dirty when {@see SupportsPartialRefresh};
 * host FormatSpec **matches the IC** (no present-time transcode). Draw colours may still
 * be 0xRRGGBBAA — PixelStore packs into the host bit depth on write.
 */
final class PreferredManagedFramebuffer
{
    public static function for(PanelDevice $ic, ?string $driver = null): ManagedFramebufferContract
    {
        $hostSpec = self::hostFormatSpecFor($ic);
        $width = $ic->width();
        $height = $ic->height();

        if ($width < 1 || $height < 1) {
            throw new PanelException('Panel IC width/height must be >= 1.');
        }

        $resolved = is_null($driver) || $driver === ''
            ? self::defaultDriverFor($ic)
            : strtolower($driver);

        self::assertDriverAllowed($ic, $resolved);

        $buffer = match ($resolved) {
            FramebufferDriver::PAGE->value, 'page_segment', 'pagesegment' => PageSegmentBuffer::sized(
                $width,
                $height,
                $hostSpec,
            ),
            FramebufferDriver::FULL->value, 'full_framebuffer', 'fullframebuffer' => FullFramebuffer::sized(
                $width,
                $height,
                $hostSpec,
            ),
            FramebufferDriver::DIRTY->value, 'dirty_regions', 'dirtyregions' => DirtyRegionsBuffer::sized(
                $width,
                $height,
                $hostSpec,
            ),
            default => throw new PanelException(
                "Unsupported panel framebuffer driver [{$resolved}]."
            ),
        };

        self::assertCompatible($ic, $buffer);

        return $buffer;
    }

    /**
     * Host store FormatSpec used while drawing into a CPU PanelIC — always the IC's.
     */
    public static function hostFormatSpecFor(PanelDevice $ic): FormatSpec
    {
        return $ic->formatSpec();
    }

    public static function defaultDriverFor(PanelDevice|FormatSpec $icOrSpec, ?FormatSpec $spec = null): string
    {
        if ($icOrSpec instanceof PanelDevice) {
            if ($icOrSpec instanceof MonochromeDisplayContract) {
                return FramebufferDriver::PAGE->value;
            }

            if ($icOrSpec instanceof FullColorDisplayContract) {
                // Partial-capable color ICs default to dirty rects; others stay dirty too
                // (still cheaper than forcing FULL every frame when damage is sparse).
                return FramebufferDriver::DIRTY->value;
            }

            if ($icOrSpec instanceof SupportsPartialRefresh) {
                $spec = $icOrSpec->formatSpec();

                return match ($spec->pixel_format) {
                    PixelFormat::MONO_VERTICAL_PAGE => FramebufferDriver::PAGE->value,
                    default => FramebufferDriver::DIRTY->value,
                };
            }

            $spec = $icOrSpec->formatSpec();
        } else {
            $spec = $icOrSpec;
        }

        return match ($spec->pixel_format) {
            PixelFormat::MONO_VERTICAL_PAGE => FramebufferDriver::PAGE->value,
            PixelFormat::ROW_MAJOR => FramebufferDriver::DIRTY->value,
            PixelFormat::MONO_HORIZONTAL,
            PixelFormat::PLANAR => FramebufferDriver::FULL->value,
        };
    }

    public static function assertCompatible(PanelDevice $ic, ManagedFramebufferContract $framebuffer): void
    {
        $isPage = $framebuffer instanceof PageSegmentBuffer;

        if ($ic instanceof MonochromeDisplayContract && ! $isPage) {
            throw new PanelException(
                'MonochromeDisplay CPU rendering requires a page Managed framebuffer (PageSegmentBuffer), got '
                .$framebuffer::class.'.'
            );
        }

        if ($ic instanceof FullColorDisplayContract && $isPage) {
            throw new PanelException(
                'FullColorDisplay CPU rendering does not allow page buffers (got PageSegmentBuffer). '
                .'Use dirty or full (non-page Managed buffer).'
            );
        }
    }

    public static function assertDriverAllowed(PanelDevice $ic, string $driver): void
    {
        $isPage = self::isPageDriver($driver);

        if ($ic instanceof MonochromeDisplayContract && ! $isPage) {
            throw new PanelException(
                "MonochromeDisplay CPU framebuffer driver must be page (got [{$driver}])."
            );
        }

        if ($ic instanceof FullColorDisplayContract && $isPage) {
            throw new PanelException(
                "FullColorDisplay CPU framebuffer driver cannot be page (got [{$driver}])."
            );
        }
    }

    public static function isPageDriver(string $driver): bool
    {
        return match (strtolower($driver)) {
            FramebufferDriver::PAGE->value, 'page_segment', 'pagesegment' => true,
            default => false,
        };
    }
}
