<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository dédié aux requêtes de consultation des produits.
 *
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Construit la requête de liste avec les catégories préchargées.
     *
     * @param string|null $search Terme de recherche appliqué au nom du produit.
     *
     * @return QueryBuilder Requête Doctrine prête pour la pagination.
     */
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
     * Retourne les derniers produits créés pour l'écran d'accueil.
     *
     * @param int $limit Nombre maximum de produits retournés.
     *
     * @return Product[] Liste des derniers produits créés.
     */
    public function findLatestCreated(int $limit = 5): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Charge un produit avec ses catégories pour la page détail.
     *
     * @param int $id Identifiant du produit.
     *
     * @return Product|null Produit trouvé ou null.
     */
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
