# PDF QR Link

A lightweight PHP application for uploading, sharing, and tracking PDF documents through QR codes and short URLs.

## Overview

PDF QR Link allows you to:
- Upload PDF documents
- Generate QR codes and short URLs for easy sharing
- Track view and download statistics
- Manage documents through an admin interface

## Features

- **PDF Management**
  - Upload single or multiple PDF files
  - Automatic QR code generation
  - Short URL creation for easy sharing
  
- **Statistics**
  - Track document views and downloads
  - View detailed access logs
  
- **Admin Dashboard**
  - Comprehensive document management
  - Import/export functionality
  - Database backup and restore
  - System settings configuration
  
- **Security**
  - Admin authentication
  - Session management
  - Secure file handling

## Requirements

- PHP 7.4 or higher
- Write permissions for the following directories:
  - `/database`
  - `/uploads`
  - `/cache`
  - `/logs`

## Installation

1. Copy all files to your web server
2. Ensure the required directories have write permissions
3. Access the site through your web browser
4. Log in to the admin panel at `/admin` with:
   - Username: `admin`
   - Password: `admin123`
5. Configure system settings as needed

No additional configuration or database setup is required. The system uses SQLite and will initialize the database automatically on first use.

## Usage

### Uploading PDF Files

1. Log in to the admin panel
2. Navigate to "Upload PDF" or "Bulk Upload"
3. Select the file(s) to upload
4. The system will generate a QR code and short URL automatically

### Sharing Documents

Share documents using either:
- The generated QR code (can be printed or embedded)
- The short URL (easy to share via email or messaging)

### Viewing Statistics

1. Log in to the admin panel
2. Navigate to "Documents" to see an overview
3. Click on a specific document to view detailed statistics
4. Access "Logs" for more detailed access information

## Administration

### Data Management

- **Import/Export**: Transfer documents between systems
- **Database Backup**: Create backups of your database
- **Reset Database**: Reset the system to its initial state (use with caution)

### System Settings

Configure various system parameters:
- Storage limits
- QR code size
- Security settings
- Site title and description

## Security Notes

- Change the default admin password immediately after installation
- Regularly backup your database
- Restrict access to the admin area

## License

This project is available for use under open-source terms.

## Support

For issues or questions, please check the documentation or create an issue in the repository.
