<?php
namespace OCA\DriverManager\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class DriverMapper extends Mapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'drivermanager_drivers', Driver::class);
    }

    public function findAll($limit = null, $offset = null) {
        $sql = 'SELECT * FROM `' . $this->tableName . '` ORDER BY surname ASC';
        $params = [];
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }
        
        return $this->findEntities($sql, $params);
    }

    public function findByUserId($userId, $limit = null, $offset = null) {
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE user_id = ? ORDER BY surname ASC';
        $params = [$userId];
        
        if ($limit !== null) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ?';
            $params[] = $offset;
        }
        
        return $this->findEntities($sql, $params);
    }

    public function findExpiringDrivers($days) {
        $targetDate = new \DateTime();
        $targetDate->add(new \DateInterval('P' . $days . 'D'));
        
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE license_expiry = ?';
        $params = [$targetDate->format('Y-m-d')];
        
        return $this->findEntities($sql, $params);
    }
}