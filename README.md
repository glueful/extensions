# Glueful Extensions Repository

This repository contains the official extensions developed by the Glueful team for the Glueful platform. Each extension adds specific functionality to enhance your Glueful framework.

## Available Extensions

- **Admin** - Administration interface for managing Glueful applications
- **CloudflareAdapter** - Integration with Cloudflare CDN and security services
- **ComplianceManager** - Tools for managing regulatory compliance (GDPR, CCPA, HIPAA)
- **EmailNotification** - Email notification system with customizable templates
- **OAuthServer** - OAuth 2.0 server implementation for API authentication
- **SecurityScanner** - Comprehensive security scanning for code, dependencies, and APIs
- **SocialLogin** - Authentication via social providers (Google, Facebook, GitHub, Apple)

## Development Setup

To set up this extensions monorepo for development:

1. Clone this repository
2. Run the setup script to configure your development environment:

```bash
php setup.php
```

This will:
- Ask for your Glueful installation path
- Set up a shared .env file for all extensions
- Configure the monorepo's composer.json
- Create necessary bootstrap files

3. To set up individual extensions for development:

```bash
php setup.php ExtensionName
```

For example:
```bash
php setup.php EmailNotification
```

## Extension Structure

Each extension follows a standard structure:

```
ExtensionName/
├── extension.json      # Extension metadata and configuration
├── README.md           # Documentation
├── [Main PHP file]     # Primary extension class
├── config.php          # Configuration defaults
├── routes.php          # Route definitions (if applicable)
├── assets/             # Images, icons, etc.
├── screenshots/        # UI screenshots for documentation
└── ...                 # Other extension-specific files and directories
```

## Development

### Setup

1. Clone this repository:
   ```bash
   git clone https://github.com/glueful/extensions.git
   ```

2. Navigate to the extensions directory:
   ```bash
   cd extensions
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

### Creating a New Extension

1. Create a new directory for your extension in the `extensions/` folder:
   ```bash
   mkdir extensions/YourExtensionName
   ```

2. Create an `extension.json` file in your extension directory following the schema described in `extensions-json-guide.md`

3. Implement required PHP files for your extension:
   - Main extension class (e.g., `YourExtensionName.php`)
   - Configuration file (optional)
   - Routes definition (optional)

4. Add documentation:
   - README.md with extension description and usage instructions
   - Screenshots in the `screenshots/` directory (recommended)

### Extension Requirements

- Extensions must include a valid `extension.json` file with required metadata
- Extensions should follow Glueful coding standards
- Extensions should include proper documentation
- Extensions should handle errors gracefully

### Testing

When developing extensions locally:

1. You may need to copy your extensions to your local Glueful instance for testing
2. Use the `.gitignore` to control which files are tracked in the repository

### Publishing

Refer to the `github-marketplace-guide.md` for instructions on publishing extensions to the Glueful Extensions Marketplace.

## Contributing

1. Fork the repository
2. Create your feature branch: `git checkout -b feature/amazing-extension`
3. Commit your changes: `git commit -am 'Add amazing extension'`
4. Push to the branch: `git push origin feature/amazing-extension`
5. Submit a pull request

## License

This repository is licensed under the terms specified in the LICENSE file.