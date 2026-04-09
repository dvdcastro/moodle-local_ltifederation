# local_ltifederation — Moodle LTI Federation Plugin

[![Moodle Plugin CI](https://github.com/dvdcastro/moodle-local-ltifederation/actions/workflows/ci.yml/badge.svg)](https://github.com/dvdcastro/moodle-local-ltifederation/actions/workflows/ci.yml)
[![Static Analysis](https://github.com/dvdcastro/moodle-local-ltifederation/actions/workflows/static.yml/badge.svg)](https://github.com/dvdcastro/moodle-local-ltifederation/actions/workflows/static.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

A Moodle local plugin that enables a **consumer** Moodle site to discover and register LTI 1.3 tools published by one or more **provider** Moodle sites, forming a federated LTI network.

## Features

- Register remote Moodle sites as LTI providers
- Fetch and browse the remote tool catalog via web service
- One-click LTI 1.3 tool registration from the catalog
- Automatic catalog sync via scheduled tasks
- Registration state tracking (none / pending / registered / error)
- Encrypted web service token storage

## Requirements

| Requirement | Minimum |
|---|---|
| Moodle | 4.5 (requires 4.4+ core, tested on 4.4 and 4.5) |
| PHP | 8.1 |
| Database | PostgreSQL 12+ or MariaDB 10+ |

## Installation

### Option A: Git clone (recommended for development)

```bash
cd /path/to/moodle
git clone https://github.com/dvdcastro/moodle-local-ltifederation local/ltifederation
php admin/cli/upgrade.php
```

### Option B: Download zip

1. Download the latest release zip from the [Releases page](https://github.com/dvdcastro/moodle-local-ltifederation/releases).
2. In your Moodle site, go to **Site Administration > Plugins > Install plugins**.
3. Upload the zip and follow the on-screen instructions.

## Configuration

After installation, navigate to **Site Administration > Plugins > Local plugins > LTI Federation**.

### Consumer role (this site consumes remote tools)

1. Go to **Site Administration > Plugins > Local plugins > LTI Provider connections**.
2. Click **Add provider** and supply:
   - **Label**: a friendly name for the remote Moodle site
   - **Provider URL**: base URL of the remote Moodle (e.g. `https://provider.example.com`)
   - **Web service token**: a valid token from the remote site with access to `local_ltifederation_get_tool_catalog`
3. Save. The plugin will sync the tool catalog on the next cron run (or click **Sync now**).
4. Browse the catalog and click **Register** to initiate LTI 1.3 dynamic registration for any tool.

### Provider role (remote site that publishes tools)

The provider side requires the `local_ltifederation` plugin installed on the remote Moodle as well. The remote admin creates a web service token scoped to `local_ltifederation_get_tool_catalog` and shares it with the consumer.

## Scheduled Tasks

| Task | Default schedule | Description |
|---|---|---|
| Sync all providers | Every hour | Fetches the tool catalog from all configured providers |
| Sync tools | Every 15 minutes | Processes queued catalog sync jobs |
| Cleanup draft registrations | Daily | Removes stale draft LTI registrations |

## Running Tests

### PHPUnit

```bash
# From Moodle root, using Docker (as configured in this project)
./run-docker-exec.sh vendor/bin/phpunit --testsuite local_ltifederation_testsuite
```

### Behat

```bash
./run-docker-exec.sh vendor/bin/behat --config /path/to/moodledata/behat/behat.yml \
  --tags @local_ltifederation
```

## CI/CD

This plugin uses [moodle-plugin-ci](https://github.com/moodlehq/moodle-plugin-ci) for automated testing on GitHub Actions:

- **ci.yml**: Full matrix (PHP 8.1/8.2, Moodle 4.4/4.5, PostgreSQL/MariaDB) — runs on push/PR to main
- **static.yml**: Fast static checks (PHP lint, PHPCS, version.php validation) — runs on every push
- **release.yml**: Builds and publishes a release zip — triggered by `v*` tags

> **Note**: Automatic publishing to moodle.org/plugins is not yet configured. Release zips are attached to GitHub Releases.

## License

This plugin is licensed under the [GNU General Public License v3.0](LICENSE).

Copyright 2026 David Castro
