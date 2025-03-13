# One Click Project

**One Click Project** is a powerful Laravel package designed to streamline your development process by generating a complete project structure with a single Artisan command. It creates a model, migration, repository (with a base repository pattern), service layer, resource, controller, request classes, and API routes—all tailored to your specifications.

## Features

- **Automated Scaffolding**: Generate a full CRUD-ready structure for your Laravel project in minutes.
- **Repository Pattern**: Implements a base repository with interfaces and implementations for clean, reusable data access logic.
- **Service Layer**: Includes a service layer with dependency injection for business logic separation.
- **API Ready**: Generates API routes with middleware support (e.g., Sanctum authentication, CORS).
- **Customizable**: Allows you to define model attributes and custom repository methods interactively.
- **Image Handling**: Built-in support for image uploads, updates, and deletions via a reusable `ImageTrait`.
- **Response Helpers**: Standardized JSON responses via `ResponseTrait` for consistent API output.
- **Route Helper**: Includes a `RouteHelper` utility for dynamically including API route files.

## Installation

You can install the package via Composer:

```bash
composer require islamwalied/one-click-project
```

The package will automatically register its service provider and command thanks to Laravel's auto-discovery feature.

## Requirements

- PHP 8.0 or higher
- Laravel 10.x or 11.x
- Composer
- GD Library extension for PHP (`ext-gd`)

### Updating Composer Configuration

Add the GD extension requirement to your `composer.json`:

```json
"require": {
    "ext-gd": "*"
}
```

## Usage

Run the Artisan command to generate your project structure:

```bash
php artisan generate:project {ModelName}
```

Replace `{ModelName}` with the name of your model (e.g., `User`, `Post`).

### Example

```bash
php artisan generate:project Post
```

#### Interactive Prompts
1. **Model Attributes**: You'll be asked to specify column names and types (e.g., `string`, `integer`, `foreignId`) for the model's migration and fillable properties.
    - Example:
      ```
      Enter column name (or "done" to finish): title
      Select column type: string
      Enter column name (or "done" to finish): user_id
      Select column type: foreignId
      Enter column name (or "done" to finish): done
      ```
2. **Custom Methods**: Optionally add custom methods to the repository and service layers.
    - Example:
      ```
      Do you want to add a custom method in the repository? (yes/no): yes
      Enter method name (e.g., findByEmail): findBySlug
      Enter return type (default: mixed): string
      Enter parameters (e.g., string $email, int $id): string $slug
      Do you want to implement this method in the service? (yes/no): yes
      ```

#### Generated Structure
After running the command, you'll get:
- `app/Models/Post.php` (Model with fillable attributes and relationships)
- `database/migrations/...create_posts_table.php` (Migration with specified columns)
- `app/Repositories/Interfaces/PostRepository.php` & `app/Repositories/Implementation/PostRepositoryImpl.php` (Repository layer)
- `app/Services/Interfaces/PostService.php` & `app/Services/Implementation/PostServiceImpl.php` (Service layer)
- `app/Http/Resources/PostResource.php` (API resource)
- `app/Http/Controllers/PostController.php` (Controller with dependency injection)
- `app/Http/Requests/StorePostRequest.php` & `UpdatePostRequest.php` (Form request validation)
- `routes/api/post.php` (API routes)


#### **Verified Publishing Section**:
- To Publish The Required Files To Use All Features You Must Use Those Commands After Installing:
    - `php artisan vendor:publish --provider="IslamWalied\OneClickProject\OneClickProjectServiceProvider" --tag="helpers"`
    - `php artisan vendor:publish --provider="IslamWalied\OneClickProject\OneClickProjectServiceProvider" --tag="traits"`
    - `php artisan vendor:publish --provider="IslamWalied\OneClickProject\OneClickProjectServiceProvider" --tag="middleware"`
    - `php artisan vendor:publish --provider="IslamWalied\OneClickProject\OneClickProjectServiceProvider" --tag="logging"`


#### Post-Generation Steps
1. **Register Service Providers**: If not already registered, add `RepositoryServiceProvider` and `ServiceServiceProvider` to `bootstrap/app.php` (Laravel 10+):
   ```php
   ->withProviders([
       App\Providers\RepositoryServiceProvider::class,
       App\Providers\ServiceServiceProvider::class,
   ])
   ```
2. **Run Migrations**: Apply the generated migration:
   ```bash
   php artisan migrate
   ```
3. **Test the API**: Use tools like Postman to test the generated endpoints (e.g., `GET /api/v1/posts`).

## Configuration

No additional configuration is required out of the box. However, you can customize the generated files by modifying the package's generator classes if needed.

### Customizing Image Storage
The `ImageTrait` uses Laravel's `public` disk by default. To change this, update your `filesystems.php` config or extend the trait in your application.

### Customizing Logging
After publishing the logging files, you need to update your `config/logging.php` file to include the custom daily logger. To use it add this object to the end of the file:

```php
'daily_by_date' => [
    'driver' => 'custom',
    'via' => App\Logging\LogHandler::class,
    'level' => 'error',
],
```

## API Endpoints

Generated routes are prefixed with `v1/` and protected by `auth:sanctum` middleware:
- `GET /v1/{model}s` - List all resources
- `GET /v1/{model}s/{id}` - Show a resource
- `POST /v1/{model}s` - Create a resource
- `PATCH /v1/{model}s/{id}` - Update a resource
- `DELETE /v1/{model}s/{id}` - Delete a resource

## Notes

- **RouteHelper Dependency**: The package assumes a `RouteHelper` class exists to include API route files dynamically. If your project doesn't have this, you'll need to manually include the generated route files in `routes/api.php` or implement a custom `RouteHelper`.
- **Localization**: The package uses Laravel's `__()` helper for messages (e.g., `messages.post.index_success`). Define these in your `lang` files for full support.

## Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository.
2. Create a feature branch (`git checkout -b feature/your-feature`).
3. Commit your changes (`git commit -m "Add your feature"`).
4. Push to the branch (`git push origin feature/your-feature`).
5. Open a Pull Request.

## Issues

If you encounter any issues or have suggestions, please [open an issue](https://github.com/islamwalied/oneClickProject/issues) on GitHub.

## License

This package is open-sourced under the [MIT License](LICENSE).

## Credits

Developed by [Islam Walied](mailto:islam.walied96@gmail.com). Special thanks to the Laravel community for inspiration and support.