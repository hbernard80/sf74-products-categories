<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function createIndexQueryBuilder(?string $search = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('product')
            ->leftJoin('product.categories', 'category')
            ->addSelect('category')
            ->orderBy('product.createdAt', 'DESC')
        ;

        if ($search) {
            $queryBuilder
                ->andWhere('LOWER(product.name) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search.'%')
            ;
        }

        return $queryBuilder;
    }

    /**
     * @return Product[]
     */
    public function findLatestCreated(int $limit = 5): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    public function findOneWithCategories(int $id): ?Product
    {
        return $this->createQueryBuilder('product')
            ->leftJoin('product.categories', 'category')
            ->addSelect('category')
            ->andWhere('product.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
