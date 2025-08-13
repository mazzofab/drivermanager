<?php
namespace OCA\DriverManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class Driver extends Entity implements JsonSerializable {
    protected $name;
    protected $surname;
    protected $licenseNumber;
    protected $licenseExpiry;
    protected $userId;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('createdAt', 'datetime');
        $this->addType('updatedAt', 'datetime');
        $this->addType('licenseExpiry', 'date');
    }

    public function jsonSerialize() {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'licenseNumber' => $this->licenseNumber,
            'licenseExpiry' => $this->licenseExpiry,
            'userId' => $this->userId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt
        ];
    }
}