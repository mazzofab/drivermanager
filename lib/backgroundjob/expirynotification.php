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
        
        try {
            // Get services
            $driverMapper = \OC::$server->query(DriverMapper::class);
            $mailer = \OC::$server->getMailer();
            $config = \OC::$server->getConfig();
            $groupManager = \OC::$server->getGroupManager();
            $userManager = \OC::$server->getUserManager();
            
            // Calculate target dates
            $today = new \DateTime();
            $dates = [
                30 => (clone $today)->add(new \DateInterval('P30D'))->format('Y-m-d'),
                7 => (clone $today)->add(new \DateInterval('P7D'))->format('Y-m-d'),
                1 => (clone $today)->add(new \DateInterval('P1D'))->format('Y-m-d')
            ];
            
            \OC::$server->getLogger()->info('Checking expiry dates: ' . json_encode($dates), ['app' => 'drivermanager']);
            
            // Check each interval
            foreach ([30, 7, 1] as $days) {
                $targetDate = $dates[$days];
                
                // Use direct SQL query for more reliable results
                $connection = \OC::$server->getDatabaseConnection();
                $sql = 'SELECT * FROM oc_drivermanager_drivers WHERE license_expiry = ? ORDER BY surname ASC, name ASC';
                $result = $connection->executeQuery($sql, [$targetDate]);
                $rows = $result->fetchAll();
                
                \OC::$server->getLogger()->info("Found " . count($rows) . " drivers expiring on {$targetDate} ({$days} days)", ['app' => 'drivermanager']);
                
                if (!empty($rows)) {
                    // Convert to driver objects
                    $expiringDrivers = [];
                    foreach ($rows as $row) {
                        $driver = new \OCA\DriverManager\Db\Driver();
                        $driver->setId($row['id']);
                        $driver->setName($row['name']);
                        $driver->setSurname($row['surname']);
                        $driver->setLicenseNumber($row['license_number']);
                        $driver->setLicenseExpiry($row['license_expiry']);
                        $driver->setUserId($row['user_id']);
                        $expiringDrivers[] = $driver;
                    }
                    
                    $this->sendGroupEmail($expiringDrivers, $days, $mailer, $config, $groupManager, $userManager);
                }
            }
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Error in driver expiry notification: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    // ... rest of the methods remain the same ...
    private function sendGroupEmail($drivers, $days, $mailer, $config, $groupManager, $userManager) {
        // Same as before - no changes needed to this method
        try {
            $group = $groupManager->get('driver notifications');
            
            if (!$group) {
                \OC::$server->getLogger()->warning('Group "driver notifications" not found. Please create this group and add users to it.', ['app' => 'drivermanager']);
                return;
            }
            
            $groupUsers = $group->getUsers();
            
            if (empty($groupUsers)) {
                \OC::$server->getLogger()->warning('No users found in "driver notifications" group', ['app' => 'drivermanager']);
                return;
            }
            
            $recipients = [];
            foreach ($groupUsers as $user) {
                $email = $user->getEMailAddress();
                if ($email) {
                    $recipients[$email] = $user->getDisplayName();
                }
            }
            
            if (empty($recipients)) {
                \OC::$server->getLogger()->warning('No email addresses found for users in "driver notifications" group', ['app' => 'drivermanager']);
                return;
            }
            
            $message = $mailer->createMessage();
            
            $fromEmail = $config->getSystemValue('mail_from_address', 'noreply') . '@' . 
                        $config->getSystemValue('mail_domain', 'yourcompany.com');
            
            $subject = $this->getEmailSubject($drivers, $days);
            $htmlBody = $this->getEmailBody($drivers, $days);
            
            $message->setSubject($subject)
                    ->setFrom([$fromEmail => 'Driver Manager System'])
                    ->setTo($recipients)
                    ->setHtmlBody($htmlBody);
            
            $mailer->send($message);
            
            \OC::$server->getLogger()->info("Successfully sent expiry notification email for {$days} days to " . count($recipients) . " recipients", ['app' => 'drivermanager']);
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Failed to send group email: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    private function getEmailSubject($drivers, $days) {
        $count = count($drivers);
        
        if ($days === 1) {
            return $count === 1 
                ? "Urgent: 1 driver license expires tomorrow"
                : "Urgent: {$count} driver licenses expire tomorrow";
        } elseif ($days === 7) {
            return $count === 1 
                ? "Warning: 1 driver license expires in 7 days"
                : "Warning: {$count} driver licenses expire in 7 days";
        } else {
            return $count === 1 
                ? "Notice: 1 driver license expires in {$days} days"
                : "Notice: {$count} driver licenses expire in {$days} days";
        }
    }

    private function getEmailBody($drivers, $days) {
        $count = count($drivers);
        
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        
        $html .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        $html .= '<h2 style="color: #0082c9; margin: 0;">Driver License Expiry Notification</h2>';
        $html .= '</div>';
        
        if ($days === 1) {
            $html .= '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #dc3545;">';
            $html .= '<strong>🚨 URGENT:</strong> The following driver' . ($count > 1 ? 's have' : ' has') . ' license' . ($count > 1 ? 's' : '') . ' expiring <strong>tomorrow</strong>:';
            $html .= '</div>';
        } elseif ($days === 7) {
            $html .= '<div style="background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #ffc107;">';
            $html .= '<strong>⚠️ WARNING:</strong> The following driver' . ($count > 1 ? 's have' : ' has') . ' license' . ($count > 1 ? 's' : '') . ' expiring in <strong>7 days</strong>:';
            $html .= '</div>';
        } else {
            $html .= '<div style="background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #17a2b8;">';
            $html .= '<strong>📢 NOTICE:</strong> The following driver' . ($count > 1 ? 's have' : ' has') . ' license' . ($count > 1 ? 's' : '') . ' expiring in <strong>' . $days . ' days</strong>:';
            $html .= '</div>';
        }
        
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        $html .= '<thead>';
        $html .= '<tr style="background-color: #0082c9; color: white;">';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Name</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Surname</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">License Number</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Expiry Date</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        $html .= '<tbody>';
        foreach ($drivers as $driver) {
            $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->getName()) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->getSurname()) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-family: monospace; font-weight: bold;">' . htmlspecialchars($driver->getLicenseNumber()) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . $this->formatDateForEmail($driver->getLicenseExpiry()) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        $html .= '</table>';
        
        $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">';
        $html .= '<p>This is an automated notification from the Driver Manager system.</p>';
        $html .= '<p>Generated on: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }

    private function formatDateForEmail($dateString) {
        try {
            $date = new \DateTime($dateString);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }
}