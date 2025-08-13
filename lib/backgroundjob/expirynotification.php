<?php
namespace OCA\DriverManager\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\DriverManager\Db\DriverMapper;

class ExpiryNotification extends TimedJob {
    
    public function __construct() {
        // Run daily
        $this->setInterval(24 * 60 * 60);
    }

    protected function run($argument) {
        \OC::$server->getLogger()->info('Running driver license expiry check', ['app' => 'drivermanager']);
        
        // Get services
        $driverMapper = \OC::$server->query(DriverMapper::class);
        $mailer = \OC::$server->getMailer();
        $config = \OC::$server->getConfig();
        
        // Check for licenses expiring in 30, 7, and 1 days
        $checkDays = [30, 7, 1];
        
        foreach ($checkDays as $days) {
            $expiringDrivers = $driverMapper->findExpiringDrivers($days);
            
            foreach ($expiringDrivers as $driver) {
                $this->sendEmail($driver, $days, $mailer, $config);
            }
        }
    }

    private function sendEmail($driver, $days, $mailer, $config) {
        try {
            $message = $mailer->createMessage();
            
            // Get email settings from config or use defaults
            $fromEmail = $config->getSystemValue('mail_from_address', 'noreply') . '@' . 
                        $config->getSystemValue('mail_domain', 'yourcompany.com');
            $adminEmail = $config->getSystemValue('admin_email', 'admin@yourcompany.com');
            
            $message->setSubject("Driver License Expiry Warning - {$days} days")
                    ->setFrom([$fromEmail => 'Driver Manager'])
                    ->setTo([$adminEmail]) // Configure recipient list
                    ->setHtmlBody($this->getEmailBody($driver, $days));
            
            $mailer->send($message);
            \OC::$server->getLogger()->info("Sent expiry email for driver: " . $driver->getName(), ['app' => 'drivermanager']);
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Failed to send expiry email: ' . $e->getMessage(), ['app' => 'drivermanager']);
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