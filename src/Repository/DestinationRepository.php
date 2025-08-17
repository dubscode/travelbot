<?php

namespace App\Repository;

use App\Entity\Destination;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Destination>
 */
class DestinationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Destination::class);
    }

    /**
     * Find destinations by country
     */
    public function findByCountry(string $country): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.country = :country')
            ->setParameter('country', $country)
            ->orderBy('d.popularityScore', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find destinations by tags
     */
    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('d');
        
        foreach ($tags as $index => $tag) {
            $qb->andWhere('JSON_CONTAINS(d.tags, :tag' . $index . ') = 1')
               ->setParameter('tag' . $index, json_encode($tag));
        }
        
        return $qb->orderBy('d.popularityScore', 'DESC')
                  ->addOrderBy('d.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Find destinations with minimum popularity score
     */
    public function findByPopularityScore(int $minScore): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.popularityScore >= :minScore')
            ->setParameter('minScore', $minScore)
            ->orderBy('d.popularityScore', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find destination with its resorts
     */
    public function findWithResorts(string $destinationId): ?Destination
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.resorts', 'r')
            ->leftJoin('r.category', 'c')
            ->leftJoin('r.amenities', 'a')
            ->where('d.id = :destinationId')
            ->setParameter('destinationId', $destinationId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Search destinations by text in name, city, country, or description
     */
    public function searchByText(string $searchTerm): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.name LIKE :searchTerm')
            ->orWhere('d.city LIKE :searchTerm')
            ->orWhere('d.country LIKE :searchTerm')
            ->orWhere('d.description LIKE :searchTerm')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('d.popularityScore', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get top destinations by popularity
     */
    public function findTopDestinations(int $limit = 20): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.popularityScore', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
