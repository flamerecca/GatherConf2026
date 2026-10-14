<?php

namespace App\View\Components;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as QrCodeGenerator;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class QrCode extends Component
{
    public function __construct(public string $value, public string $label = '') {}

    public function dataUri(): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'outputBase64' => true,
            'addQuietzone' => true,
        ]);

        return (new QrCodeGenerator($options))->render($this->value);
    }

    public function render(): View
    {
        return view('components.qr-code');
    }
}
