<?php
namespace OCA\DriverManager\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class DriverMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'drivermanager_drivers', Driver::class);
    }

    public function findAll($limit = null, $offset = null) {
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

    public function findByUserId(string $userId, $limit = null, $offset = null) {
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

    public function findExpiringDrivers($days) {
        $qb = $this->db->getQueryBuilder();
        $targetDate = new \DateTime();
        $targetDate->add(new \DateInterval('P' . $days . 'D'));
        
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('license_expiry', $qb->createNamedParameter($targetDate->format('Y-m-d'))));
           
        return $this->findEntities($qb);
    }
}