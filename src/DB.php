<?php

namespace PureFramework;

/**
 * Pure Framework
 *
 * @copyright Copyright (c) 2014-2026 Jonathan Sharp
 * @license   MIT https://opensource.org/licenses/MIT
 * @link      https://github.com/pureframework/pure-framework
 */

class DB
{
    // ------------------------------------------------------------
    // Utility methods that can be overridden
    // ------------------------------------------------------------
    public static function log($msg)
    {
        // Noop
    }

    public static function decodeRows($rows)
    {
        return $rows;
    }

    public static function encodeData($data, $insert = false)
    {
        return $data;
    }

    public static function encodeWhereData($where)
    {
        return $where;
    }

    /**
     * mapTableClass() given a SQL table, returns the appropriate 
     * PHP Class. It may be passed an array in the format of array('table', 'php_class')
     */
    public static function mapTableClass($table)
    {
        $class = 'stdClass';
        if (is_array($table)) {
            $class = $table[1];
            $table = $table[0];
        } else if (class_exists($table)) {
            $class = $table;
        }
        return [$table, $class];
    }

    public static function encodeHexLiteral($hex)
    {
        return "x'" . str_replace('-', '', $hex) . "'";
    }

    /**
     * Build a row object from data. Does not auto-populate UUID unless $populateUuidProperty is a string.
     *
     * @param string|false|null $populateUuidProperty UUID column to generate, false to skip, null to skip
     */
    public static function objectFactory(
        string $objectName,
        array|object $data,
        ?array $fields = null,
        string|false|null $populateUuidProperty = null,
    ): object {
        return self::buildObject($objectName, $data, $fields, $populateUuidProperty, forInsert: false);
    }

    /**
     * Build a row object for INSERT. Honors DTO $insertSkip when present; auto-populates UUID when
     * $populateUuidProperty is null and the class defines $uuidProperty or a {name}_uuid property exists.
     *
     * @param string|false|null $populateUuidProperty UUID column to generate, false to skip, null to auto-detect (insert only)
     */
    public static function objectInsertFactory(
        string $objectName,
        array|object $data,
        ?array $fields = null,
        string|false|null $populateUuidProperty = null,
    ): object {
        return self::buildObject($objectName, $data, $fields, $populateUuidProperty, forInsert: true);
    }

    /**
     * @param string|false|null $populateUuidProperty
     */
    private static function buildObject(
        string $objectName,
        array|object $data,
        ?array $fields,
        string|false|null $populateUuidProperty,
        bool $forInsert,
    ): object {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        $o = new $objectName();
        $nativeKeys = array_keys(get_object_vars($o));

        $allowedKeys = $nativeKeys;
        if ($forInsert) {
            $skip = self::insertSkipForClass($objectName);
            if ($skip !== []) {
                $allowedKeys = array_values(array_diff($nativeKeys, $skip));
            }
        }

        if ($fields === null) {
            $keys = array_keys($data);
        } else {
            $keys = $fields;
        }
        $keys = array_values(array_intersect($keys, $allowedKeys));

        $uuidProperty = null;
        if ($populateUuidProperty === false) {
            $uuidProperty = null;
        } elseif (is_string($populateUuidProperty) && $populateUuidProperty !== '') {
            $uuidProperty = $populateUuidProperty;
        } elseif ($populateUuidProperty === null && $forInsert) {
            $uuidProperty = self::resolveUuidProperty($objectName, $o);
        }

        $preserveKeys = $keys;
        if ($uuidProperty !== null && in_array($uuidProperty, $nativeKeys, true)) {
            $o->{$uuidProperty} = Util::uuid();
            if (!in_array($uuidProperty, $preserveKeys, true)) {
                $preserveKeys[] = $uuidProperty;
            }
        }

        $o = Util::unsetObjectKeysExcept($o, $preserveKeys);
        $o = Util::mapArrayToObject($data, $o, $keys);

        return $o;
    }

    /** @return list<string> */
    private static function insertSkipForClass(string $class): array
    {
        if (!class_exists($class) || !property_exists($class, 'insertSkip')) {
            return [];
        }

        $skip = $class::$insertSkip;

        return is_array($skip) ? $skip : [];
    }

    private static function resolveUuidProperty(string $class, object $instance): ?string
    {
        if (class_exists($class) && property_exists($class, 'uuidProperty')) {
            $declared = $class::$uuidProperty;
            if (is_string($declared) && $declared !== '' && property_exists($instance, $declared)) {
                return $declared;
            }
        }

        $candidate = self::shortClassName($class) . '_uuid';
        if (property_exists($instance, $candidate)) {
            return $candidate;
        }

        return null;
    }

    private static function shortClassName(string $class): string
    {
        $class = ltrim($class, '\\');
        if (str_contains($class, '\\')) {
            $parts = explode('\\', $class);

            return end($parts);
        }

        return $class;
    }

    public static function prepareDataArray($data, $fields = null)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if ($fields === null) {
            $fields = array_keys($data);
        }

        return array_intersect_key($data, array_flip($fields));
    }

    // ------------------------------------------------------------
    // Core Database Methods
    // ------------------------------------------------------------
    /**
     * PURE_DB_connection() is a singleton that returns the PDO database connection 
     * (handles a single connection internally)
     */
    public static function connection($conn = null, $user = null, $pass = null)
    {
        static $pdo = null;

        if ($conn === null && $pdo === null) {
            // Attempt to connect to the database the first time it is used
            if (!defined('PURE_DB_CONNECTION')) {
                static::log('PURE_DB_CONNECTION constant is not defined');
                return false;
            }
            if (!defined('PURE_DB_USERNAME')) {
                static::log('PURE_DB_USERNAME constant is not defined');
                return false;
            }
            if (!defined('PURE_DB_PASSWORD')) {
                static::log('PURE_DB_PASSWORD constant is not defined');
                return false;
            }
            // Attempt to connect
            return static::connection(PURE_DB_CONNECTION, PURE_DB_USERNAME, PURE_DB_PASSWORD);
        }

        if ($conn !== null) {
            $pdo = new \PDO($conn, $user, $pass);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        }
        return $pdo;
    }

    /**
     * Begin a database transaction on the singleton connection.
     */
    public static function beginTransaction(): bool
    {
        $pdo = static::connection();
        if ($pdo === false) {
            static::log('beginTransaction: no database connection');
            return false;
        }
        try {
            return $pdo->beginTransaction();
        } catch (\PDOException $e) {
            static::log($e->getMessage());
            return false;
        }
    }

    /**
     * Commit the active transaction.
     */
    public static function commit(): bool
    {
        $pdo = static::connection();
        if ($pdo === false) {
            static::log('commit: no database connection');
            return false;
        }
        try {
            return $pdo->commit();
        } catch (\PDOException $e) {
            static::log($e->getMessage());
            return false;
        }
    }

    /**
     * Roll back the active transaction.
     */
    public static function rollback(): bool
    {
        $pdo = static::connection();
        if ($pdo === false) {
            static::log('rollback: no database connection');
            return false;
        }
        try {
            return $pdo->rollBack();
        } catch (\PDOException $e) {
            static::log($e->getMessage());
            return false;
        }
    }

    /**
     * Return whether the connection has an open transaction.
     */
    public static function inTransaction(): bool
    {
        $pdo = static::connection();
        if ($pdo === false) {
            return false;
        }
        return $pdo->inTransaction();
    }

    /**
     * Run a callback inside a transaction. Commits on success; rolls back on failure.
     *
     * @return mixed The callback return value, or false when begin/commit fails
     * @throws \Throwable Re-throws exceptions from the callback after rollback
     */
    public static function transaction(callable $callback): mixed
    {
        if (!static::beginTransaction()) {
            return false;
        }
        try {
            $result = $callback();
            if (!static::commit()) {
                static::rollback();
                return false;
            }
            return $result;
        } catch (\Throwable $e) {
            if (static::inTransaction()) {
                static::rollback();
            }
            throw $e;
        }
    }

    /**
     * PURE_DB_error() extracts any error messages from the last executed statement
     */
    public static function error($statement, $query = null, $data = null)
    {
        if (!$statement) {
            $obj = static::connection();
        } else {
            $obj = $statement;
        }
        $err = $obj->errorInfo();
        $msg = $err[0] . ': ' . $err[2] . "\n\n";
        $msg .= "QUERY: $query\n\n";
        ob_start();
        foreach ($data as $key => $value) {
            // If key includes _uuid -- bin to hex it
            if (str_ends_with($key, '_uuid')) {
                $msg .= "{$key}: " . bin2hex($value) . "\n";
            } else {
                $msg .= "{$key}: " . var_export($value, true) . "\n";
            }
        }
        $msg .= ob_get_clean();
        $msg .= "\n";
        static::log($msg);
    }

    /**
     * PURE_DB_query() runs a prepared and executed query returning the PDO statement object
     */
    public static function query($query, $data = null)
    {
        if ($data === null) {
            $data = [];
        }
        if (!is_array($data)) {
            $data = array($data);
        }

        $pdo = static::connection();
        $stmt = $pdo->prepare($query);
        if ($stmt !== false) {
            try {
                if ($stmt->execute($data) !== false) {
                    return $stmt;
                }
            } catch (\Exception $exception) {
                static::log($exception->getMessage());
            }
        }

        static::error($stmt, $query, $data);
        return false;
    }

    /**
     * rawquery() runs a raw query against the database
     */
    public static function rawQuery($query)
    {
        $pdo = static::connection();
        $stmt = $pdo->query($query);
        if ($stmt !== false) {
            return $stmt;
        }
        static::error($stmt, $query);
        return false;
    }

    /**
     * PURE_DB_select() method is a wrapper around PURE_DB_query() that returns all of the data
     * in the form of an array of objects.
     */
    public static function select($query, $data = null, $class = null)
    {
        if ($class === null) {
            $class = 'stdClass';
        }

        $stmt = static::query($query, $data);
        if ($stmt !== false) {
            $data = $stmt->fetchAll(\PDO::FETCH_CLASS, $class);
            if (is_array($data)) {
                return static::decodeRows($data);
            }
        }
        return false;
    }

    /**
     * PURE_DB_row_count() is a wrapper around PURE_DB_query() that returns the row count of the query.
     */
    public static function rowCount($query, $data = null)
    {
        $stmt = static::query($query, $data);
        if ($stmt !== false) {
            return $stmt->rowCount();
        }
        return false;
    }

    // ------------------------------------------------------------
    // Utility methods for generating SQL statements
    // ------------------------------------------------------------
    /**
     * PURE_DB_prepare_where_statement() constructs a where clause for a 
     * SQL statement.
     */
    public static function prepareWhereStatement($where, $data = null)
    {
        $ret = new \stdClass;
        $ret->where = '';
        $ret->data = [];

        if (is_array($where)) {
            $where = static::encodeWhereData($where);

            $tmp = [];
            foreach ($where as $column => $value) {
                $tmp[] = '`' . $column . '`=?';
                $ret->data[] = $value;
            }
            $ret->where = ' WHERE ' . implode(' AND ', $tmp);
        } else if ($where !== null) {
            $ret->where = ' ' . $where;
            $ret->data = static::encodeWhereData($data);
        }
        return $ret;
    }

    /**
     * PURE_DB_construct_query() is a utility method to handle proper string
     * concatenation for a SQL statement.
     */
    public static function constructQuery($query, $where = null, $whereData = null)
    {
        $w = static::prepareWhereStatement($where, $whereData);
        $ret = new \stdClass;
        $ret->query = $query . $w->where;
        $ret->data = $w->data;
        return $ret;
    }

    /**
     * PURE_DB_fetch() returns an array of objects with each object representing a row.
     */
    public static function fetch($table, $where = null, $data = null)
    {
        [$table, $class] = static::mapTableClass($table);
        $q = static::constructQuery('SELECT * FROM ' . $table, $where, $data);
        return static::select($q->query, $q->data, $class);
    }

    /**
     * PURE_DB_fetch_single() returns a single record
     */
    public static function fetchSingle($table, $where = null, $data = null)
    {
        [$table, $class] = static::mapTableClass($table);
        $q = static::constructQuery('SELECT * FROM ' . $table, $where, $data);
        $q->query .= ' LIMIT 1';

        $ret = static::select($q->query, $q->data, $class);
        if (is_array($ret)) {
            // Explicitly return null if the array is empty
            if (empty($ret)) {
                return null;
            }

            return array_shift($ret);
        }
        return $ret;
    }

    /**
     * PURE_DB_fetch_fields() constructs a query with a list of just the fields specified
     */
    public static function fetchFields($table, $fields, $where = null, $data = null)
    {
        if (!is_array($table)) {
            $table = array($table, 'stdClass');
        }
        [$table, $class] = static::mapTableClass($table);

        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $q = static::constructQuery('SELECT `' . implode('`,`', $fields) . '` FROM ' . $table, $where, $data);
        $ret = static::select($q->query, $q->data, $class);
        return $ret;
    }

    /**
     * PURE_DB_fetch_field() returns an array of values for the single field that was selected
     */
    public static function fetchField($table, $field, $where = null, $data = null)
    {
        $ret = static::fetchFields($table, $field, $where, $data);
        if (is_array($ret)) {
            $tmp = [];
            foreach ($ret as $r) {
                $tmp[] = $r->{$field};
            }
            return $tmp;
        }
        return $ret;
    }

    private static function mapKeyValues(object|array $data, $insert = false)
    {
        // Handle an object passed in
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        // Provide an opportunity for a user-space method to encode/modify any properties
        // such as setting "created" dates, etc.
        $data = static::encodeData($data, $insert);

        $keyValues = new \stdClass;
        $keyValues->keys = array_keys($data);
        $keyValues->values = array_values($data);
        return $keyValues;
    }

    /**
     * Insert
     */
    public static function insert(string|array $table, object|array $data)
    {
        $keyValues = self::mapKeyValues($data, true);
        [$table, $class] = static::mapTableClass($table);

        $keys = $keyValues->keys;
        $data = $keyValues->values;
        $query = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $keys) . '`) VALUES (' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $stmt = static::query($query, $data);
        if ($stmt) {
            $pdo = static::connection();
            return $pdo->lastInsertId();
        }

        return false;
    }

    /**
     * PURE_DB_update() returns the number of rows changed or false
     */
    public static function update(string|array $table, object|array $data, string|array|null $where = null, $whereData = null)
    {
        $keyValues = self::mapKeyValues($data, false);
        [$table, $class] = static::mapTableClass($table);

        $set = '`' . implode('`=?, `', $keyValues->keys) . '`=?';
        $q = static::constructQuery('UPDATE ' . $table . ' SET ' . $set, $where, $whereData);
        $data = array_merge($keyValues->values, $q->data);
        return static::rowCount($q->query, $data);
    }

    /**
     * PURE_DB_delete() constructs a delete SQL statement based upon the included where clause
     */
    public static function delete($table, $where = null, $data = null): int|false
    {
        $w = static::prepareWhereStatement($where, $data);
        if (empty($w->where)) {
            static::log('ERROR: Delete query with no WHERE clause');
            return false;
        }
        $query = 'DELETE FROM ' . $table . $w->where;
        return static::rowCount($query, $w->data);
    }

    public static function pagingFor($table, $itemsPerPage = 10, $where = null, $data = null, $class = null)
    {
        $ret = new \stdClass;
        $ret->row_count = 0;
        $ret->page_count = 0;

        $q = static::constructQuery('SELECT count(*) AS row_count FROM ' . $table, $where, $data);
        $row = static::select($q->query, $q->data, $class);
        if ($row !== false && count($row) > 0) {
            $ret->row_count = $row[0]->row_count;
            $ret->page_count = (int) ceil($ret->row_count / $itemsPerPage);
        }
        return $ret;
    }
}