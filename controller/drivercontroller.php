<?php
namespace OCA\DriverManager\Controller;

use DateTime;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use OCA\DriverManager\Db\Driver;
use OCA\DriverManager\Db\DriverMapper;
use OCP\IDBConnection;

class DriverController extends Controller {
    private $mapper;
    private $userId;

    public function __construct($AppName, IRequest $request, IDBConnection $db) {
        parent::__construct($AppName, $request);
        $this->mapper = new DriverMapper($db);
        
        // Get current user
        $userSession = \OC::$server->getUserSession();
        $user = $userSession->getUser();
        $this->userId = $user ? $user->getUID() : null;
    }

    /**
     * @NoAdminRequired
     */
    public function index() {
        return new DataResponse($this->mapper->findAll());
    }

    /**
     * @NoAdminRequired
     * @param string $name
     * @param string $surname
     * @param string $licenseNumber
     * @param string $licenseExpiry
     */
    public function create($name, $surname, $licenseNumber, $licenseExpiry) {
        $driver = new Driver();
        $driver->setName($name);
        $driver->setSurname($surname);
        $driver->setLicenseNumber($licenseNumber);
        $driver->setLicenseExpiry($licenseExpiry);
        $driver->setUserId($this->userId);
        $driver->setCreatedAt(new DateTime());
        $driver->setUpdatedAt(new DateTime());
        
        return new DataResponse($this->mapper->insert($driver));
    }

    /**
     * @NoAdminRequired
     * @param int $id
     * @param string $name
     * @param string $surname
     * @param string $licenseNumber
     * @param string $licenseExpiry
     */
    public function update($id, $name, $surname, $licenseNumber, $licenseExpiry) {
        try {
            $driver = $this->mapper->find($id);
            $driver->setName($name);
            $driver->setSurname($surname);
            $driver->setLicenseNumber($licenseNumber);
            $driver->setLicenseExpiry($licenseExpiry);
            $driver->setUpdatedAt(new DateTime());
            
            return new DataResponse($this->mapper->update($driver));
        } catch(\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * @NoAdminRequired
     * @param int $id
     */
    public function destroy($id) {
        try {
            $driver = $this->mapper->find($id);
            $this->mapper->delete($driver);
            return new DataResponse($driver);
        } catch(\Exception $e) {
            return new DataResponse(['error' => $e->getMessage()], 404);
        }
    }
}