<?php
class FieldRegistry {
    private static array $types = [];

    public static function register(FieldType $type): void {
        self::$types[$type->getName()] = $type;
    }

    public static function get(string $name): ?FieldType {
        return self::$types[$name] ?? null;
    }

    public static function getTypeNames(): array {
        return array_keys(self::$types);
    }

    function loadFieldTypes(string $directory, string $namespace): void {
        foreach (glob($directory . '/*.php') as $file) {
            require_once $file; // ensure class is loaded

            // Derive class name from filename
            $className = $namespace . '\\' . basename($file, '.php');

            if (class_exists($className) && is_subclass_of($className, FieldType::class)) {
                // Instantiate once and register
                $instance = new $className();
                FieldRegistry::register($instance);
            }
        }
    }
}

require_once __DIR__ . '/FieldType.php';
require_once __DIR__ . '/StringFieldType.php';
require_once __DIR__ . '/IntegerFieldType.php';
require_once __DIR__ . '/DecimalFieldType.php';
require_once __DIR__ . '/BooleanFieldType.php';
FieldRegistry::register(new StringFieldType());
FieldRegistry::register(new IntegerFieldType());
FieldRegistry::register(new DecimalFieldType());
FieldRegistry::register(new BooleanFieldType());
