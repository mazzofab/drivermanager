<?php

declare(strict_types=1);

namespace OCA\DriverManager\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Driver>
 */
class DriverMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'drivermanager_drivers', Driver::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
     * @throws DoesNotExistException
     */
    public function find(int $id): Driver {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

        return $this->findEntity($qb);
    }

    /**
     * @return Driver[]
     */
    public function findAll(?int $limit = null, ?int $offset = null): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('surname', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    /**
     * @return Driver[]
     */
    public function findByUserId(string $userId, ?int $limit = null, ?int $offset = null): array {
        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('surname', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }
        if ($offset !== null) {
            $qb->setFirstResult($offset);
        }

        return $this->findEntities($qb);
    }

    /**
     * Find drivers with licenses expiring in exactly X days
     * @return Driver[]
     */
    public function findExpiringDrivers(int $days): array {
        $targetDate = new \DateTime();
        $targetDate->add(new \DateInterval('P' . $days . 'D'));

        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq(
                'license_expiry',
                $qb->createNamedParameter($targetDate->format('Y-m-d'))
            ))
            ->orderBy('surname', 'ASC')
            ->addOrderBy('name', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find drivers with licenses expiring within X days
     * @return Driver[]
     */
    public function findExpiringWithinDays(int $days): array {
        $today = new \DateTime();
        $futureDate = new \DateTime();
        $futureDate->add(new \DateInterval('P' . $days . 'D'));

        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->gte(
                'license_expiry',
                $qb->createNamedParameter($today->format('Y-m-d'))
            ))
            ->andWhere($qb->expr()->lte(
                'license_expiry',
                $qb->createNamedParameter($futureDate->format('Y-m-d'))
            ))
            ->orderBy('license_expiry', 'ASC')
            ->addOrderBy('surname', 'ASC');

        return $this->findEntities($qb);
    }

    /**
     * Find drivers expiring on specific dates
     * @param string[] $dates
     * @return Driver[]
     */
    public function findExpiringOnDates(array $dates): array {
        if (empty($dates)) {
            return [];
        }

        $qb = $this->db->getQueryBuilder();

        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->in('license_expiry', $qb->createNamedParameter($dates, IQueryBuilder::PARAM_STR_ARRAY)))
            ->orderBy('license_expiry', 'ASC')
            ->addOrderBy('surname', 'ASC');

        return $this->findEntities($qb);
    }
}
