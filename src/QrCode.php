<?php

declare(strict_types=1);

namespace FwsDoctrineAuth\AppTwoFactor;

use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\SvgWriter;
use FwsDoctrineAuth\Exception\DoctrineAuthException;

use function class_exists;

/**
 * Draws the pairing QR code. Needs endroid/qr-code, which is optional, so you can use another library with
 * Pairing::$uri instead.
 */
final class QrCode
{
    /**
     * SVG markup without an XML declaration, so it can go straight into a page.
     */
    public function svg(string $uri, int $size = 256): string
    {
        if (! class_exists(EndroidQrCode::class)) {
            throw new DoctrineAuthException('Making QR codes needs endroid/qr-code: composer require endroid/qr-code');
        }

        return (new SvgWriter())
            ->write(new EndroidQrCode(data: $uri, size: $size, margin: 0), options: [
                SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true,
            ])
            ->getString();
    }
}
