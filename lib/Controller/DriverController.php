<?php

declare(strict_types=1);

namespace OCA\DriverManager\Controller;

use DateTime;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\DriverManager\Db\Driver;
use OCA\DriverManager\Db\DriverMapper;
use Psr\Log\LoggerInterface;

class DriverController extends Controller {
    private DriverMapper $mapper;
    private IUserSession $userSession;
    private LoggerInterface $logger;
    private ?string $userId;

    public function __construct(
        IRequest $request,
        DriverMapper $mapper,
        IUserSession $userSession,
        LoggerInterface $logger
    ) {
        parent::__construct('drivermanager', $request);
        $this->mapper = $mapper;
        $this->userSession = $userSession;
        $this->logger = $logger;
        
        $user = $this->userSession->getUser();
        $this->userId = $user ? $user->getUID() : null;
    }

    #[NoAdminRequired]
    public function index(): DataResponse {
        return new DataResponse($this->mapper->findAll());
    }

    #[NoAdminRequired]
    public function create(string $name, string $surname, string $licenseNumber, string $licenseExpiry): DataResponse {
        $driver = new Driver();
        $driver->setName($name);
        $driver->setSurname($surname);
        $driver->setLicenseNumber($licenseNumber);
        $driver->setLicenseExpiry($licenseExpiry);
        $driver->setUserId($this->userId);
        
        $now = new DateTime();
        $driver->setCreatedAt($now->format('Y-m-d H:i:s'));
        $driver->setUpdatedAt($now->format('Y-m-d H:i:s'));
        
        return new DataResponse($this->mapper->insert($driver));
    }

    #[NoAdminRequired]
    public function update(int $id, string $name, string $surname, string $licenseNumber, string $licenseExpiry): DataResponse {
        try {
            $driver = $this->mapper->find($id);
            $driver->setName($name);
            $driver->setSurname($surname);
            $driver->setLicenseNumber($licenseNumber);
            $driver->setLicenseExpiry($licenseExpiry);
            
            $now = new DateTime();
            $driver->setUpdatedAt($now->format('Y-m-d H:i:s'));
            
            return new DataResponse($this->mapper->update($driver));
        } catch (\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): DataResponse {
        try {
            if (!$id || $id <= 0) {
                return new DataResponse(['error' => 'Invalid driver ID'], 400);
            }
            
            $driver = $this->mapper->find($id);
            
            if (!$driver) {
                return new DataResponse(['error' => 'Driver not found'], 404);
            }
            
            $this->mapper->delete($driver);
            
            return new DataResponse(['success' => true, 'message' => 'Driver deleted successfully']);
            
        } catch (\Exception $e) {
            $this->logger->error(
                'Error deleting driver: ' . $e->getMessage(),
                ['app' => 'drivermanager']
            );
            return new DataResponse(['error' => 'Failed to delete driver: ' . $e->getMessage()], 500);
        }
    }
}