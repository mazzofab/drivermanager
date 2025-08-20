<?php

declare(strict_types=1);

namespace OCA\DriverManager\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string getSurname()
 * @method void setSurname(string $surname)
 * @method string getLicenseNumber()
 * @method void setLicenseNumber(string $licenseNumber)
 * @method string getLicenseExpiry()
 * @method void setLicenseExpiry(string $licenseExpiry)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getCreatedAt()
 * @method void setCreatedAt(string $createdAt)
 * @method string getUpdatedAt()
 * @method void setUpdatedAt(string $updatedAt)
 */
class Driver extends Entity implements JsonSerializable {
    protected string $name = '';
    protected string $surname = '';
    protected string $licenseNumber = '';
    protected string $licenseExpiry = '';
    protected string $userId = '';
    protected string $createdAt = '';
    protected string $updatedAt = '';

    public function __construct() {
        $this->addType('id', 'integer');
        $this->addType('name', 'string');
        $this->addType('surname', 'string');
        $this->addType('licenseNumber', 'string');
        $this->addType('licenseExpiry', 'string');
        $this->addType('userId', 'string');
        $this->addType('createdAt', 'string');
        $this->addType('updatedAt', 'string');
    }

    public function jsonSerialize(): array {
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
