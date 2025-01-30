<?php

namespace ControleOnline\Service\Gateways;

use ControleOnline\Entity\Invoice;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

class BitcoinService
{
    public function getBitcoin(Invoice $invoice): array
    {
        $walletAddress = 'BC1QQDMP6P903LNVLQRJ7QWMSGWX7D8YLTQURZDMPL';
        $amount = $invoice->getPrice();
        $payload = "bitcoin:{$walletAddress}?amount={$amount}";

        $qrCode = QrCode::create($payload)
            ->setEncoding(new Encoding('UTF-8'))
            ->setSize(300)
            ->setMargin(10)
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::High);
        
        $writer = new PngWriter();
        $qrCodeResult = $writer->write($qrCode);

        $encodedImage = base64_encode($qrCodeResult->getString());

        return [
            'payload' => $payload,
            'encodedImage' => $encodedImage,
        ];
    }
}
