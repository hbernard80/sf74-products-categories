<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository dédié aux requêtes de consultation des catégories.
 *
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Construit la requête de liste avec le parent et les enfants préchargés.
     *
     * @param string|null $search Terme de recherche appliqué au nom de catégorie.
     *
     * @return QueryBuilder Requête Doctrine prête pour la pagination.
     */
    public function createIndexQueryBuilder(?string $search = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('category')
            ->leftJoin('category.parent', 'parent')
            ->addSelect('parent')
            ->leftJoin('category.children', 'child')
            ->addSelect('child')
            ->orderBy('category.createdAt', 'DESC')
        ;

        if (null !== $search && '' !== $search) {
            $queryBuilder
                ->andWhere(sprintf("LOWER(category.name) LIKE LOWER(:search) ESCAPE '%s'", LikePattern::ESCAPE_CHAR))
                ->setParameter('search', LikePattern::contains($search))
            ;
        }

        return $queryBuilder;
    }

    /**
     * Retourne les dernières catégories créées pour l'écran d'accueil.
     *
     * @param int $limit Nombre maximum de catégories retournées.
     *
     * @return Category[] Liste des dernières catégories créées.
     */
    public function findLatestCreated(int $limit = 5): array
    {
        return $this->createQueryBuilder('category')
            ->leftJoin('category.parent', 'parent')
            ->addSelect('parent')
            ->orderBy('category.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Retourne les catégories racines, sans parent.
     *
     * @return Category[] Liste des catégories sans parent.
     */
    public function findParentCategories(): array
    {
        return $this->findBy(['parent' => null], ['name' => 'ASC']);
    }

    /**
     * Retourne les sous-catégories directes d'une catégorie parente.
     *
     * @param Category $parent Catégorie parente.
     *
     * @return Category[] Liste des sous-catégories directes.
     */
    public function findChildrenByParent(Category $parent): array
    {
        return $this->findBy(['parent' => $parent], ['name' => 'ASC']);
    }

    /**
     * Retourne les identifiants de toutes les sous-catégories, directes ou non.
     *
     * Parcourt l'arbre niveau par niveau (une requête par niveau).
     *
     * @param Category $category Catégorie dont on cherche les descendants.
     *
     * @return list<int> Identifiants des descendants.
     */
    public function findDescendantIds(Category $category): array
    {
        if (null === $category->getId()) {
            return [];
        }

        $descendantIds = [];
        $levelIds = [$category->getId()];

        while ([] !== $levelIds) {
            /** @var list<int> $levelIds */
            $levelIds = array_map('intval', $this->createQueryBuilder('category')
                ->select('category.id')
                ->andWhere('IDENTITY(category.parent) IN (:parentIds)')
                ->setParameter('parentIds', $levelIds)
                ->getQuery()
                ->getSingleColumnResult());

            // Ignore les identifiants déjà vus, au cas où une boucle existerait déjà en base.
            $levelIds = array_values(array_diff($levelIds, $descendantIds, [$category->getId()]));
            $descendantIds = [...$descendantIds, ...$levelIds];
        }

        return $descendantIds;
    }

    /**
     * Charge une catégorie avec ses principales relations pour la page détail.
     *
     * @param int $id Identifiant de la catégorie.
     *
     * @return Category|null Catégorie trouvée ou null.
     */
    public function findOneWithRelations(int $id): ?Category
    {
        return $this->createQueryBuilder('category')
            ->leftJoin('category.parent', 'parent')
            ->addSelect('parent')
            ->leftJoin('category.children', 'child')
            ->addSelect('child')
            ->leftJoin('category.products', 'product')
            ->addSelect('product')
            ->andWhere('category.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
