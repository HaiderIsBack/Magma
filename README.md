# Magma PHP Framework

Magma is a lightweight, fast, and developer-friendly PHP framework engineered for building modern web applications and APIs with minimal configuration and maximum performance.

## Features

- ⚡ Lightning Fast: Minimal overhead with optimized routing and a robust dependency injection container.

- 🛡️ Secure by Default: Built-in protection against CSRF, XSS, and SQL Injection vulnerabilities.

- 🧩 Modular Architecture: Bring only the components you need for your specific stack.

- 🔌 Eloquent-style ORM: Expressive and powerful database query builder and ORM.

- 🛣️ Flexible Routing: Full support for parameter constraints, route groups, and middleware pipelines.

- 📦 Composer Ready: Seamless installation and dependency management via Packagist (rolog/magma).

## Requirements

Ensure your server or local environment meets the following minimum requirements:

- PHP >= 8.0

- Composer

- PDO PHP Extension

## Installation

You can install Magma into your project via Composer:

```PHP
composer require rolog/magma
```

## Quick Start

1. Create your entry point (public/index.php)

```PHP
<?php

define('APP_ROOT', __DIR__);

require dirname(APP_ROOT) . '/vendor/autoload.php';

use Magma\Core\Application;
use Magma\Routing\Dispatcher;
use Magma\Http\Request;

$router = Application::run();

$request = Request::fromGlobals();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';

$match = $router->match($method, $uri);

if ($match === null) {
    http_response_code(404);
    echo '404 - Page Not Found';
    exit;
}

$dispatcher = new Dispatcher();
$dispatcher->dispatch($match, $request);
```

2. Configure your local development server

You can use PHP's built-in development server to spin up your application locally:

```cmd
php -S localhost:8000 -t public
```

Open your browser and navigate to http://localhost:8000.

## Contributing

Contributions are always welcome! Please feel free to submit a Pull Request or open an issue on GitHub.

Fork the repository

Create your feature branch (git checkout -b feature/AmazingFeature)

Commit your changes (git commit -m 'Add some AmazingFeature')

Push to the branch (git push origin feature/AmazingFeature)

Open a Pull Request

## License

Magma is open-source software licensed under the MIT License.
