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
            $mailer = \OC::$server->getMailer();
            $config = \OC::$server->getConfig();
            $groupManager = \OC::$server->getGroupManager();
            $userManager = \OC::$server->getUserManager();
            
            // Get all drivers expiring within the next 30 days
            $expiringDrivers = $this->getDriversExpiringWithin30Days();
            
            if (!empty($expiringDrivers)) {
                \OC::$server->getLogger()->info("Found " . count($expiringDrivers) . " drivers expiring within 30 days", ['app' => 'drivermanager']);
                $this->sendGroupEmail($expiringDrivers, $mailer, $config, $groupManager, $userManager);
            } else {
                \OC::$server->getLogger()->info("No drivers found expiring within 30 days", ['app' => 'drivermanager']);
            }
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Error in driver expiry notification: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    /**
     * Get all drivers with licenses expiring within the next 30 days
     */
    private function getDriversExpiringWithin30Days() {
        try {
            $connection = \OC::$server->getDatabaseConnection();
            $today = new \DateTime();
            $thirtyDaysFromNow = (new \DateTime())->add(new \DateInterval('P30D'));
            
            $sql = 'SELECT * FROM oc_drivermanager_drivers 
                    WHERE license_expiry BETWEEN ? AND ? 
                    ORDER BY license_expiry ASC, surname ASC, name ASC';
            
            $result = $connection->executeQuery($sql, [
                $today->format('Y-m-d'), 
                $thirtyDaysFromNow->format('Y-m-d')
            ]);
            $rows = $result->fetchAll();
            
            // Convert to driver objects with expiry info
            $expiringDrivers = [];
            foreach ($rows as $row) {
                $driver = new \stdClass();
                $driver->id = $row['id'];
                $driver->name = $row['name'];
                $driver->surname = $row['surname'];
                $driver->licenseNumber = $row['license_number'];
                $driver->licenseExpiry = $row['license_expiry'];
                $driver->userId = $row['user_id'];
                
                // Calculate days until expiry
                $expiryDate = new \DateTime($row['license_expiry']);
                $today = new \DateTime();
                $driver->daysUntilExpiry = $expiryDate->diff($today)->days;
                
                // Determine urgency level
                if ($driver->daysUntilExpiry <= 1) {
                    $driver->urgency = 'critical';
                    $driver->urgencyText = 'EXPIRES TOMORROW';
                } elseif ($driver->daysUntilExpiry <= 7) {
                    $driver->urgency = 'urgent';
                    $driver->urgencyText = 'EXPIRES IN ' . $driver->daysUntilExpiry . ' DAYS';
                } else {
                    $driver->urgency = 'warning';
                    $driver->urgencyText = 'Expires in ' . $driver->daysUntilExpiry . ' days';
                }
                
                $expiringDrivers[] = $driver;
            }
            
            return $expiringDrivers;
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Error fetching expiring drivers: ' . $e->getMessage(), ['app' => 'drivermanager']);
            return [];
        }
    }

    /**
     * Send email to all users in the "driver notifications" group
     */
    private function sendGroupEmail($drivers, $mailer, $config, $groupManager, $userManager) {
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
            $subject = $this->getEmailSubject($drivers);
            $htmlBody = $this->getEmailBody($drivers);
            
            $message->setSubject($subject)
                    ->setFrom([$fromEmail => 'Driver Manager System'])
                    ->setTo($recipients)
                    ->setHtmlBody($htmlBody);
            
            // Send the email
            $mailer->send($message);
            
            \OC::$server->getLogger()->info("Successfully sent expiry notification email to " . count($recipients) . " recipients for " . count($drivers) . " expiring drivers", ['app' => 'drivermanager']);
            
        } catch (\Exception $e) {
            \OC::$server->getLogger()->error('Failed to send group email: ' . $e->getMessage(), ['app' => 'drivermanager']);
        }
    }

    /**
     * Generate email subject based on urgency and number of drivers
     */
    private function getEmailSubject($drivers) {
        $total = count($drivers);
        $critical = count(array_filter($drivers, function($d) { return $d->urgency === 'critical'; }));
        $urgent = count(array_filter($drivers, function($d) { return $d->urgency === 'urgent'; }));
        
        if ($critical > 0) {
            return "🚨 CRITICAL: {$critical} driver license" . ($critical > 1 ? 's expire' : ' expires') . " within 24 hours";
        } elseif ($urgent > 0) {
            return "⚠️ URGENT: {$urgent} driver license" . ($urgent > 1 ? 's expire' : ' expires') . " within 7 days";
        } else {
            return "📢 NOTICE: {$total} driver license" . ($total > 1 ? 's expire' : ' expires') . " within 30 days";
        }
    }

    /**
     * Generate comprehensive HTML email body
     */
    private function getEmailBody($drivers) {
        $total = count($drivers);
        $critical = array_filter($drivers, function($d) { return $d->urgency === 'critical'; });
        $urgent = array_filter($drivers, function($d) { return $d->urgency === 'urgent'; });
        $warning = array_filter($drivers, function($d) { return $d->urgency === 'warning'; });
        
        // Email header
        $html = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
        
        // Header section
        $html .= '<div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        $html .= '<h2 style="color: #0082c9; margin: 0;">🚗 Driver License Expiry Report</h2>';
        $html .= '<p style="margin: 5px 0 0 0; color: #666;">Generated on ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';
        
        // Summary section
        $html .= '<div style="background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">';
        $html .= '<h3 style="margin-top: 0; color: #004085;">📊 Summary</h3>';
        $html .= '<p style="margin-bottom: 0;"><strong>Total drivers with expiring licenses:</strong> ' . $total . '</p>';
        if (count($critical) > 0) {
            $html .= '<p style="margin: 5px 0; color: #dc3545;"><strong>🚨 Critical (≤1 day):</strong> ' . count($critical) . '</p>';
        }
        if (count($urgent) > 0) {
            $html .= '<p style="margin: 5px 0; color: #fd7e14;"><strong>⚠️ Urgent (2-7 days):</strong> ' . count($urgent) . '</p>';
        }
        if (count($warning) > 0) {
            $html .= '<p style="margin: 5px 0; color: #0d6efd;"><strong>📢 Notice (8-30 days):</strong> ' . count($warning) . '</p>';
        }
        $html .= '</div>';
        
        // Main message
        $html .= '<div style="margin-bottom: 20px;">';
        $html .= '<p><strong>The following drivers have their driving licenses expiring within the next 30 days:</strong></p>';
        $html .= '</div>';
        
        // Drivers table
        $html .= '<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        // Table header
        $html .= '<thead>';
        $html .= '<tr style="background-color: #0082c9; color: white;">';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Name</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Surname</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">License Number</th>';
        $html .= '<th style="padding: 12px; text-align: left; border: 1px solid #dee2e6;">Expiry Date</th>';
        $html .= '<th style="padding: 12px; text-align: center; border: 1px solid #dee2e6;">Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        
        // Table body - grouped by urgency
        $html .= '<tbody>';
        
        // Critical drivers first (red background)
        foreach ($critical as $driver) {
            $html .= '<tr style="background-color: #f8d7da; border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($driver->name) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($driver->surname) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-family: monospace; font-weight: bold;">' . htmlspecialchars($driver->licenseNumber) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . $this->formatDateForEmail($driver->licenseExpiry) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold; color: #721c24;">🚨 ' . $driver->urgencyText . '</td>';
            $html .= '</tr>';
        }
        
        // Urgent drivers (yellow background)
        foreach ($urgent as $driver) {
            $html .= '<tr style="background-color: #fff3cd; border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->name) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->surname) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-family: monospace; font-weight: bold;">' . htmlspecialchars($driver->licenseNumber) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . $this->formatDateForEmail($driver->licenseExpiry) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold; color: #856404;">⚠️ ' . $driver->urgencyText . '</td>';
            $html .= '</tr>';
        }
        
        // Warning drivers (blue background)
        foreach ($warning as $driver) {
            $html .= '<tr style="border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->name) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . htmlspecialchars($driver->surname) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-family: monospace; font-weight: bold;">' . htmlspecialchars($driver->licenseNumber) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6;">' . $this->formatDateForEmail($driver->licenseExpiry) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; text-align: center; color: #0c5460;">📢 ' . $driver->urgencyText . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        
        // Action required section
        $html .= '<div style="background-color: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #007bff;">';
        $html .= '<h3 style="margin-top: 0; color: #004085;">🎯 Action Required:</h3>';
        $html .= '<ul style="margin-bottom: 0;">';
        if (count($critical) > 0) {
            $html .= '<li><strong style="color: #dc3545;">IMMEDIATE ACTION: Contact drivers with licenses expiring within 24 hours!</strong></li>';
        }
        if (count($urgent) > 0) {
            $html .= '<li><strong style="color: #fd7e14;">URGENT: Schedule renewals for drivers expiring within 7 days</strong></li>';
        }
        $html .= '<li>Contact all drivers to arrange license renewal appointments</li>';
        $html .= '<li>Ensure renewals are completed before expiry dates</li>';
        $html .= '<li>Update the Driver Manager system once renewals are confirmed</li>';
        $html .= '<li>Monitor the system daily for new expiring licenses</li>';
        $html .= '</ul>';
        $html .= '</div>';
        
        // Footer
        $html .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">';
        $html .= '<p><strong>Driver Manager System</strong> - Automated License Expiry Notification</p>';
        $html .= '<p>This email is sent daily to members of the "driver notifications" group.</p>';
        $html .= '<p>To stop receiving these notifications, ask your administrator to remove you from the group.</p>';
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
}