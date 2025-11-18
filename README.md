# Ovimo

Relevant infos here.

## Hetzner server

### PHP settings
1. Login as user forge
2. su root
3. sudo nano /etc/php/8.3/fpm/pool.d/www.conf
4. Set request_terminate_timeout to 600 seconds (600)
5. sudo systemctl restart php8.3-fpm

### Nginx settings

In nginx general site config add these lines:

    fastcgi_read_timeout 600;
    fastcgi_send_timeout 600;
    fastcgi_connect_timeout 600;

## Database seeder

1. UserSeeder (database/seeders/UserSeeder.php) - Creates the user and their team
2. SourceSeeder (database/seeders/SourceSeeder.php) - Creates all 8 sources
3. PostSeeder (database/seeders/PostSeeder.php) - Creates all 639 posts from the exported data
4. DatabaseSeeder - Updated to call all three seeders in order

The posts data is stored in database/seeders/data/posts.php.

To seed a fresh database, run:
php artisan migrate:fresh --seed

Or to just run seeders (without resetting migrations):
php artisan db:seed
