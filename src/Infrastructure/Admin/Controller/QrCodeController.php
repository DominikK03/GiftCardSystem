<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPPORT')]
class QrCodeController extends AbstractController
{
    #[Route('/admin/giftcards/{id}/qr', name: 'admin_giftcard_qr', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $id,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 300,
            margin: 10,
        ))->build();

        return new Response($result->getString(), 200, [
            'Content-Type' => $result->getMimeType(),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
