# Ovimo

Relevant infos here.

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
