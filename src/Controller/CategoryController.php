<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/category')]
final class CategoryController extends AbstractController
{
    #[Route(name: 'app_category_index', methods: ['GET'])]
    public function index(Request $request, CategoryRepository $categoryRepository): Response
    {
        $search = trim($request->query->getString('q'));

        $categories = new Pagerfanta(new QueryAdapter($categoryRepository->createIndexQueryBuilder($search)));
        $categories
            ->setMaxPerPage(10)
            ->setNormalizeOutOfRangePages(true)
            ->setCurrentPage(max(1, $request->query->getInt('page', 1)))
        ;

        return $this->render('category/index.html.twig', [
            'categories' => $categories,
            'search' => $search,
        ]);
    }

    #[Route('/subcategories', name: 'app_category_subcategories', methods: ['GET'])]
    public function subcategories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $parent = $categoryRepository->find($request->query->getInt('parent'));

        if (!$parent) {
            return $this->json([]);
        }

        return $this->json(array_map(
            static fn (Category $category): array => [
                'id' => $category->getId(),
                'name' => $category->getName(),
            ],
            $categoryRepository->findChildrenByParent($parent)
        ));
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new Category();
        $now = new \DateTimeImmutable();
        $category
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_show', methods: ['GET'])]
    public function show(int $id, CategoryRepository $categoryRepository): Response
    {
        $category = $categoryRepository->findOneWithRelations($id);

        if (!$category) {
            throw $this->createNotFoundException('Unable to find Category entity.');
        }

        return $this->render('category/show.html.twig', [
            'category' => $category,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$category->getId(), $request->getPayload()->getString('_token'))) {
            if (!$category->getProducts()->isEmpty()) {
                $this->addFlash('danger', 'Impossible de supprimer cette catégorie : elle est rattachée à un ou plusieurs produits.');

                return $this->redirectToRoute('app_category_show', ['id' => $category->getId()], Response::HTTP_SEE_OTHER);
            }

            if (!$category->getChildren()->isEmpty()) {
                $this->addFlash('danger', 'Impossible de supprimer cette catégorie : elle contient une ou plusieurs sous-catégories.');

                return $this->redirectToRoute('app_category_show', ['id' => $category->getId()], Response::HTTP_SEE_OTHER);
            }

            $entityManager->remove($category);
            $entityManager->flush();

            $this->addFlash('success', 'La catégorie a bien été supprimée.');
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }
}
