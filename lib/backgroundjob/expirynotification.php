<?php
namespace OCA\DriverManager\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\DriverManager\Db\DriverMapper;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\ILogger;
use OCP\Notification\IManager;

class ExpiryNotification extends TimedJob {
    private $driverMapper;
    private $userManager;
    private $mailer;
    private $logger;
    private $notificationManager;

    public function __construct(DriverMapper $driverMapper, 
                               IUserManager $userManager,
                               IMailer $mailer,
                               ILogger $logger,
                               IManager $notificationManager) {
        $this->driverMapper = $driverMapper;
        $this->userManager = $userManager;
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->notificationManager = $notificationManager;
        
        // Run daily
        $this->setInterval(24 * 60 * 60);
    }

    protected function run($argument) {
        $this->logger->info('Running driver license expiry check');
        
        // Check for licenses expiring in 30, 7, and 1 days
        $checkDays = [30, 7, 1];
        
        foreach ($checkDays as $days) {
            $expiringDrivers = $this->driverMapper->findExpiringDrivers($days);
            
            foreach ($expiringDrivers as $driver) {
                $this->sendNotification($driver, $days);
                $this->sendEmail($driver, $days);
            }
        }
    }

    private function sendNotification($driver, $days) {
        $notification = $this->notificationManager->createNotification();
        $notification->setApp('drivermanager')
                     ->setUser('admin') // Send to admin or configure recipient list
                     ->setDateTime(new \DateTime())
                     ->setObject('driver', $driver->getId())
                     ->setSubject('license_expiry', [
                         'name' => $driver->getName() . ' ' . $driver->getSurname(),
                         'days' => $days
                     ]);
        
        $this->notificationManager->notify($notification);
    }

    private function sendEmail($driver, $days) {
        try {
            $message = $this->mailer->createMessage();
            $message->setSubject("Driver License Expiry Warning - {$days} days")
                    ->setFrom(['noreply@yourcompany.com' => 'Driver Manager'])
                    ->setTo(['admin@yourcompany.com']) // Configure recipient list
                    ->setHtmlBody($this->getEmailBody($driver, $days));
            
            $this->mailer->send($message);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send expiry email: ' . $e->getMessage());
        }
    }

    private function getEmailBody($driver, $days) {
        return sprintf(
            '<h2>Driver License Expiry Warning</h2>
             <p>The driving license for <strong>%s %s</strong> will expire in <strong>%d days</strong>.</p>
             <p><strong>License Number:</strong> %s</p>
             <p><strong>Expiry Date:</strong> %s</p>
             <p>Please take necessary action to renew the license.</p>',
            $driver->getName(),
            $driver->getSurname(),
            $days,
            $driver->getLicenseNumber(),
            $driver->getLicenseExpiry()
        );
    }
}