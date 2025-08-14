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
        
        // Convert DateTime to string format for ownCloud 10.15.3 compatibility
        $now = new DateTime();
        $driver->setCreatedAt($now->format('Y-m-d H:i:s'));
        $driver->setUpdatedAt($now->format('Y-m-d H:i:s'));
        
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
            
            // Convert DateTime to string format for ownCloud 10.15.3 compatibility
            $now = new DateTime();
            $driver->setUpdatedAt($now->format('Y-m-d H:i:s'));
            
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
            // Check if ID is valid
            if (!$id || !is_numeric($id)) {
                return new DataResponse(['error' => 'Invalid driver ID'], 400);
            }
            
            // Try to find the driver first
            $driver = $this->mapper->find($id);
            
            if (!$driver) {
                return new DataResponse(['error' => 'Driver not found'], 404);
            }
            
            // Delete the driver
            $this->mapper->delete($driver);
            
            return new DataResponse(['success' => true, 'message' => 'Driver deleted successfully']);
            
        } catch(\Exception $e) {
            \OC::$server->getLogger()->error('Error deleting driver: ' . $e->getMessage(), ['app' => 'drivermanager']);
            return new DataResponse(['error' => 'Failed to delete driver: ' . $e->getMessage()], 500);
        }
    }
}