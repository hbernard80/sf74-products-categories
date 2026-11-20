<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /**
     * Affiche le tableau d'accueil avec les derniers produits et catégories créés.
     *
     * @param ProductRepository $productRepository Repository des produits.
     * @param CategoryRepository $categoryRepository Repository des catégories.
     *
     * @return Response Réponse HTML de la page d'accueil.
     */
    #[Route('/', name: 'app_home')]
    public function index(ProductRepository $productRepository, CategoryRepository $categoryRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'latest_products' => $productRepository->findLatestCreated(),
            'latest_categories' => $categoryRepository->findLatestCreated(),
        ]);
    }
}
