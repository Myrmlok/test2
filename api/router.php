<?php
require_once __DIR__ . '/config.php';

class Router {
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '') {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, callable $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    public function patch(string $path, callable $handler): void {
        $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable $handler): void {
        $pattern = $this->basePath . $path;
        $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function run(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Маршрут не найден']);
    }
}

class Request {
    public static function getJson(): ?array {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return null;
        }
        return json_decode($raw, true);
    }

    public static function query(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
}

class Response {
    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    public static function noContent(): void {
        http_response_code(204);
    }

    public static function error(string $message, int $status = 400): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }
}
