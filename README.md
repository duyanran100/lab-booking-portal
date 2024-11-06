## Requirements

- **Docker** and **Docker Compose** are required.

## Installing Composer Dependencies

You may install the application's dependencies by navigating to the application's directory and executing the following command. This command uses a small Docker container containing PHP and Composer to install the application's dependencies:

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php83-composer:latest \
    composer install --ignore-platform-reqs
```

## Start and Stop Apllication
```bash
./vendor/bin/sail up -d
./vendor/bin/sail stop
```

## Install packages
```bash
./vendor/bin/sail composer install
```

## Database Migration
```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan migrate:fresh --seed
```

## Generate App Key
```bash
./vendor/bin/sail artisan key:generate
```