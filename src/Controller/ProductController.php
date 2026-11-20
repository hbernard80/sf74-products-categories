<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/product')]
final class ProductController extends AbstractController
{
    /**
     * Affiche la liste paginée des produits, éventuellement filtrée par nom.
     *
     * @param Request $request Requête HTTP contenant les paramètres de recherche et de pagination.
     * @param ProductRepository $productRepository Repository des produits.
     *
     * @return Response Réponse HTML de la liste des produits.
     */
    #[Route(name: 'app_product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $productRepository): Response
    {
        $search = trim($request->query->getString('q'));

        $products = new Pagerfanta(new QueryAdapter($productRepository->createIndexQueryBuilder($search)));
        $products
            ->setMaxPerPage(10)
            ->setNormalizeOutOfRangePages(true)
            ->setCurrentPage(max(1, $request->query->getInt('page', 1)))
        ;

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    /**
     * Affiche et traite le formulaire de création d'un produit.
     *
     * @param Request $request Requête HTTP du formulaire.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
     *
     * @return Response Réponse HTML du formulaire ou redirection après création.
     */
    #[Route('/new', name: 'app_product_new', methods: ['GET', 'POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Les dates sont initialisées par TimestampableTrait lors de la persistance.
        $product = new Product();

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * Affiche un produit avec ses catégories associées.
     *
     * @param int $id Identifiant du produit.
     * @param ProductRepository $productRepository Repository des produits.
     *
     * @return Response Réponse HTML de la page détail.
     */
    #[Route('/{id}', requirements: ['id' => Requirement::DIGITS], name: 'app_product_show', methods: ['GET'])]
    public function show(int $id, ProductRepository $productRepository): Response
    {
        $product = $productRepository->findOneWithCategories($id);

        if (!$product) {
            throw $this->createNotFoundException('Unable to find Product entity.');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    /**
     * Modifie un produit existant.
     *
     * @param Request $request Requête HTTP du formulaire.
     * @param Product $product Produit à modifier.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
     *
     * @return Response Réponse HTML du formulaire ou redirection après modification.
     */
    #[Route('/{id}/edit', requirements: ['id' => Requirement::DIGITS], name: 'app_product_edit', methods: ['GET', 'POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('product/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    /**
     * Supprime un produit après validation du jeton CSRF.
     *
     * @param Request $request Requête HTTP contenant le jeton CSRF.
     * @param Product $product Produit à supprimer.
     * @param EntityManagerInterface $entityManager Gestionnaire d'entités Doctrine.
     *
     * @return Response Redirection vers la liste des produits, ou vers la page détail si le jeton est invalide.
     */
    #[Route('/{id}', requirements: ['id' => Requirement::DIGITS], name: 'app_product_delete', methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete'.$product->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('danger', 'Le produit n\'a pas été supprimé : le formulaire a expiré ou est invalide. Veuillez réessayer.');

            return $this->redirectToRoute('app_product_show', ['id' => $product->getId()], Response::HTTP_SEE_OTHER);
        }

        $entityManager->remove($product);
        $entityManager->flush();

        $this->addFlash('success', 'Le produit a bien été supprimé.');

        return $this->redirectToRoute('app_product_index', [], Response::HTTP_SEE_OTHER);
    }
}
