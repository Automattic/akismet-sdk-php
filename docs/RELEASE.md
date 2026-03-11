# Publishing Akismet PHP SDK to GitHub & Packagist

## Prerequisites

- Admin access to the [Automattic](https://github.com/Automattic) GitHub org
- A [Packagist](https://packagist.org) account (ideally an Automattic org account)
- Local clone of the enterprise repo at `github.a8c.com`

## First-Time Setup

### Step 1: Create the Public Repository

1. Go to https://github.com/organizations/Automattic/repositories/new
2. Create the repo with these settings:
   - **Name:** `akismet-sdk-php`
   - **Description:** `Official PHP SDK for the Akismet spam protection service`
   - **Visibility:** Public
   - **Do NOT** initialize with README, .gitignore, or license (we're pushing an existing repo)

### Step 2: Add the Public Remote

From your local clone:

```bash
git remote add public git@github.com:Automattic/akismet-sdk-php.git
```

Verify both remotes:

```bash
git remote -v
# origin    git@github.a8c.com:Automattic/akismet-sdk-php.git
# public    git@github.com:Automattic/akismet-sdk-php.git
```

### Step 3: Push trunk to the Public Repo

```bash
git push public trunk
```

This pushes only the `trunk` branch. Work-in-progress branches on the enterprise repo stay private.

### Step 4: Configure the Public Repo Settings

On https://github.com/Automattic/akismet-sdk-php/settings:

1. **Default branch:** Set to `trunk`
2. **Branch protection** on `trunk`:
   - Require PR reviews before merging
   - Require status checks to pass (if CI is set up)
3. **Features:** Enable Issues, disable Wiki (docs are on akismet.com)

### Step 5: Preflight Validation

Run all quality checks and verify the distribution archive before tagging:

```bash
composer install
composer check

# Run integration tests if you have an API key
AKISMET_API_KEY=your-key composer test:integration

# Verify only the expected files ship in the archive
git archive --format=tar HEAD | tar -tf - | sort
# Should contain only: CHANGELOG.md, LICENSE, README.md, composer.json, src/**
```

### Step 6: Finalize the Changelog and Tag the Release

```bash
# Write the changelog for 1.0.0
vendor/bin/changelogger write --release-version=1.0.0

# Commit the changelog
git add CHANGELOG.md
git commit -m "Prepare 1.0.0 release"

# Tag the release
git tag v1.0.0

# Push to both remotes
git push origin trunk && git push origin v1.0.0
git push public trunk && git push public v1.0.0
```

### Step 7: Create a GitHub Release

1. Go to https://github.com/Automattic/akismet-sdk-php/releases/new
2. **Tag:** `v1.0.0`
3. **Title:** `v1.0.0`
4. Copy the relevant section from `CHANGELOG.md` into the description
5. Publish

### Step 8: Submit to Packagist

1. Go to https://packagist.org/packages/submit
2. Enter: `https://github.com/Automattic/akismet-sdk-php`
3. Packagist will crawl the repo and index the package as `automattic/akismet-sdk`

### Step 9: Set Up Auto-Update Webhook

So Packagist picks up new releases automatically:

1. Go to your Packagist profile > Settings > API Token
2. On the **public** GitHub repo, go to Settings > Webhooks > Add webhook:
   - **URL:** `https://packagist.org/api/github?username=YOUR_PACKAGIST_USERNAME`
   - **Content type:** `application/json`
   - **Secret:** Your Packagist API token
   - **Events:** Just the push event

Alternatively, use the [Packagist GitHub App](https://github.com/apps/packagist) which is simpler — install it and grant access to the repo.

### Step 10: Verify

```bash
# Wait ~5 minutes, then verify the package is live
composer show automattic/akismet-sdk --all

# Test installation in a fresh directory
mkdir /tmp/akismet-test && cd /tmp/akismet-test
composer init --no-interaction
composer require automattic/akismet-sdk
```

## Ongoing Release Workflow

For future releases:

```bash
# Work happens on enterprise repo (origin) as usual
# When ready to release:

vendor/bin/changelogger write --release-version=X.Y.Z
git add CHANGELOG.md
git commit -m "Prepare X.Y.Z release"
git tag vX.Y.Z

# Push to both remotes
git push origin trunk && git push origin vX.Y.Z
git push public trunk && git push public vX.Y.Z
```

Only `trunk` and version tags are pushed to the public repo. Feature branches, review branches, and in-progress work stay on the enterprise repo.
