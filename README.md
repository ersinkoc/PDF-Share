# PDF QR Link

A PHP-based document management system for PDF files with QR code generation and tracking capabilities.

## Features

- PDF document upload and management
- Automatic QR code generation for each document
- Short URL generation for easy sharing
- Document access tracking and analytics
- Role-based user management
- Backup and restore functionality

## Requirements

- PHP 8.0+
- SQLite 3
- PHP Extensions:
  - PDO SQLite
  - GD/Imagick (for QR codes)
  - ZIP
  - JSON
  - FileInfo

## Directory Structure

The following directories must be writable:
```
/database   - SQLite database storage
/uploads    - PDF file storage
/backups    - System backup storage
/cache      - QR code and temporary files
/logs       - System and error logs
```

## Installation

1. Set directory permissions:
```bash
chmod 755 database uploads backups cache logs
```

2. Access the application and log in:
```
URL: /admin
Username: admin
Password: admin123
```

3. Configure system settings in admin panel

## Core Functions

### Document Management
- Upload single/multiple PDF files
- Generate QR codes automatically
- Create short URLs for sharing
- Track document access

### User Management
- Role-based access control
- Activity logging
- Session management
- IP-based restrictions

### System Features
- Database backup/restore
- Error logging
- Storage management
- System statistics

## Security

- Change default admin credentials immediately
- Set proper file permissions
- Enable CSRF protection
- Monitor access logs
- Regular backups recommended

## License

MIT License
