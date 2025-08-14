<?php
namespace OCA\DriverManager\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\DriverManager\Db\DriverMapper;

class ExpiryNotification extends TimedJob {
    
    public function __construct() {
        // Run daily at a specific time (you can adjust this)
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
            
            // Check for licenses expiring in 30, 7, and 1 days
            $checkDays = [30, 7, 1];
            
            foreach ($checkDays as $days) {
                $expiringDrivers = $driverMapper->findExpiringDrivers($days);
                
                if (!empty($expiringDrivers)) {
                    \OC::$server->getLogger()->info("Found " . count($expiringDrivers) . " drivers expiring in {$days} days", ['app' => 'drivermanager']);
                    $this->sendGroupEmail($expiringDrivers, $days, $mailer, $config, $groupManager, $userManager);
                }
            }
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Error in driver expiry notification: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    /**
     * Send email to all users in the "driver notifications" group
     */
    private function sendGroupEmail($drivers, $days, $mailer, $config, $groupManager, $userManager) {
        try {
            // Get the "driver notifications" group
            $group = $groupManager->get('driver notifications');
            
            if (!$group) {
                \OC::$server->getLogger()->warning('Group "driver notifications" not found. Please create this group and add users to it.', ['app' => 'drivermanager']);
                return;
            }
            
            // Get all users in the group
            $groupUsers = $group->getUsers();
            
            if (empty($groupUsers)) {
                \OC::$server->getLogger()->warning('No users found in "driver notifications" group', ['app' => 'drivermanager']);
                return;
            }
            
            // Get email addresses
            $recipients = [];
            foreach ($groupUsers as $user) {
                $email = $user->getEMailAddress();
                if ($email) {
                    $recipients[$email] = $user->getDisplayName();
                    \OC::$server->getLogger()->info("Added recipient: {$email} ({$user->getDisplayName()})", ['app' => 'drivermanager']);
                }
            }
            
            if (empty($recipients)) {
                \OC::$server->getLogger()->warning('No email addresses found for users in "driver notifications" group', ['app' => 'drivermanager']);
                return;
            }
            
            // Create and send email
            $message = $mailer->createMessage();
            
            // Get email settings from config
            $fromEmail = $config->getSystemValue('mail_from_address', 'noreply') . '@' . 
                        $config->getSystemValue('mail_domain', 'yourcompany.com');
            
            // Set email properties
            $subject = $this->getEmailSubject($drivers, $days);
            $htmlBody = $this->getEmailBody($drivers, $days);
            
            $message->setSubject($subject)
                    ->setFrom([$fromEmail => 'Driver Manager System'])
                    ->setTo($recipients)
                    ->setHtmlBody($htmlBody);
            
            // Send the email
            $mailer->send($message);
            
            \OC::$server->getLogger()->info("Successfully sent expiry notification email for {$days} days to " . count($recipients) . " recipients", ['app' => 'drivermanager']);
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Failed to send group email: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    /**
     * Generate email subject based on number of drivers and days
     */
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

    /**
     * Generate HTML email body with driver details
     */
    private function getEmailBody($drivers, $days) {
        $count = count($drivers);
        
        // Email header
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        
        // Header section
        $html .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        $html .= '<h2 style="color: #0082c9; margin: 0;">Driver License Expiry Notification</h2>';
        $html .= '</div>';
        
        // Urgency indicator
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
        
        // Drivers table
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        // Table header
        $html .= '<thead>';
        $html .= '<tr style="background-color: #0082c9; color: white;">';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Name</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Surname</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">License Number</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Expiry Date</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body
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
        
        // Action required section
        $html .= '<div style="background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">';
        $html .= '<h3 style="margin-top: 0; color: #004085;">Action Required:</h3>';
        $html .= '<ul style="margin-bottom: 0;">';
        $html .= '<li>Contact the driver' . ($count > 1 ? 's' : '') . ' to arrange license renewal</li>';
        $html .= '<li>Ensure renewal is completed before the expiry date</li>';
        $html .= '<li>Update the system once renewal is confirmed</li>';
        if ($days === 1) {
            $html .= '<li><strong>URGENT: Take immediate action as license' . ($count > 1 ? 's expire' : ' expires') . ' tomorrow!</strong></li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
        
        // Footer
        $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">';
        $html .= '<p>This is an automated notification from the Driver Manager system.</p>';
        $html .= '<p>Generated on: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '<p>Total drivers monitored: ' . $this->getTotalDriversCount() . '</p>';
        $html .= '</div>';
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * Format date for email display (DD/MM/YYYY)
     */
    private function formatDateForEmail($dateString) {
        try {
            $date = new \DateTime($dateString);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }

    /**
     * Get total count of drivers for footer info
     */
    private function getTotalDriversCount() {
        try {
            $driverMapper = \OC::$server->query(DriverMapper::class);
            return count($driverMapper->findAll());
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
}