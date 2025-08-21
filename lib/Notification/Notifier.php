<?php
declare(strict_types=1);

namespace OCA\DriverManager\Notification;

use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;

class Notifier implements INotifier {
    protected IFactory $factory;
    protected IURLGenerator $url;

    public function __construct(IFactory $factory, IURLGenerator $urlGenerator) {
        $this->factory = $factory;
        $this->url = $urlGenerator;
    }

    /**
     * Identifier of the notifier, only use [a-z0-9_]
     */
    public function getID(): string {
        return 'drivermanager';
    }

    /**
     * Human readable name describing the notifier
     */
    public function getName(): string {
        return $this->factory->get('drivermanager')->t('Driver Manager');
    }

    /**
     * @param INotification $notification
     * @param string $languageCode The code of the language that should be used to prepare the notification
     */
    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== 'drivermanager') {
            // Not our app, so we don't handle this notification
            throw new \InvalidArgumentException();
        }

        // Get the language translator for this language
        $l = $this->factory->get('drivermanager', $languageCode);

        // Set the icon for the notification - use absolute URL
        $notification->setIcon($this->url->getAbsoluteURL($this->url->imagePath('drivermanager', 'app.svg')));

        // Parse the subject based on the subject identifier
        $subject = $notification->getSubject();
        $subjectParams = $notification->getSubjectParameters();
        
        switch ($subject) {
            case 'driver_licenses_expired':
                $notification->setParsedSubject(
                    $l->n(
                        '%n driver license has EXPIRED!',
                        '%n driver licenses have EXPIRED!',
                        $subjectParams['expired'] ?? 1
                    )
                );
                break;
                
            case 'driver_licenses_critical':
                $notification->setParsedSubject(
                    $l->n(
                        '🚨 %n license expires within 24 hours!',
                        '🚨 %n licenses expire within 24 hours!',
                        $subjectParams['critical'] ?? 1
                    )
                );
                break;
                
            case 'driver_licenses_urgent':
                $notification->setParsedSubject(
                    $l->n(
                        '⚠️ %n license expires within 7 days',
                        '⚠️ %n licenses expire within 7 days',
                        $subjectParams['urgent'] ?? 1
                    )
                );
                break;
                
            case 'driver_licenses_warning':
                $notification->setParsedSubject(
                    $l->n(
                        '📢 %n license expires within 30 days',
                        '📢 %n licenses expire within 30 days',
                        $subjectParams['warning'] ?? $subjectParams['count'] ?? 1
                    )
                );
                break;
                
            default:
                // Fallback for any other notification types
                $notification->setParsedSubject(
                    $l->t('Driver license expiry notification')
                );
                break;
        }

        // Parse the message
        $message = $notification->getMessage();
        $messageParams = $notification->getMessageParameters();
        
        if ($message === 'driver_expiry_details') {
            $parsedMessage = $messageParams['message'] ?? 'Driver licenses expiring soon';
            if (!empty($messageParams['drivers'])) {
                $parsedMessage .= ': ' . $messageParams['drivers'];
            }
            $notification->setParsedMessage($parsedMessage);
        } else {
            // If rich message is set, it's already plain text
            $richMessage = $notification->getRichMessage();
            if ($richMessage) {
                $notification->setParsedMessage($richMessage);
            }
        }

        // Set the link - ensure it's absolute by using getAbsoluteURL
        if (!$notification->getLink()) {
            // Build the absolute URL to the driver manager app
            $relativeUrl = $this->url->linkToRoute('drivermanager.page.index');
            $absoluteUrl = $this->url->getAbsoluteURL($relativeUrl);
            $notification->setLink($absoluteUrl);
        }

        return $notification;
    }
}
