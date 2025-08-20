# Nextcloud Driver Manager App

A comprehensive Nextcloud app to manage driver information and track license expiry dates with automatic email notifications.

## Features

- **Driver Management**: Complete driver information management (Name, Surname, License Number, Expiry Date)
- **Automatic Notifications**: Email notifications for licenses expiring in 30, 7, and 1 day intervals
- **Group-based Notifications**: Send notifications to users in the "driver notifications" group
- **Search & Pagination**: Advanced search functionality with pagination support
- **Custom Date Picker**: User-friendly date selection interface
- **Responsive Design**: Works on desktop and mobile devices
- **Background Jobs**: Automated daily checks for expiring licenses

## Requirements

- **Nextcloud**: Version 25.0 or higher (supports up to Nextcloud 30)
- **PHP**: Version 8.0 or higher
- **Database**: MySQL 8.0+, PostgreSQL 9.6+, or SQLite 3.0+
- **Email Configuration**: Configured SMTP server for notifications

## Installation

### Method 1: From Nextcloud App Store (Recommended)
1. Open your Nextcloud instance
2. Go to Apps → Browse and search for "Driver Manager"
3. Click "Download and enable"

### Method 2: Manual Installation
1. Download the latest release from GitHub
2. Extract the archive to your Nextcloud apps directory:
   ```bash
   cd /path/to/nextcloud/apps/
   unzip drivermanager-v2.0.0.zip
   ```
3. Set proper permissions:
   ```bash
   chown -R www-data:www-data /path/to/nextcloud/apps/drivermanager
   ```
4. Enable the app:
   ```bash
   sudo -u www-data php /path/to/nextcloud/occ app:enable drivermanager
   ```

## Configuration

### 1. Email Notifications Setup

#### Configure SMTP Settings
Go to **Settings → Administration → Basic settings** and configure your email server:
- SMTP host and port
- Authentication credentials
- Encryption method (TLS/SSL)

#### Create Notification Group
1. Go to **Settings → Users → Groups**
2. Create a new group called **"driver notifications"**
3. Add users who should receive expiry notifications to this group

### 2. Background Jobs
Ensure background jobs are properly configured:
```bash
# Set up cron job for Nextcloud (recommended)
sudo crontab -u www-data -e

# Add this line:
*/5 * * * * php /path/to/nextcloud/cron.php
```

Or use **Settings → Administration → Basic settings** to configure background jobs via the web interface.

### 3. App Settings
The app works out of the box after installation. Additional customization options:

- **Notification Recipients**: Users in the "driver notifications" group
- **Check Frequency**: Daily automatic checks (runs once per day regardless of cron frequency)
- **Notification Intervals**: 30, 7, and 1 day before expiry

## Usage

### Adding Drivers
1. Navigate to **Driver Manager** from the main navigation
2. Click **"Add New Driver"**
3. Fill in the driver information:
   - Name (auto-capitalized)
   - Surname (auto-capitalized)
   - License Number (auto-uppercase, alphanumeric only)
   - Expiry Date (use the calendar picker)
4. Click **"Save"**

### Managing Drivers
- **Search**: Use the search bar to find drivers by name, surname, or license number
- **Edit**: Click the "Edit" button next to any driver
- **Delete**: Click the "Delete" button (requires confirmation)
- **Pagination**: Navigate through large lists using the pagination controls

### Understanding Status Colors
- **🟢 Valid**: License expires in more than 30 days
- **🟡 Expiring Soon**: License expires within 30 days
- **🔴 Expired**: License has already expired

## Email Notifications

The app automatically sends **one daily email notification** for licenses expiring within 30 days. The notification system includes built-in duplicate prevention to ensure emails are sent only once per day, even if your cron job runs more frequently.

### Notification Schedule
- **Once Daily**: Notifications are sent once per day when the cron job runs
- **Duplicate Prevention**: The system tracks the last notification date to prevent multiple emails

### Email Categories

#### Critical (🚨)
- Licenses expiring within 24 hours
- Highest priority notifications

#### Urgent (⚠️)
- Licenses expiring within 2-7 days
- Requires immediate attention

#### Notice (📢)
- Licenses expiring within 8-30 days
- Plan renewal appointments

### Email Content
Each notification includes:
- Summary of expiring licenses by urgency
- Complete driver details table
- Action items and recommendations
- Color-coded urgency indicators

### Testing Email Notifications

To reset the notification system for testing purposes (allows the email to be sent again):

```bash
# Reset the last notification date
sudo -u www-data php /path/to/nextcloud/occ config:app:delete drivermanager drivermanager_last_notification_run
```

After running this command, the notification will be sent on the next cron execution.

## Troubleshooting

### Common Issues

#### Background Job Not Running
```bash
# Check if the job is registered
sudo -u www-data php /path/to/nextcloud/occ background:job:list | grep DriverManager

# Run background jobs manually
sudo -u www-data php /path/to/nextcloud/cron.php
```

#### Email Not Sending
1. Verify SMTP configuration in Nextcloud settings
2. Check that the "driver notifications" group exists and has members
3. Ensure group members have valid email addresses
4. Check Nextcloud logs: **Settings → Administration → Logging**
5. Verify the notification hasn't already been sent today (check logs for "already ran today" message)

#### Notification Sent Multiple Times
If you're receiving multiple emails per day:
1. Ensure you're using the updated version with duplicate prevention
2. Check logs for the message: "Driver expiry notification already ran today"
3. The app stores the last run date to prevent duplicates

#### Reset Notification for Testing
To force the notification to run again for testing:
```bash
sudo -u www-data php /path/to/nextcloud/occ config:app:delete drivermanager drivermanager_last_notification_run
```

#### App Not Loading
```bash
# Check app status
sudo -u www-data php /path/to/nextcloud/occ app:list | grep drivermanager

# Check logs for errors
sudo -u www-data php /path/to/nextcloud/occ log:watch
```

### Log Files
Check these locations for troubleshooting:
- Nextcloud logs: **Settings → Administration → Logging**
- System logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- PHP logs: Check your PHP configuration for log file location
- Search for "drivermanager" in logs to find app-specific messages

## Development

### Building from Source
```bash
git clone https://github.com/yourusername/nextcloud-drivermanager.git
cd nextcloud-drivermanager
npm install
npm run build
```

### Running Tests
```bash
# PHP tests
composer install
./vendor/bin/phpunit

# JavaScript tests
npm test
```

## API Endpoints

The app provides REST API endpoints:

- `GET /apps/drivermanager/api/drivers` - List all drivers
- `POST /apps/drivermanager/api/drivers` - Create new driver
- `PUT /apps/drivermanager/api/drivers/{id}` - Update driver
- `DELETE /apps/drivermanager/api/drivers/{id}` - Delete driver

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is licensed under the AGPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## Support

- **Issues**: [GitHub Issues](https://github.com/yourusername/nextcloud-drivermanager/issues)
- **Documentation**: [Wiki](https://github.com/yourusername/nextcloud-drivermanager/wiki)
- **Community**: [Nextcloud Community](https://help.nextcloud.com/)

## Changelog

### Version 2.0.1 (2025-01-18)
- 🔧 Fixed duplicate email notifications issue
- ✨ Added notification reset command for testing
- ✨ Improved date comparison logic
- 📝 Enhanced documentation for troubleshooting

### Version 2.0.0 (2025-01-01)
- ✨ Nextcloud compatibility (25-30)
- ✨ PHP 8.0+ support with type declarations
- ✨ Modern dependency injection
- ✨ Improved email notifications
- ✨ Enhanced search and pagination
- 🔧 Database optimizations
- 🔧 Code modernization

### Version 1.0.3 (2024-01-01)
- Initial ownCloud release
- Basic driver management
- Email notifications
- Background job support