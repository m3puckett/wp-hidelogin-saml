# Packaging Instructions for Hide WP Login SAML-Aware

This document provides detailed instructions for creating a distributable ZIP file for the Hide WP Login SAML-Aware WordPress plugin.

## Prerequisites

Before packaging, ensure you have:
- All plugin files updated and tested
- Version numbers updated in:
  - `hidelogin.php` (Plugin header - line 6)
  - `readme.txt` (Stable tag - line 9)
  - `README.md` (Changelog section)

## Directory Structure

The plugin ZIP file should contain a single directory named `wp-hidelogin-saml` with the following structure:

```
wp-hidelogin-saml/
├── hidelogin.php          (Main plugin file)
├── LICENSE                (GPL v3 license text)
├── README.md              (GitHub/general documentation)
└── readme.txt             (WordPress.org standard format)
```

## Files to Include

### Required Files

1. **hidelogin.php** - The main plugin file containing all functionality
2. **LICENSE** - The GNU General Public License v3.0 text
3. **README.md** - Comprehensive documentation for GitHub and general use
4. **readme.txt** - WordPress.org standard format readme
5. **vendor/** - Production dependencies (Plugin Update Checker library)
   - Must include `vendor/yahnis-elsts/plugin-update-checker/`
   - Must exclude `vendor/php-stubs/` (dev dependency only)

### Files to Exclude

Do NOT include the following in the distribution ZIP:

- `.git/` - Git repository data
- `.gitignore` - Git ignore file
- `.idea/` - IDE configuration files
- `.claude/` - Claude Code configuration
- `*.iml` - IntelliJ IDEA project files
- `PACKAGING.md` - This packaging guide
- `composer.json` - Excluded from final ZIP (used during build only)
- `composer.lock` - Not needed in distribution
- `vendor/php-stubs/` - WordPress stubs for IDE only (dev dependency)
- `node_modules/` - If any Node.js dependencies exist
- `.DS_Store` - macOS system files
- `Thumbs.db` - Windows thumbnail cache

**Important:** The `vendor/` directory contains TWO types of dependencies:
- **Production:** `vendor/yahnis-elsts/plugin-update-checker/` - MUST be included (enables automatic updates)
- **Development:** `vendor/php-stubs/wordpress-stubs/` - MUST be excluded (IDE autocomplete only)

The packaging script automatically handles this by running `composer install --no-dev`.

## Automated Packaging (Recommended)

### Method 1: Using Bash Script

Create a file named `package.sh` in the project root:

```bash
#!/bin/bash

# Configuration
PLUGIN_SLUG="wp-hidelogin-saml"
VERSION="2.1.0"  # Update this for each release
BUILD_DIR="build"
DIST_DIR="dist"

# Clean previous builds
rm -rf "$BUILD_DIR" "$DIST_DIR"

# Create build directory
mkdir -p "$BUILD_DIR/$PLUGIN_SLUG"

# Copy files
echo "Copying plugin files..."
cp hidelogin.php "$BUILD_DIR/$PLUGIN_SLUG/"
cp LICENSE "$BUILD_DIR/$PLUGIN_SLUG/"
cp README.md "$BUILD_DIR/$PLUGIN_SLUG/"
cp readme.txt "$BUILD_DIR/$PLUGIN_SLUG/"
cp composer.json "$BUILD_DIR/$PLUGIN_SLUG/"

# Install production dependencies (excludes dev dependencies)
echo "Installing production dependencies..."
cd "$BUILD_DIR/$PLUGIN_SLUG"
composer install --no-dev --no-interaction --optimize-autoloader
rm composer.json  # Remove composer.json from final package
cd ../..

# Create dist directory
mkdir -p "$DIST_DIR"

# Create ZIP file
echo "Creating ZIP file..."
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/$PLUGIN_SLUG-$VERSION.zip" "$PLUGIN_SLUG/"
cd ..

# Clean up build directory
rm -rf "$BUILD_DIR"

echo "Package created: $DIST_DIR/$PLUGIN_SLUG-$VERSION.zip"
echo "File size: $(du -h "$DIST_DIR/$PLUGIN_SLUG-$VERSION.zip" | cut -f1)"
```

Make it executable and run:

```bash
chmod +x package.sh
./package.sh
```

### Method 2: Using Make

Create a `Makefile` in the project root:

```makefile
PLUGIN_SLUG = wp-hidelogin-saml
VERSION = 2.1.0
BUILD_DIR = build
DIST_DIR = dist
PLUGIN_DIR = $(BUILD_DIR)/$(PLUGIN_SLUG)
ZIP_FILE = $(DIST_DIR)/$(PLUGIN_SLUG)-$(VERSION).zip

.PHONY: all clean build package

all: clean build package

clean:
	rm -rf $(BUILD_DIR) $(DIST_DIR)

build:
	mkdir -p $(PLUGIN_DIR)
	cp hidelogin.php $(PLUGIN_DIR)/
	cp LICENSE $(PLUGIN_DIR)/
	cp README.md $(PLUGIN_DIR)/
	cp readme.txt $(PLUGIN_DIR)/

package: build
	mkdir -p $(DIST_DIR)
	cd $(BUILD_DIR) && zip -r ../$(ZIP_FILE) $(PLUGIN_SLUG)/
	rm -rf $(BUILD_DIR)
	@echo "Package created: $(ZIP_FILE)"
	@du -h $(ZIP_FILE)

.DEFAULT_GOAL := all
```

Run with:

```bash
make
```

## Manual Packaging

If you prefer to package manually:

### Step 1: Create a Clean Working Directory

```bash
cd /path/to/wp-saml-hidelogin-fix
mkdir -p ../temp-packaging/wp-hidelogin-saml
```

### Step 2: Copy Required Files

```bash
cp hidelogin.php ../temp-packaging/wp-hidelogin-saml/
cp LICENSE ../temp-packaging/wp-hidelogin-saml/
cp README.md ../temp-packaging/wp-hidelogin-saml/
cp readme.txt ../temp-packaging/wp-hidelogin-saml/
```

### Step 3: Create the ZIP Archive

```bash
cd ../temp-packaging
zip -r wp-hidelogin-saml-2.1.0.zip wp-hidelogin-saml/
```

### Step 4: Verify the Package

```bash
# Check ZIP contents
unzip -l wp-hidelogin-saml-2.1.0.zip

# Expected output:
# wp-hidelogin-saml/hidelogin.php
# wp-hidelogin-saml/LICENSE
# wp-hidelogin-saml/README.md
# wp-hidelogin-saml/readme.txt
```

### Step 5: Clean Up

```bash
rm -rf wp-hidelogin-saml/
mv wp-hidelogin-saml-2.1.0.zip /path/to/final/location/
cd ..
rm -rf temp-packaging
```

## Testing the Package

Before distributing, test the packaged ZIP file:

### 1. Test Installation in WordPress

1. Set up a test WordPress installation
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file
4. Verify the plugin installs without errors
5. Activate the plugin
6. Verify the settings page appears at **Settings > SAML Hide Login**
7. Test basic functionality

### 2. Verify File Permissions

Ensure all files have correct permissions after extraction:

```bash
unzip wp-hidelogin-saml-2.1.0.zip -d test/
find test/wp-hidelogin-saml/ -type f -exec chmod 644 {} \;
find test/wp-hidelogin-saml/ -type d -exec chmod 755 {} \;
```

### 3. Check for Syntax Errors

```bash
php -l test/wp-hidelogin-saml/hidelogin.php
```

### 4. Verify README Files

Open both README.md and readme.txt to ensure:
- Version numbers are correct
- License information is accurate
- Installation instructions are clear
- All links work correctly

## Distribution Checklist

Before releasing a new version:

- [ ] Update version number in `hidelogin.php` (line 6)
- [ ] Update version number in `readme.txt` (Stable tag)
- [ ] Update changelog in both `README.md` and `readme.txt`
- [ ] Test the plugin in a clean WordPress installation
- [ ] Verify SAML functionality still works
- [ ] Check for PHP errors/warnings
- [ ] Verify the license is GPL v3
- [ ] Create the ZIP package
- [ ] Test installing the ZIP package in WordPress
- [ ] Create a Git tag for the release: `git tag v2.1.0`
- [ ] Push the tag to GitHub: `git push origin v2.1.0`
- [ ] Create a GitHub release with the ZIP file attached

## Naming Convention

Use the following naming convention for ZIP files:

```
wp-hidelogin-saml-{VERSION}.zip
```

Examples:
- `wp-hidelogin-saml-2.1.0.zip`
- `wp-hidelogin-saml-2.2.0.zip`
- `wp-hidelogin-saml-3.0.0.zip`

## Uploading to WordPress.org (Optional)

If you plan to submit to the WordPress.org plugin repository:

1. Create an account at https://wordpress.org/
2. Submit your plugin for review
3. Wait for approval (can take several weeks)
4. Once approved, use SVN to manage updates:

```bash
svn checkout https://plugins.svn.wordpress.org/wp-hidelogin-saml
cd wp-hidelogin-saml
# Copy files to trunk/
svn add trunk/*
svn commit -m "Version 2.1.0"
# Create a tag
svn cp trunk tags/2.1.0
svn commit -m "Tagging version 2.1.0"
```

## GitHub Release Process

1. Create and push a Git tag:

```bash
git tag -a v2.1.0 -m "Version 2.1.0"
git push origin v2.1.0
```

2. Go to GitHub repository: https://github.com/m3puckett/wp-hidelogin-saml
3. Click "Releases" > "Draft a new release"
4. Select the tag (v2.1.0)
5. Add release title: "Hide WP Login SAML-Aware v2.1.0"
6. Copy changelog from README.md
7. Attach the ZIP file
8. Click "Publish release"

## Troubleshooting

### ZIP File Too Large

If the ZIP file is unexpectedly large:

```bash
# Check ZIP contents and sizes
unzip -l wp-hidelogin-saml-2.1.0.zip | sort -k4 -rn

# Verify no unwanted files are included
```

### WordPress Won't Install the ZIP

Common issues:
- ZIP contains multiple directories instead of one
- Main plugin file is not in the root of the plugin directory
- Plugin headers are malformed
- File permissions are incorrect

### Plugin Doesn't Activate After Installation

Check:
- PHP syntax errors: `php -l hidelogin.php`
- WordPress minimum version requirements
- PHP minimum version requirements
- Missing dependencies

## Version Control

When releasing a new version:

```bash
# Commit all changes
git add hidelogin.php README.md readme.txt
git commit -m "Version 2.1.0 - Added auto-redirect to SAML"

# Create tag
git tag -a v2.1.0 -m "Version 2.1.0"

# Push to GitHub
git push origin main
git push origin v2.1.0
```

## Quick Reference Commands

```bash
# Create package (one-liner)
mkdir -p build/wp-hidelogin-saml && \
cp hidelogin.php LICENSE README.md readme.txt build/wp-hidelogin-saml/ && \
cd build && zip -r wp-hidelogin-saml-2.1.0.zip wp-hidelogin-saml/ && \
cd .. && mv build/wp-hidelogin-saml-2.1.0.zip . && rm -rf build

# Verify package
unzip -l wp-hidelogin-saml-2.1.0.zip

# Test PHP syntax
php -l hidelogin.php
```

## Support

For questions about packaging or distribution:
- GitHub Issues: https://github.com/m3puckett/wp-hidelogin-saml/issues
- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
