# barlito/php-starter

[![Starter workflow](https://github.com/barlito/php-starter/actions/workflows/symfony_starter.yaml/badge.svg?branch=master)](https://github.com/barlito/php-starter/actions/workflows/symfony_starter.yaml)

A production-ready Symfony starter template featuring FrankenPHP, Docker Swarm deployment, and comprehensive code quality tools.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Quick Start](#quick-start)
- [Available Commands](#available-commands)
- [Code Quality](#code-quality)
- [Testing](#testing)
- [CI/CD](#cicd)
- [Architecture](#architecture)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)

## Features

- **FrankenPHP** (PHP 8.3): Modern PHP application server built on Caddy
- **Docker Swarm**: Production-ready orchestration with Traefik integration
- **Make + Castor**: Dual task automation system for flexibility
- **Code Quality Tools**: PHP CS Fixer, PHPCS, PHPMD pre-configured
- **Testing Suite**: PHPUnit and Behat ready to use
- **Reusable Make Rules**: Git submodule with common Symfony tasks
- **CI/CD Ready**: GitHub Actions workflow included

## Requirements

### System Requirements
- **Docker** (with Swarm mode for development deployment)
- **Make**
- **[Castor](https://castor.jolicode.com/)** - PHP task runner

### External Dependencies
- **[barlito/traefik-base](https://github.com/barlito/traefik-base)** - Traefik stack must be running for routing
- **[barlito/php-make-rules](https://github.com/barlito/php-make-rules)** - Included as git submodule

### Installing Castor
```bash
# macOS
brew install castor

# Linux
curl -Ls https://github.com/jolicode/castor/releases/latest/download/castor-linux-amd64 -o /usr/local/bin/castor
chmod +x /usr/local/bin/castor

# Or via Composer
composer global require jolicode/castor
```

## Quick Start

### 1. Clone and Initialize

```bash
# Clone the repository
git clone https://github.com/barlito/php-starter.git my-project
cd my-project

# Remove existing git history and start fresh
rm -rf .git
git init

# Initialize submodules
git submodule update --init --recursive
```

> **Note**: The Docker container runs with UID/GID 1000 by default. If your user has a different UID/GID and you experience permission issues, export your UID/GID before deploying:
> ```bash
> export USER_ID=$(id -u)
> export GROUP_ID=$(id -g)
> ```

### 2. Configure Your Stack

```bash
# Set your stack name (updates Makefile, docker-compose.yml, castor.php)
castor barlito:castor:set-stack-name my_stack_name
```

### 3. Deploy and Install Symfony

```bash
# Deploy the Docker stack
make docker.deploy

# Install Symfony skeleton
make symfony.install
```

Your Symfony application is now running! Access it at the URL configured in your Traefik setup (default: `starter.local.barlito.fr`).

### 4. Install Development Tools (Optional)

```bash
# Install barlito/utils package (contains config for quality tools)
make package.barlito_utils.install

# Install code quality tools
make cs_fixer.install
make phpcs.install
make phpmd.install

# Install testing frameworks
make phpunit.install
make behat.install
make behat.init
```

## Available Commands

### Docker & Stack Management

```bash
# Development
make docker.deploy              # Deploy stack with Docker Swarm
make docker.bash                # Access PHP container shell
make docker.command args="..."  # Run command in PHP container

# Full deployment (composer, DB, migrations, fixtures)
make deploy

# CI environment
make docker.deploy.ci           # Deploy with docker-compose for CI
make docker.undeploy.ci         # Stop and remove CI containers

# Production
make docker.deploy.prod         # Deploy production stack
```

### Symfony Operations

```bash
make symfony.install            # Install Symfony skeleton
make symfony.security_check     # Run security vulnerability check
```

### Composer

```bash
make composer.command args="require vendor/package"
make composer.command args="update"
```

### Database (Doctrine)

```bash
# Available in make/app/doctrine.mk
make doctrine.database.create
make doctrine.migration.migrate
# See make/app/doctrine.mk for full list
```

### Node/Asset Management

```bash
# Available in make/app/node.mk
# See submodule for Node.js related commands
```

## Code Quality

The project uses configurations from `vendor/barlito/utils/config/` for all quality tools.

### Check Code Style

```bash
# Run all style checks at once
make check_style

# Individual checks
make phpcs                      # PHP CodeSniffer
make phpmd                      # PHP Mess Detector
make cs_fixer.dry_run           # Preview PHP CS Fixer changes
```

### Fix Code Style

```bash
make cs_fixer                   # Auto-fix code style issues
```

### Custom Options

```bash
# Pass custom options to PHP CS Fixer
make cs_fixer CSFIXER_OPT="--verbose"
```

## Testing

### PHPUnit

```bash
make phpunit.install            # Install PHPUnit
make phpunit                    # Run tests
```

### Behat

```bash
make behat.install              # Install Behat
make behat.init                 # Initialize Behat configuration
make behat                      # Run Behat scenarios
```

## CI/CD

The project includes a GitHub Actions workflow (`.github/workflows/symfony_starter.yaml`) that:

1. Deploys the stack in CI mode
2. Installs Symfony and all development tools
3. Runs all code quality checks (phpcs, phpmd, php-cs-fixer)
4. Executes test suites (PHPUnit, Behat)
5. Cleans up the environment

The workflow runs on every push and demonstrates the complete setup sequence.

## Architecture

### Dual Build System

The project uses both **Make** and **Castor** for task automation:

- **Make**: Primary task runner, imports reusable rules from the `make/` submodule
- **Castor**: Handles project-specific tasks (e.g., stack name configuration)

### Docker Stack

- **FrankenPHP**: Modern PHP 8.3 server based on Caddy with live reload in dev mode
- **Docker Swarm**: Orchestration for development (uses `docker stack deploy`)
- **Traefik Integration**: HTTP/HTTPS routing via external `traefik_traefik_proxy` network
- **Volumes**: Persistent storage for Caddy data and configuration

### Make Rules Organization

Rules are organized in the `make/` submodule:

- **`make/app/`**: Application-specific rules (Symfony, Doctrine, Composer, testing, code style)
- **`make/stack/`**: Infrastructure rules (Docker, deployment)

### Configuration

Stack configuration is centralized in three files:
- `Makefile`: Stack variables and imports
- `castor.php`: Castor context with environment variables
- `docker-compose.yml`: Service definitions and Traefik labels

Use `castor barlito:castor:set-stack-name` to update all files at once.

## Troubleshooting

### Traefik Network Not Found

**Error**: `network traefik_traefik_proxy not found`

**Solution**: Ensure [barlito/traefik-base](https://github.com/barlito/traefik-base) is running:
```bash
# Check if Traefik network exists
docker network ls | grep traefik

# If not, deploy traefik-base first
```

### Container Not Starting

**Error**: Container exits immediately or restarts

**Solution**: Check container logs:
```bash
# Find your container
docker ps -a | grep starter_php

# View logs
docker logs <container_id>
```

### Submodule Not Initialized

**Error**: `make: *** No rule to make target 'make/entrypoint.mk'`

**Solution**: Initialize git submodules:
```bash
git submodule update --init --recursive
```

### Permission Issues

**Error**: Permission denied when accessing files in `var/` or other directories

**Solution**: The container runs with UID/GID 1000 by default. If your user has a different UID/GID:

```bash
# Check your UID/GID
id -u  # Should return your user ID
id -g  # Should return your group ID

# If different from 1000, export your UID/GID and rebuild:
export USER_ID=$(id -u)
export GROUP_ID=$(id -g)
make docker.deploy

# Optional: Add to your shell profile (~/.bashrc or ~/.zshrc) to persist:
echo 'export USER_ID=$(id -u)' >> ~/.bashrc
echo 'export GROUP_ID=$(id -g)' >> ~/.bashrc
source ~/.bashrc
```

## Roadmap

- [ ] Add PHPStan for static analysis
- [ ] Add Rector for automated refactoring
- [ ] Add disabled GitHub workflow for deployment examples
- [ ] Add database service configuration examples

## Contributing

This is a starter template. Fork it, customize it, make it yours!

## License

This project structure is provided as-is for creating new Symfony applications.

## Related Projects

- [barlito/traefik-base](https://github.com/barlito/traefik-base) - Traefik proxy stack
- [barlito/php-make-rules](https://github.com/barlito/php-make-rules) - Reusable Make rules
- [barlito/utils](https://github.com/barlito/utils) - Shared configuration files
