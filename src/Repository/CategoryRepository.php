<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    public function createIndexQueryBuilder(?string $search = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('category')
            ->leftJoin('category.parent', 'parent')
            ->addSelect('parent')
            ->leftJoin('category.children', 'child')
            ->addSelect('child')
            ->orderBy('category.createdAt', 'DESC')
        ;

        if ($search) {
            $queryBuilder
                ->andWhere('LOWER(category.name) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search.'%')
            ;
        }

        return $queryBuilder;
    }

    /**
     * @return Category[]
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
     * @return Category[]
     */
    public function findParentCategories(): array
    {
        return $this->findBy(['parent' => null], ['name' => 'ASC']);
    }

    /**
     * @return Category[]
     */
    public function findChildrenByParent(Category $parent): array
    {
        return $this->findBy(['parent' => $parent], ['name' => 'ASC']);
    }

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

    //    /**
    //     * @return Category[] Returns an array of Category objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Category
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
