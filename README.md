# ownCloud Driver Manager App

## Features

- Complete driver information management (Name, Surname, License Number, Expiry Date)
- Automatic expiry notifications at 30, 7, and 1 day intervals
- Email and in-app notifications
- Integration with ownCloud's permission system
- Custom app icon and responsive interface
- Background job for automated checking
- Database migration support

## Installation

1. Extract this ZIP file to your ownCloud apps directory: `/path/to/owncloud/apps/`
2. Set proper permissions: `chown -R www-data:www-data /path/to/owncloud/apps/drivermanager`
3. Enable the app in ownCloud Admin → Apps → "Driver Manager"
4. Configure email settings in ownCloud Admin → Additional → Email server
5. Customize notification recipients by editing email addresses in: `lib/backgroundjob/expirynotification.php`

## Configuration

### Email Recipients
Edit the following file to configure who receives notifications:
`lib/backgroundjob/expirynotification.php`

### Notification Intervals
The app checks for expiring licenses at 30, 7, and 1 day intervals.

## Requirements

- ownCloud 10.15.x
- PHP 7.4 or higher
- Configured email server for notifications
- Background jobs enabled

## License

AGPL - See ownCloud licensing for details.