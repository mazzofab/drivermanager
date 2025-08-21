<?php

declare(strict_types=1);

namespace OCA\DriverManager\BackgroundJob;

use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use OCP\AppFramework\Utility\ITimeFactory;

class ExpiryNotification extends TimedJob {
    private IMailer $mailer;
    private IConfig $config;
    private IGroupManager $groupManager;
    private IUserManager $userManager;
    private LoggerInterface $logger;
    private INotificationManager $notificationManager;

    public function __construct(
        ITimeFactory $timeFactory,
        ?IMailer $mailer = null,
        ?IConfig $config = null,
        ?IGroupManager $groupManager = null,
        ?IUserManager $userManager = null,
        ?LoggerInterface $logger = null,
        ?INotificationManager $notificationManager = null
    ) {
        // Call parent constructor first to initialize the $time property
        parent::__construct($timeFactory);
        
        // Run daily at a specific time (24 hours = 86400 seconds)
        // This ensures the job only runs once per day
        $this->setInterval(86400);
        
        // Initialize dependencies - use service container if not provided
        $this->mailer = $mailer ?? \OC::$server->getMailer();
        $this->config = $config ?? \OC::$server->getConfig();
        $this->groupManager = $groupManager ?? \OC::$server->getGroupManager();
        $this->userManager = $userManager ?? \OC::$server->getUserManager();
        $this->logger = $logger ?? \OC::$server->get(LoggerInterface::class);
        $this->notificationManager = $notificationManager ?? \OC::$server->getNotificationManager();
    }

    /**
     * @param mixed $argument
     */
    protected function run($argument): void {
        // Check if we've already run today
        $lastRunKey = 'drivermanager_last_notification_run';
        $lastRun = $this->config->getAppValue('drivermanager', $lastRunKey, '');
        $today = date('Y-m-d');
        
        if ($lastRun === $today) {
            $this->logger->debug('Driver expiry notification already ran today, skipping', ['app' => 'drivermanager']);
            return;
        }
        
        $this->executeNotification(false);
    }

    /**
     * Public method for testing notifications
     * Can be called from the controller for manual testing
     */
    public function runTest(): void {
        $this->logger->info('Running test notification (manual trigger)', ['app' => 'drivermanager']);
        $this->executeNotification(true);
    }

    /**
     * Execute the notification logic
     * @param bool $isTest Whether this is a test run (bypasses daily check)
     */
    private function executeNotification(bool $isTest = false): void {
        $lastRunKey = 'drivermanager_last_notification_run';
        $today = date('Y-m-d');
        
        $this->logger->info('Running driver license expiry check for ' . $today . ($isTest ? ' (TEST MODE)' : ''), ['app' => 'drivermanager']);

        try {
            // Get all drivers expiring within the next 30 days
            $expiringDrivers = $this->getDriversExpiringWithin30Days();

            if (!empty($expiringDrivers)) {
                $this->logger->info(
                    "Found " . count($expiringDrivers) . " drivers expiring within 30 days",
                    ['app' => 'drivermanager']
                );
                
                // Send email notifications
                $this->sendGroupEmail($expiringDrivers);
                
                // Send push notifications to mobile apps
                $this->sendPushNotifications($expiringDrivers);
                
                // Update last run date after successful notifications (only if not a test)
                if (!$isTest) {
                    $this->config->setAppValue('drivermanager', $lastRunKey, $today);
                }
                
                $this->logger->info('Successfully sent email and push notifications' . ($isTest ? ' (TEST MODE)' : '') . ' and updated last run date', ['app' => 'drivermanager']);
            } else {
                $this->logger->info("No drivers found expiring within 30 days", ['app' => 'drivermanager']);
                // Still update last run date even if no drivers found (only if not a test)
                if (!$isTest) {
                    $this->config->setAppValue('drivermanager', $lastRunKey, $today);
                }
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'Error in driver expiry notification: ' . $e->getMessage(),
                ['app' => 'drivermanager', 'exception' => $e]
            );
            throw $e; // Re-throw for the controller to handle
        }
    }

    /**
     * Send push notifications to mobile app users
     * @param \stdClass[] $drivers
     */
    private function sendPushNotifications(array $drivers): void {
        try {
            // Get the "driver notifications" group
            $group = $this->groupManager->get('driver notifications');

            if (!$group) {
                $this->logger->warning(
                    'Group "driver notifications" not found for push notifications',
                    ['app' => 'drivermanager']
                );
                return;
            }

            // Get all users in the group
            $groupUsers = $group->getUsers();

            if (empty($groupUsers)) {
                $this->logger->warning(
                    'No users found in "driver notifications" group for push notifications',
                    ['app' => 'drivermanager']
                );
                return;
            }

            $this->logger->info(
                'Starting push notifications for ' . count($groupUsers) . ' users',
                ['app' => 'drivermanager']
            );

            // Count different urgency levels
            $expired = count(array_filter($drivers, function ($d) {
                return $d->urgency === 'expired';
            }));
            $critical = count(array_filter($drivers, function ($d) {
                return $d->urgency === 'critical';
            }));
            $urgent = count(array_filter($drivers, function ($d) {
                return $d->urgency === 'urgent';
            }));
            $warning = count(array_filter($drivers, function ($d) {
                return $d->urgency === 'warning';
            }));

            // Prepare notification message
            $subject = '';
            $message = '';
            $richMessage = '';
            
            if ($expired > 0) {
                $subject = 'driver_licenses_expired';
                $message = "{$expired} driver license" . ($expired > 1 ? 's have' : ' has') . " EXPIRED!";
                $richMessage = "🚨 CRITICAL: {$expired} license" . ($expired > 1 ? 's' : '') . " expired";
            } elseif ($critical > 0) {
                $subject = 'driver_licenses_critical';
                $message = "{$critical} driver license" . ($critical > 1 ? 's expire' : ' expires') . " within 24 hours!";
                $richMessage = "🚨 URGENT: {$critical} license" . ($critical > 1 ? 's expire' : ' expires') . " in 24h";
            } elseif ($urgent > 0) {
                $subject = 'driver_licenses_urgent';
                $message = "{$urgent} driver license" . ($urgent > 1 ? 's expire' : ' expires') . " within 7 days";
                $richMessage = "⚠️ WARNING: {$urgent} license" . ($urgent > 1 ? 's expire' : ' expires') . " this week";
            } else {
                $subject = 'driver_licenses_warning';
                $message = "{$warning} driver license" . ($warning > 1 ? 's expire' : ' expires') . " within 30 days";
                $richMessage = "📢 NOTICE: {$warning} license" . ($warning > 1 ? 's expire' : ' expires') . " this month";
            }

            // Get first few driver names for the message
            $driverNames = array_slice(array_map(function($d) {
                return $d->name . ' ' . $d->surname;
            }, $drivers), 0, 3);
            
            if (count($drivers) > 3) {
                $driverNames[] = 'and ' . (count($drivers) - 3) . ' more';
            }

            // Send notification to each user in the group
            $sentCount = 0;
            $failedCount = 0;
            
            foreach ($groupUsers as $user) {
                try {
                    $userId = $user->getUID();
                    $this->logger->debug(
                        "Creating notification for user: {$userId}",
                        ['app' => 'drivermanager']
                    );
                    
                    $notification = $this->notificationManager->createNotification();
                    
                    // Set basic notification parameters
                    $notification->setApp('drivermanager')
                        ->setUser($userId)
                        ->setDateTime(new \DateTime())
                        ->setObject('drivers', 'expiring_' . date('Ymd_His')) // Make unique
                        ->setSubject($subject, [
                            'count' => count($drivers),
                            'expired' => $expired,
                            'critical' => $critical,
                            'urgent' => $urgent,
                            'warning' => $warning
                        ]);
                    
                    // Set the message with driver details
                    $notification->setMessage('driver_expiry_details', [
                        'message' => $message,
                        'drivers' => implode(', ', $driverNames)
                    ]);
                    
                    // Set simple rich content without complex parameters
                    $notification->setRichSubject($richMessage);
                    $notification->setRichMessage($message . ': ' . implode(', ', $driverNames));
                    
                    // Set absolute link to view in Driver Manager
                    $urlGenerator = \OC::$server->getURLGenerator();
                    $relativeUrl = $urlGenerator->linkToRoute('drivermanager.page.index');
                    $absoluteUrl = $urlGenerator->getAbsoluteURL($relativeUrl);
                    $notification->setLink($absoluteUrl);
                    
                    // Send the notification
                    $this->notificationManager->notify($notification);
                    $sentCount++;
                    
                    $this->logger->info(
                        "Successfully sent push notification to user: {$userId}",
                        ['app' => 'drivermanager']
                    );
                    
                } catch (\Exception $e) {
                    $failedCount++;
                    $this->logger->error(
                        "Failed to send push notification to user {$user->getUID()}: " . $e->getMessage(),
                        ['app' => 'drivermanager', 'exception' => $e]
                    );
                }
            }

            $this->logger->info(
                "Push notification summary: {$sentCount} sent successfully, {$failedCount} failed, for " . count($drivers) . " expiring drivers",
                ['app' => 'drivermanager']
            );
            
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send push notifications: ' . $e->getMessage(),
                ['app' => 'drivermanager', 'exception' => $e]
            );
        }
    }

    /**
     * Get appropriate icon based on urgency
     */
    private function getNotificationIcon(int $expired, int $critical, int $urgent): string {
        // Just use the default app icon that exists
        // The app.svg file is in the img folder
        return \OC::$server->getURLGenerator()->imagePath('drivermanager', 'app.svg');
    }

    /**
     * Get all drivers with licenses expiring within the next 30 days
     * @return \stdClass[]
     */
    private function getDriversExpiringWithin30Days(): array {
        try {
            $connection = \OC::$server->getDatabaseConnection();
            $today = new \DateTime();
            $thirtyDaysFromNow = (new \DateTime())->add(new \DateInterval('P30D'));

            $qb = $connection->getQueryBuilder();
            $qb->select('*')
                ->from('drivermanager_drivers')
                ->where($qb->expr()->gte('license_expiry', $qb->createNamedParameter($today->format('Y-m-d'))))
                ->andWhere($qb->expr()->lte('license_expiry', $qb->createNamedParameter($thirtyDaysFromNow->format('Y-m-d'))))
                ->orderBy('license_expiry', 'ASC')
                ->addOrderBy('surname', 'ASC')
                ->addOrderBy('name', 'ASC');

            $result = $qb->executeQuery();
            $rows = $result->fetchAll();

            // Convert to driver objects with expiry info
            $expiringDrivers = [];
            foreach ($rows as $row) {
                $driver = new \stdClass();
                $driver->id = (int)$row['id'];
                $driver->name = $row['name'];
                $driver->surname = $row['surname'];
                $driver->licenseNumber = $row['license_number'];
                $driver->licenseExpiry = $row['license_expiry'];
                $driver->userId = $row['user_id'];

                // Calculate days until expiry
                $expiryDate = new \DateTime($row['license_expiry']);
                $today = new \DateTime();
                $today->setTime(0, 0, 0); // Reset time to start of day for accurate comparison
                $expiryDate->setTime(0, 0, 0);
                
                $diff = $today->diff($expiryDate);
                $driver->daysUntilExpiry = $diff->days;

                // Handle past dates (expired licenses)
                if ($expiryDate < $today) {
                    $driver->daysUntilExpiry = -$diff->days; // Negative for expired
                } elseif (!$diff->invert) {
                    $driver->daysUntilExpiry = $diff->days;
                }

                // Determine urgency level
                if ($driver->daysUntilExpiry < 0) {
                    $driver->urgency = 'expired';
                    $driver->urgencyText = 'EXPIRED ' . abs($driver->daysUntilExpiry) . ' days ago';
                } elseif ($driver->daysUntilExpiry === 0) {
                    $driver->urgency = 'critical';
                    $driver->urgencyText = 'EXPIRES TODAY';
                } elseif ($driver->daysUntilExpiry === 1) {
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
            $this->logger->error(
                'Error fetching expiring drivers: ' . $e->getMessage(),
                ['app' => 'drivermanager', 'exception' => $e]
            );
            return [];
        }
    }

    /**
     * Send email to all users in the "driver notifications" group
     * @param \stdClass[] $drivers
     */
    private function sendGroupEmail(array $drivers): void {
        try {
            // Get the "driver notifications" group
            $group = $this->groupManager->get('driver notifications');

            if (!$group) {
                $this->logger->warning(
                    'Group "driver notifications" not found. Please create this group and add users to it.',
                    ['app' => 'drivermanager']
                );
                return;
            }

            // Get all users in the group
            $groupUsers = $group->getUsers();

            if (empty($groupUsers)) {
                $this->logger->warning(
                    'No users found in "driver notifications" group',
                    ['app' => 'drivermanager']
                );
                return;
            }

            // Get email addresses
            $recipients = [];
            foreach ($groupUsers as $user) {
                $email = $user->getEMailAddress();
                if ($email) {
                    $recipients[$email] = $user->getDisplayName();
                    $this->logger->info(
                        "Added recipient: {$email} ({$user->getDisplayName()})",
                        ['app' => 'drivermanager']
                    );
                }
            }

            if (empty($recipients)) {
                $this->logger->warning(
                    'No email addresses found for users in "driver notifications" group',
                    ['app' => 'drivermanager']
                );
                return;
            }

            // Create and send email
            $message = $this->mailer->createMessage();

            // Get email settings from config
            $fromEmail = $this->config->getSystemValueString('mail_from_address', 'noreply') . '@' .
                        $this->config->getSystemValueString('mail_domain', 'yourcompany.com');

            // Set email properties
            $subject = $this->getEmailSubject($drivers);
            $htmlBody = $this->getEmailBody($drivers);

            $message->setSubject($subject)
                    ->setFrom([$fromEmail => 'Driver Manager System'])
                    ->setTo($recipients)
                    ->setHtmlBody($htmlBody);

            // Send the email
            $this->mailer->send($message);

            $this->logger->info(
                "Successfully sent expiry notification email to " . count($recipients) . " recipients for " . count($drivers) . " expiring drivers",
                ['app' => 'drivermanager']
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to send group email: ' . $e->getMessage(),
                ['app' => 'drivermanager', 'exception' => $e]
            );
        }
    }

    /**
     * Generate email subject based on urgency and number of drivers
     * @param \stdClass[] $drivers
     */
    private function getEmailSubject(array $drivers): string {
        $total = count($drivers);
        $expired = count(array_filter($drivers, function ($d) {
            return $d->urgency === 'expired';
        }));
        $critical = count(array_filter($drivers, function ($d) {
            return $d->urgency === 'critical';
        }));
        $urgent = count(array_filter($drivers, function ($d) {
            return $d->urgency === 'urgent';
        }));

        if ($expired > 0) {
            return "🚨 CRITICAL: {$expired} driver license" . ($expired > 1 ? 's have' : ' has') . " EXPIRED";
        } elseif ($critical > 0) {
            return "🚨 CRITICAL: {$critical} driver license" . ($critical > 1 ? 's expire' : ' expires') . " within 24 hours";
        } elseif ($urgent > 0) {
            return "⚠️ URGENT: {$urgent} driver license" . ($urgent > 1 ? 's expire' : ' expires') . " within 7 days";
        } else {
            return "📢 NOTICE: {$total} driver license" . ($total > 1 ? 's expire' : ' expires') . " within 30 days";
        }
    }

    /**
     * Generate comprehensive HTML email body
     * @param \stdClass[] $drivers
     */
    private function getEmailBody(array $drivers): string {
        $total = count($drivers);
        $expired = array_filter($drivers, function ($d) {
            return $d->urgency === 'expired';
        });
        $critical = array_filter($drivers, function ($d) {
            return $d->urgency === 'critical';
        });
        $urgent = array_filter($drivers, function ($d) {
            return $d->urgency === 'urgent';
        });
        $warning = array_filter($drivers, function ($d) {
            return $d->urgency === 'warning';
        });

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
        if (count($expired) > 0) {
            $html .= '<p style="margin: 5px 0; color: #dc3545;"><strong>🚨 EXPIRED:</strong> ' . count($expired) . '</p>';
        }
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

        // Table body - grouped by urgency (expired first, then critical, urgent, warning)
        $html .= '<tbody>';

        // Expired drivers first (dark red background)
        foreach ($expired as $driver) {
            $html .= '<tr style="background-color: #721c24; color: white; border-bottom: 1px solid #dee2e6;">';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($driver->name) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($driver->surname) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-family: monospace; font-weight: bold;">' . htmlspecialchars($driver->licenseNumber) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; font-weight: bold;">' . $this->formatDateForEmail($driver->licenseExpiry) . '</td>';
            $html .= '<td style="padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold;">💀 ' . $driver->urgencyText . '</td>';
            $html .= '</tr>';
        }

        // Critical drivers (red background)
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
        if (count($expired) > 0) {
            $html .= '<li><strong style="color: #721c24;">EXPIRED LICENSES: Immediate action required - drivers cannot legally drive!</strong></li>';
        }
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
        $html .= '<p>This is a daily notification sent to members of the "driver notifications" group.</p>';
        $html .= '<p>Push notifications have also been sent to mobile app users.</p>';
        $html .= '<p>To stop receiving these notifications, ask your administrator to remove you from the group.</p>';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Format date for email display (DD/MM/YYYY)
     */
    private function formatDateForEmail(string $dateString): string {
        try {
            $date = new \DateTime($dateString);
            return $date->format('d/m/Y');
        } catch (\Exception $e) {
            return $dateString;
        }
    }
}
