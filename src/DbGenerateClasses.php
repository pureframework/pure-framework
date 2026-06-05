<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * Generates plain PHP row classes from SQL CREATE TABLE files.
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class DbGenerateClasses
{
    /**
     * Parses a SQL CREATE TABLE statement and extracts field names and column metadata.
     *
     * @return array{table: string, props: array<string, array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string}>}|false
     */
    public static function parseSqlToConfig(string $sql): array|false
    {
        $sql = str_replace("\r\n", "\n", $sql);
        $config = [];
        if (preg_match("/^CREATE TABLE[^`]+`([a-zA-Z_0-9]+)`[^(]+\(([^;]+)\)[^;]*;$/mis", $sql, $matched)) {
            $config['table'] = trim($matched[1]);
            if (preg_match_all('/\s*`([a-zA-Z0-9-_]+)`\s+([^,\n]+)/', $matched[2], $fields, PREG_SET_ORDER)) {
                foreach ($fields as $f) {
                    $definition = trim($f[2]);
                    if (str_starts_with($definition, '(')) {
                        continue;
                    }
                    if (preg_match('/^(PRIMARY|UNIQUE|KEY|CONSTRAINT|INDEX|FULLTEXT|SPATIAL)\b/i', $definition)) {
                        continue;
                    }
                    $columnName = $f[1];
                    $config['props'][$columnName] = self::parseColumnMeta($columnName, $definition);
                }

                return $config;
            }
        }

        return false;
    }

    /**
     * @param array{table: string, props: array<string, array>} $config
     * @return object{className: string, class: string}
     */
    public static function generateClass(array $config, bool $typed = false): object
    {
        $name = $config['table'];

        if (!$typed) {
            $cls = 'class ' . $name . " {\n";
            foreach ($config['props'] as $columnName => $meta) {
                $cls .= '  public $' . $columnName . " = '';\n";
            }
            $cls .= '}';
        } else {
            $cls = self::generateTypedClass($name, $config['props']);
        }

        $response = new \stdClass();
        $response->className = $name;
        $response->class = $cls;

        return $response;
    }

    /**
     * Full PHP file contents for one generated row class.
     */
    public static function buildClassFileContent(array $config, ?string $namespace = null, bool $typed = false): string
    {
        $response = self::generateClass($config, $typed);
        $content = '<' . "?php\n// GENERATED " . date('r') . "\n\n";

        if ($namespace !== null && $namespace !== '') {
            $content .= 'namespace ' . self::normalizeNamespace($namespace) . ";\n\n";
        }

        $content .= $response->class . "\n";

        return $content;
    }

    /**
     * Write one generated row class per SQL file (PSR-4 friendly).
     *
     * @return list<string> Generated class names
     */
    public static function generateToDirectory(
        string $sqlPath,
        string $outputDir,
        ?string $namespace = null,
        bool $force = true,
        bool $typed = false,
    ): array {
        if ($namespace !== null && $namespace !== '') {
            self::normalizeNamespace($namespace);
        }

        $sqlFiles = self::listSqlFiles($sqlPath);

        if (!$force && is_dir($outputDir)) {
            $newestSqlMtime = 0;
            foreach ($sqlFiles as $f) {
                $mtime = filemtime($f);
                if ($mtime !== false && $mtime > $newestSqlMtime) {
                    $newestSqlMtime = $mtime;
                }
            }

            $needsUpdate = false;
            foreach ($sqlFiles as $f) {
                $content = file_get_contents($f);
                if ($content === false) {
                    throw new \RuntimeException('Failed to read ' . $f);
                }
                $config = self::parseSqlFile($f, $content);
                if ($config === null) {
                    continue;
                }
                $outFile = Util::path($outputDir, $config['table'] . '.php');
                if (!is_file($outFile) || filemtime($outFile) === false || filemtime($outFile) < $newestSqlMtime) {
                    $needsUpdate = true;
                    break;
                }
            }

            if (!$needsUpdate) {
                return [];
            }
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            throw new \RuntimeException("Cannot create output directory: {$outputDir}");
        }

        $generatedClasses = [];

        foreach ($sqlFiles as $f) {
            $content = file_get_contents($f);
            if ($content === false) {
                throw new \RuntimeException('Failed to read ' . $f);
            }
            $config = self::parseSqlFile($f, $content);
            if ($config === null) {
                continue;
            }

            $outFile = Util::path($outputDir, $config['table'] . '.php');
            $fileContent = self::buildClassFileContent($config, $namespace, $typed);

            if (!file_put_contents($outFile, $fileContent)) {
                throw new \RuntimeException('Failed to write ' . $outFile);
            }

            $generatedClasses[] = $config['table'];
        }

        return $generatedClasses;
    }

    /**
     * @return list<string> SQL file paths
     */
    public static function listSqlFiles(string $sqlPath): array
    {
        $sqlFiles = [];
        $d = dir($sqlPath);
        if ($d === false) {
            throw new \RuntimeException("Cannot read SQL path: {$sqlPath}");
        }
        while (false !== ($entry = $d->read())) {
            if (substr($entry, -4) === '.sql') {
                $sqlFiles[] = Util::path($sqlPath, $entry);
            }
        }
        $d->close();
        sort($sqlFiles);
        return $sqlFiles;
    }

    /**
     * Generates a PHP cache file from SQL schema files.
     *
     * @return list<string> Generated class names
     */
    public static function generateFromPath(
        string $sqlPath,
        string $cachePath,
        bool $force = true,
        bool $typed = false,
    ): array {
        $sqlFiles = self::listSqlFiles($sqlPath);

        if (!$force && file_exists($cachePath)) {
            $cacheModified = filemtime($cachePath);
            $needsUpdate = false;
            foreach ($sqlFiles as $f) {
                if (filemtime($f) >= $cacheModified) {
                    $needsUpdate = true;
                    break;
                }
            }
            if (!$needsUpdate) {
                return [];
            }
        }

        $generatedClasses = [];
        $generated = '<' . "?php\n// GENERATED " . date('r') . "\n\n";

        foreach ($sqlFiles as $f) {
            $content = file_get_contents($f);
            if ($content === false) {
                throw new \RuntimeException('Failed to read ' . $f);
            }
            $tmp = self::parseSqlFile($f, $content);
            if ($tmp === null) {
                continue;
            }
            $response = self::generateClass($tmp, $typed);
            $generated .= '// ' . basename($f) . "\n" . $response->class . "\n\n";
            $generatedClasses[] = $response->className;
        }

        if (!file_put_contents($cachePath, $generated)) {
            throw new \RuntimeException('Failed to write ' . $cachePath);
        }

        return $generatedClasses;
    }

    /**
     * True when a `.sql` basename starts with digits and underscore (e.g. `00_database.sql`, `001_add_column.sql`).
     * These migration/setup files are skipped by codegen when they are not CREATE TABLE statements.
     */
    public static function isMigrationSqlFile(string $filename): bool
    {
        return (bool) preg_match('/^\d+_/', $filename);
    }

    /**
     * @return array{table: string, props: array<string, array>}|null Null when file is skipped (migration prefix)
     */
    private static function parseSqlFile(string $path, string $content): ?array
    {
        $config = self::parseSqlToConfig($content);
        if ($config !== false) {
            return $config;
        }

        if (self::isMigrationSqlFile(basename($path))) {
            return null;
        }

        throw new \RuntimeException('Failed to parse ' . basename($path));
    }

    /**
     * @return array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string}
     */
    private static function parseColumnMeta(string $columnName, string $definition): array
    {
        $def = strtolower(preg_replace('/\s+/', ' ', $definition) ?? $definition);
        $meta = [
            'name' => $columnName,
            'sqlType' => 'varchar',
            'nullable' => true,
        ];

        if (preg_match('/^([a-z]+)/', $def, $typeMatch)) {
            $meta['sqlType'] = $typeMatch[1];
        }

        if (str_contains($def, 'unsigned')) {
            $meta['unsigned'] = true;
        }

        if (preg_match('/\bnot\s+null\b/', $def)) {
            $meta['nullable'] = false;
        } elseif (preg_match('/\bnull\b/', $def)) {
            $meta['nullable'] = true;
        }

        if (str_contains($def, 'auto_increment')) {
            $meta['autoIncrement'] = true;
        }

        if (preg_match('/default\s+(current_timestamp|now\(\))/i', $def)) {
            $meta['defaultExpr'] = 'CURRENT_TIMESTAMP';
        } elseif (preg_match("/default\s+'([^']*)'/i", $def, $defaultMatch)) {
            $meta['defaultExpr'] = $defaultMatch[1];
        } elseif (preg_match('/default\s+(\d+)/i', $def, $defaultMatch)) {
            $meta['defaultExpr'] = $defaultMatch[1];
        }

        if ($columnName === 'id') {
            $meta['pk'] = true;
            $meta['autoIncrement'] = $meta['autoIncrement'] ?? true;
            $meta['nullable'] = false;
        }

        if ($columnName === 'created' && !isset($meta['defaultExpr'])) {
            $meta['defaultExpr'] = 'CURRENT_TIMESTAMP';
        }

        if ($columnName === 'updated' && !isset($meta['defaultExpr'])) {
            $meta['defaultExpr'] = 'CURRENT_TIMESTAMP';
        }

        return $meta;
    }

    /**
     * @param array<string, array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string}> $props
     */
    private static function generateTypedClass(string $tableName, array $props): string
    {
        $docLines = [" * Generated row class for table `{$tableName}`."];
        $insertSkip = [];

        foreach ($props as $columnName => $meta) {
            $docLines[] = ' * @property ' . self::phpTypeForColumn($meta) . ' $' . $columnName
                . self::docSuffixForColumn($meta);
            if (self::shouldSkipOnInsert($meta)) {
                $insertSkip[] = $columnName;
            }
        }

        $cls = "/**\n" . implode("\n", $docLines) . "\n */\n";
        $cls .= 'class ' . $tableName . " {\n";

        $uuidColumn = $tableName . '_uuid';
        if (array_key_exists($uuidColumn, $props)) {
            $cls .= "  public static string \$uuidProperty = '{$uuidColumn}';\n\n";
        }

        if ($insertSkip !== []) {
            $encoded = array_map(static fn (string $col): string => "'{$col}'", $insertSkip);
            $cls .= "  /** @var list<string> Columns omitted on insert (auto-increment / DB defaults) */\n";
            $cls .= '  public static array $insertSkip = [' . implode(', ', $encoded) . "];\n\n";
        }

        foreach ($props as $columnName => $meta) {
            $phpType = self::phpTypeForColumn($meta);
            $default = self::defaultLiteralForColumn($phpType, $meta);
            $cls .= "  public {$phpType} \${$columnName} = {$default};\n";
        }

        $cls .= '}';

        return $cls;
    }

    /**
     * @param array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string} $meta
     */
    private static function phpTypeForColumn(array $meta): string
    {
        $sqlType = $meta['sqlType'];
        $nullable = $meta['nullable'] ?? false;

        $baseType = match ($sqlType) {
            'int', 'tinyint', 'smallint', 'mediumint', 'bigint' => 'int',
            default => 'string',
        };

        if ($nullable) {
            return '?' . $baseType;
        }

        return $baseType;
    }

    /**
     * @param array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string} $meta
     */
    private static function defaultLiteralForColumn(string $phpType, array $meta): string
    {
        if (str_starts_with($phpType, '?')) {
            return 'null';
        }

        if ($phpType === 'int') {
            return '0';
        }

        return "''";
    }

    /**
     * @param array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string} $meta
     */
    private static function docSuffixForColumn(array $meta): string
    {
        $parts = [];

        if (!empty($meta['pk'])) {
            $parts[] = 'primary key';
        }

        if (!empty($meta['autoIncrement'])) {
            $parts[] = 'auto-increment';
        }

        if (!empty($meta['defaultExpr'])) {
            $parts[] = 'default ' . $meta['defaultExpr'];
        }

        if ($parts === []) {
            return '';
        }

        return ' (' . implode('; ', $parts) . ')';
    }

    /**
     * @param array{name: string, sqlType: string, nullable: bool, unsigned?: bool, autoIncrement?: bool, pk?: bool, defaultExpr?: string} $meta
     */
    private static function shouldSkipOnInsert(array $meta): bool
    {
        if (!empty($meta['autoIncrement'])) {
            return true;
        }

        $name = $meta['name'];
        if (!empty($meta['defaultExpr']) && in_array($name, ['created', 'updated'], true)) {
            return true;
        }

        return false;
    }

    private static function normalizeNamespace(string $namespace): string
    {
        $namespace = trim($namespace, "\\ \t\n\r\0\x0B");
        if ($namespace === '') {
            throw new \InvalidArgumentException('Namespace must not be empty when provided.');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $namespace)) {
            throw new \InvalidArgumentException("Invalid namespace: {$namespace}");
        }

        return $namespace;
    }
}
