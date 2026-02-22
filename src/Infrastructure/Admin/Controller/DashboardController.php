<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Controller;

use App\Application\Admin\DashboardDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardDataProvider $dataProvider
    ) {}

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => $this->dataProvider->getStatistics(),
        ]);
    }
}
