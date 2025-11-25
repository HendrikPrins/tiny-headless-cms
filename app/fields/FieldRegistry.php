<?php
require __DIR__ . '/FieldType.php';

class FieldRegistry {
    private static array $types = [];

    public static function register(FieldType $type): void {
        self::$types[$type->getName()] = $type;
    }

    public static function get(string $name): ?FieldType {
        return self::$types[$name] ?? null;
    }

    public static function getAll(): array {
        return self::$types;
    }

    public static function getTypeNames(): array {
        return array_keys(self::$types);
    }

    public static function loadFieldTypes(string $directory): void {
        if (!is_dir($directory)) {
            trigger_error("Field type directory '{$directory}' not found.", E_USER_WARNING);
            return;
        }

        foreach (glob($directory . '/*.php') as $file) {
            if (in_array($file, [__FILE__, __DIR__ . '/FieldType.php'])) {
                continue; // Skip base class and registry file
            }
            try {
                require_once $file;

                $className = basename($file, '.php');

                if (!class_exists($className)) {
                    trigger_error("Class '{$className}' not found in file '{$file}'.", E_USER_WARNING);
                    continue;
                }

                if (!is_subclass_of($className, FieldType::class)) {
                    trigger_error("Class '{$className}' does not extend FieldType.", E_USER_WARNING);
                    continue;
                }

                $instance = new $className();
                self::register($instance);
            } catch (\Throwable $e) {
                trigger_error("Error loading field type from '{$file}': " . $e->getMessage(), E_USER_WARNING);
            }
        }
    }

    public static function isValidType(string $type)
    {
        return isset(self::$types[$type]);
    }
}
