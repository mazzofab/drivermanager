<?php

declare(strict_types=1);

namespace OCA\DriverManager\Controller;

use DateTime;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OCA\DriverManager\Db\Driver;
use OCA\DriverManager\Db\DriverMapper;
use OCA\DriverManager\BackgroundJob\ExpiryNotification;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;

class DriverController extends Controller {
    private DriverMapper $mapper;
    private IUserSession $userSession;
    private LoggerInterface $logger;
    private IGroupManager $groupManager;
    private ITimeFactory $timeFactory;
    private IMailer $mailer;
    private IConfig $config;
    private IUserManager $userManager;
    private INotificationManager $notificationManager;
    private ?string $userId;

    public function __construct(
        IRequest $request,
        DriverMapper $mapper,
        IUserSession $userSession,
        LoggerInterface $logger,
        IGroupManager $groupManager,
        ITimeFactory $timeFactory,
        IMailer $mailer,
        IConfig $config,
        IUserManager $userManager,
        INotificationManager $notificationManager
    ) {
        parent::__construct('drivermanager', $request);
        $this->mapper = $mapper;
        $this->userSession = $userSession;
        $this->logger = $logger;
        $this->groupManager = $groupManager;
        $this->timeFactory = $timeFactory;
        $this->mailer = $mailer;
        $this->config = $config;
        $this->userManager = $userManager;
        $this->notificationManager = $notificationManager;
        
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

    /**
     * Test notification system - triggers immediate notification regardless of daily limit
     * Only accessible to users in the "driver notifications" group or admins
     */
    #[NoAdminRequired]
    public function testNotification(): DataResponse {
        try {
            // Check if user is in the driver notifications group or is admin
            $user = $this->userSession->getUser();
            if (!$user) {
                return new DataResponse(['error' => 'User not authenticated'], 401);
            }

            $group = $this->groupManager->get('driver notifications');
            $isInGroup = $group && $group->inGroup($user);
            $isAdmin = $this->groupManager->isAdmin($user->getUID());

            if (!$isInGroup && !$isAdmin) {
                return new DataResponse([
                    'error' => 'Access denied. You must be in the "driver notifications" group or be an admin to test notifications.'
                ], 403);
            }

            $this->logger->info('Manual test notification triggered by user: ' . $user->getUID(), ['app' => 'drivermanager']);

            // Create and run the notification job with test mode
            $notification = new ExpiryNotification(
                $this->timeFactory,
                $this->mailer,
                $this->config,
                $this->groupManager,
                $this->userManager,
                $this->logger,
                $this->notificationManager
            );

            // Call the public test method instead of the protected run method
            try {
                $notification->runTest();
                
                return new DataResponse([
                    'success' => true,
                    'message' => 'Test notifications sent successfully! Check your email and push notifications.',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
            } catch (\Exception $e) {
                // Check if it's a "no drivers found" situation
                if (strpos($e->getMessage(), 'No drivers found') !== false) {
                    return new DataResponse([
                        'success' => true,
                        'message' => 'Notification test completed. No expiring drivers found within 30 days.',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error(
                'Error testing notifications: ' . $e->getMessage(),
                ['app' => 'drivermanager', 'exception' => $e]
            );
            return new DataResponse([
                'error' => 'Failed to send test notifications: ' . $e->getMessage()
            ], 500);
        }
    }
}
