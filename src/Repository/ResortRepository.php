<?php

namespace App\Repository;

use App\Entity\Resort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Resort>
 */
class ResortRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Resort::class);
    }

    public function save(Resort $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Resort $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }


    /**
     * Find resorts with minimum star rating
     */
    public function findByMinimumStarRating(int $minStarRating): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.starRating >= :minStarRating')
            ->setParameter('minStarRating', $minStarRating)
            ->orderBy('r.starRating', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find resorts with exact star rating
     */
    public function findByExactStarRating(int $starRating): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.starRating = :starRating')
            ->setParameter('starRating', $starRating)
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find resorts by star rating range
     */
    public function findByStarRatingRange(int $minRating, int $maxRating): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.starRating >= :minRating')
            ->andWhere('r.starRating <= :maxRating')
            ->setParameter('minRating', $minRating)
            ->setParameter('maxRating', $maxRating)
            ->orderBy('r.starRating', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find luxury resorts (4+ stars)
     */
    public function findLuxuryResorts(): array
    {
        return $this->findByMinimumStarRating(4);
    }

    /**
     * Find resorts by destination with amenities
     */
    public function findByDestinationWithAmenities(string $destinationId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.amenities', 'a')
            ->leftJoin('r.category', 'c')
            ->leftJoin('r.destination', 'd')
            ->where('d.id = :destinationId')
            ->setParameter('destinationId', $destinationId)
            ->orderBy('r.starRating', 'DESC')
            ->addOrderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}