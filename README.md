# PDF QR Link

A modern PHP application for managing, sharing, and tracking PDF documents with QR codes and short URLs.

## Overview

PDF QR Link is a comprehensive document management system that enables:
- Secure PDF document storage and management
- Automatic QR code generation for easy sharing
- Short URL creation for convenient access
- Detailed analytics and tracking
- Cloud storage integration (S3/MinIO)

## Key Features

### Document Management
- Upload and manage PDF documents
- Automatic QR code generation
- Short URL creation
- Document versioning
- Bulk upload support
- Document categorization

### Cloud Storage
- Amazon S3 integration
- MinIO support
- Automatic backups
- Storage synchronization
- Configurable retention policies

### Security
- Role-based access control
- Secure file handling
- Session management
- Activity logging
- IP-based access control
- CSRF protection

### Analytics & Tracking
- View and download statistics
- Access logs with IP tracking
- User engagement metrics
- Export reports (CSV/JSON)
- Custom date range filtering

### Admin Dashboard
- Intuitive user interface
- Document management
- User administration
- System configuration
- Backup management
- Import/export functionality

## Technical Requirements

- PHP 8.0 or higher
- SQLite 3
- Required PHP Extensions:
  - PDO SQLite
  - GD or Imagick
  - ZIP
  - JSON
  - FileInfo
- Write permissions for:
  - `/database`
  - `/uploads`
  - `/backups`
  - `/cache`
  - `/logs`

## Installation

1. Clone or download the repository
2. Ensure all required directories have write permissions:
   ```bash
   chmod 755 database uploads backups cache logs
   ```
3. Access the application through your web browser
4. Log in to the admin panel at `/admin` using:
   - Username: `admin`
   - Password: `admin123`
5. Configure system settings in the admin panel

The application uses SQLite and will automatically initialize the database on first use.

## Configuration

### System Settings
- General settings (site title, description)
- Upload limits and restrictions
- QR code customization
- Security parameters
- Email notifications
- Storage settings

### Cloud Storage Setup
1. Access S3 settings in admin panel
2. Configure:
   - Provider (S3/MinIO)
   - Endpoint
   - Access credentials
   - Bucket settings
   - SSL verification
   - Path style options

## Usage Guide

### Document Management
1. Upload documents via admin panel
2. System automatically generates:
   - QR code
   - Short URL
   - Preview (if enabled)
3. Share using QR code or URL
4. Track usage in analytics

### Backup Management
1. Configure backup settings
2. Schedule automatic backups
3. Choose storage location:
   - Local storage
   - Cloud storage (S3/MinIO)
4. Set retention policies

### Analytics
1. Access statistics dashboard
2. View:
   - Document access metrics
   - User engagement data
   - Download statistics
   - Geographic data
3. Export reports in CSV/JSON

## Security Recommendations

1. Change default admin credentials immediately
2. Configure secure file permissions
3. Enable SSL/TLS
4. Set up regular backups
5. Monitor access logs
6. Update regularly

## Troubleshooting

Common issues and solutions:
1. Upload fails
   - Check file permissions
   - Verify PHP upload limits
   - Ensure directory exists
2. QR code not generating
   - Check GD/Imagick extension
   - Verify temp directory access
3. Cloud storage issues
   - Validate credentials
   - Check network connectivity
   - Verify bucket permissions

## Support

For support:
1. Check documentation
2. Review troubleshooting guide
3. Submit issue on repository
4. Contact system administrator

## License

This project is licensed under the MIT License. See LICENSE file for details.

## Contributing

Contributions are welcome! Please:
1. Fork the repository
2. Create feature branch
3. Submit pull request
4. Follow coding standards
5. Include tests when applicable
