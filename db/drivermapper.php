<?php
namespace OCA\DriverManager\Db;

use OCP\AppFramework\Db\Mapper;
use OCP\IDBConnection;

class DriverMapper extends Mapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'drivermanager_drivers', Driver::class);
    }

    public function find($id) {
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `id` = ?';
        return $this->findEntity($sql, [$id]);
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

    /**
     * Find drivers with licenses expiring in exactly X days
     */
    public function findExpiringDrivers($days) {
        $targetDate = new \DateTime();
        $targetDate->add(new \DateInterval('P' . $days . 'D'));
        
        $sql = 'SELECT * FROM `' . $this->tableName . '` WHERE license_expiry = ? ORDER BY surname ASC, name ASC';
        $params = [$targetDate->format('Y-m-d')];
        
        return $this->findEntities($sql, $params);
    }

    /**
     * Find drivers with licenses expiring within X days (alternative method)
     */
    public function findExpiringWithinDays($days) {
        $today = new \DateTime();
        $futureDate = new \DateTime();
        $futureDate->add(new \DateInterval('P' . $days . 'D'));
        
        $sql = 'SELECT * FROM `' . $this->tableName . '` 
                WHERE license_expiry BETWEEN ? AND ? 
                ORDER BY license_expiry ASC, surname ASC';
        
        $params = [
            $today->format('Y-m-d'),
            $futureDate->format('Y-m-d')
        ];
        
        return $this->findEntities($sql, $params);
    }

    /**
     * Find drivers expiring on specific dates
     */
    public function findExpiringOnDates($dates) {
        if (empty($dates)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($dates) - 1) . '?';
        $sql = 'SELECT * FROM `' . $this->tableName . '` 
                WHERE license_expiry IN (' . $placeholders . ') 
                ORDER BY license_expiry ASC, surname ASC';
        
        return $this->findEntities($sql, $dates);
    }
}