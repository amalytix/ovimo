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

### Scheduler setting

php /home/forge/ovimo.ai/current/artisan schedule:run

### Worker setting

[program:worker-606160]
directory=/home/forge/ovimo.ai/current/
command=php8.3 /home/forge/ovimo.ai/current/artisan queue:work database --sleep=3 --tries=3 --max-time=3600 --quiet
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
user=forge
numprocs=4
redirect_stderr=true
stdout_logfile=/home/forge/.forge/worker-606160.log
stdout_logfile_maxbytes=5MB
stdout_logfile_backups=3
stopwaitsecs=15
stopasgroup=true
killasgroup=true


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
