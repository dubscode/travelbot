<?php

namespace App\Repository;

use App\Entity\ResortCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ResortCategory>
 */
class ResortCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResortCategory::class);
    }

    public function save(ResortCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ResortCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find similar categories using vector similarity
     */
    public function findSimilarCategories(array $queryVector, int $limit = 10): array
    {
        return $this->createQueryBuilder('rc')
            ->select('rc', 'COSINE_SIMILARITY(rc.embedding, :queryVector) AS similarity')
            ->where('rc.embedding IS NOT NULL')
            ->setParameter('queryVector', $queryVector)
            ->orderBy('similarity', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}