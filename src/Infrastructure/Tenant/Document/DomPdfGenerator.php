<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenant\Document;

use App\Application\Tenant\Port\PdfGeneratorInterface;
use App\Domain\Tenant\Entity\Tenant;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class DomPdfGenerator implements PdfGeneratorInterface
{
    public function __construct(
        private readonly Environment $twig
    ) {
    }

    public function generateCooperationAgreement(Tenant $tenant): string
    {
        $html = $this->twig->render('document/cooperation_agreement.html.twig', [
            'tenant' => $tenant,
            'date' => new \DateTimeImmutable(),
        ]);

        return $this->renderPdf($html);
    }

    public function generateInvoice(
        Tenant $tenant,
        string $invoiceNumber,
        array  $items,
        string $currency,
        int    $vatRate
    ): string {
        $nettoTotal = 0;
        $calculatedItems = [];

        foreach ($items as $item) {
            $itemNetto = $item['quantity'] * $item['unitPriceAmount'];
            $itemVat = (int) round($itemNetto * $vatRate / 100);
            $itemBrutto = $itemNetto + $itemVat;

            $calculatedItems[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unitPriceAmount' => $item['unitPriceAmount'],
                'netto' => $itemNetto,
                'vatAmount' => $itemVat,
                'brutto' => $itemBrutto,
            ];

            $nettoTotal += $itemNetto;
        }

        $vatTotal = (int) round($nettoTotal * $vatRate / 100);
        $bruttoTotal = $nettoTotal + $vatTotal;

        $html = $this->twig->render('document/invoice.html.twig', [
            'tenant' => $tenant,
            'invoiceNumber' => $invoiceNumber,
            'date' => new \DateTimeImmutable(),
            'items' => $calculatedItems,
            'currency' => $currency,
            'vatRate' => $vatRate,
            'nettoTotal' => $nettoTotal,
            'vatTotal' => $vatTotal,
            'bruttoTotal' => $bruttoTotal,
        ]);

        return $this->renderPdf($html);
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
