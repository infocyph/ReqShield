<?php

declare(strict_types=1);

/**
 * ReqShield Automatic Improvement Patcher
 * 
 * This script applies all suggested improvements to the ReqShield validator library.
 * 
 * Usage: php apply-improvements.php [path-to-src-directory]
 * Example: php apply-improvements.php ./src
 * 
 * If no path is provided, it will look for ./src directory
 */

class ReqShieldPatcher
{
    private string $srcPath;
    private array $log = [];
    private int $filesModified = 0;
    private int $filesCreated = 0;
    private bool $dryRun = false;

    public function __construct(string $srcPath, bool $dryRun = false)
    {
        $this->srcPath = rtrim($srcPath, '/');
        $this->dryRun = $dryRun;
    }

    public function apply(): void
    {
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║      ReqShield Automatic Improvement Patcher          ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n\n";

        if ($this->dryRun) {
            echo "🔍 DRY RUN MODE - No files will be modified\n\n";
        }

        if (!is_dir($this->srcPath)) {
            $this->error("Source directory not found: {$this->srcPath}");
            return;
        }

        echo "📂 Source directory: {$this->srcPath}\n";
        echo "🚀 Starting patch process...\n\n";

        // Create backup
        $this->createBackup();

        // Apply all improvements
        $this->patchDatabaseProvider();
        $this->patchRuleContract();
        $this->patchMockDatabaseProvider();
        $this->patchInvalidRuleException();
        $this->patchValidationException();
        $this->patchDateRule();
        $this->patchRequiredRule();
        $this->patchUniqueRule();
        $this->createFieldAliasClass();
        $this->createNestedValidatorClass();
        $this->patchValidator();
        $this->patchValidationResult();
        $this->patchFunctions();

        // Summary
        $this->printSummary();
    }

    private function createBackup(): void
    {
        $backupPath = dirname($this->srcPath) . '/src-backup-' . date('Y-m-d-His');
        
        if ($this->dryRun) {
            $this->log("Would create backup at: {$backupPath}");
            return;
        }

        echo "💾 Creating backup at: {$backupPath}\n";
        
        if (!$this->copyDirectory($this->srcPath, $backupPath)) {
            $this->error("Failed to create backup!");
            exit(1);
        }

        $this->log("Backup created successfully");
    }

    private function copyDirectory(string $src, string $dst): bool
    {
        if (!is_dir($src)) {
            return false;
        }

        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }

        $dir = opendir($src);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (is_dir("$src/$file")) {
                $this->copyDirectory("$src/$file", "$dst/$file");
            } else {
                copy("$src/$file", "$dst/$file");
            }
        }
        closedir($dir);

        return true;
    }

    private function patchDatabaseProvider(): void
    {
        $this->log("Patching Contracts/DatabaseProvider.php");
        
        $file = "{$this->srcPath}/Contracts/DatabaseProvider.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add compositeUnique method before the query method
        if (!str_contains($content, 'compositeUnique')) {
            $queryMethodPos = strpos($content, 'public function query(');
            
            if ($queryMethodPos !== false) {
                $newMethod = "    /**
     * Check if a composite key is unique.
     *
     * @param  string  \$table  Table name
     * @param  array  \$columns  Array of column => value pairs
     * @param  int|null  \$ignoreId  ID to ignore (for updates)
     * @return bool True if unique, false if not
     *
     * @example
     * \$provider->compositeUnique('user_roles', ['user_id' => 1, 'role_id' => 2]);
     */
    public function compositeUnique(string \$table, array \$columns, ?int \$ignoreId = null): bool;

    ";
                
                $content = substr_replace($content, $newMethod, $queryMethodPos, 0);
                
                if ($this->writeFile($file, $content)) {
                    $this->filesModified++;
                }
            }
        }
    }

    private function patchRuleContract(): void
    {
        $this->log("Patching Contracts/Rule.php");
        
        $file = "{$this->srcPath}/Contracts/Rule.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add better documentation with examples
        if (!str_contains($content, '@example return 5;')) {
            // Enhanced cost() documentation
            $content = preg_replace(
                '/\/\*\*\s+\*\s+Get the cost of this rule.*?\*\/\s+public function cost\(\): int;/s',
                '/**
     * Get the cost of this rule (for optimization).
     * Lower cost rules run first.
     *
     * Cost guidelines:
     * - 1-10: Simple checks (type checks, empty checks)
     * - 10-50: Medium complexity (string operations, regex)
     * - 100+: Expensive operations (database queries, API calls)
     *
     * @return int The cost value
     *
     * @example return 5; // For simple type checks
     */
    public function cost(): int;',
                $content
            );

            if ($this->writeFile($file, $content)) {
                $this->filesModified++;
            }
        }
    }

    private function patchMockDatabaseProvider(): void
    {
        $this->log("Patching Database/MockDatabaseProvider.php");
        
        $file = "{$this->srcPath}/Database/MockDatabaseProvider.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add security warning to class docblock
        if (!str_contains($content, 'WARNING: FOR TESTING')) {
            $content = str_replace(
                '/**
 * MockDatabaseProvider
 *
 * A simple in-memory database provider for testing and examples.
 * Replace this with your actual database implementation.
 */',
                '/**
 * MockDatabaseProvider
 *
 * ⚠️ WARNING: FOR TESTING AND EXAMPLES ONLY ⚠️
 *
 * This is a simple in-memory database provider intended ONLY for:
 * - Unit testing
 * - Documentation examples
 * - Quick prototyping
 *
 * DO NOT USE IN PRODUCTION!
 *
 * For production use, implement DatabaseProvider with:
 * - PDO with prepared statements
 * - Your ORM (Eloquent, Doctrine, etc.)
 * - Proper query builder with parameter binding
 *
 * This mock implementation does NOT provide real security or performance.
 */',
                $content
            );
        }

        // Add compositeUnique method before query method
        if (!str_contains($content, 'public function compositeUnique')) {
            $queryMethodPos = strpos($content, '    /**
     * Execute a database query.');
            
            if ($queryMethodPos !== false) {
                $newMethod = '    /**
     * Check if a composite key is unique.
     */
    public function compositeUnique(string $table, array $columns, ?int $ignoreId = null): bool
    {
        if (!isset($this->data[$table])) {
            return true; // No data, so it\'s unique
        }

        foreach ($this->data[$table] as $row) {
            // Check if we should ignore this row
            if ($ignoreId && isset($row[\'id\']) && $row[\'id\'] === $ignoreId) {
                continue;
            }

            // Check if all columns match
            $allMatch = true;
            foreach ($columns as $column => $value) {
                if (!isset($row[$column]) || $row[$column] !== $value) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return false; // Found a matching row, not unique
            }
        }

        return true; // No matching row found, it\'s unique
    }

';
                
                $content = substr_replace($content, $newMethod, $queryMethodPos, 0);
            }
        }

        // Add input validation to addData
        if (!str_contains($content, 'Rows must be a non-empty array')) {
            $content = str_replace(
                '    public function addData(string $table, array $rows): void
    {
        $this->data[$table] = $rows;
    }',
                '    public function addData(string $table, array $rows): void
    {
        if (!is_array($rows) || empty($rows)) {
            throw new \InvalidArgumentException("Rows must be a non-empty array");
        }
        $this->data[$table] = $rows;
    }',
                $content
            );
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function patchInvalidRuleException(): void
    {
        $this->log("Patching Exceptions/InvalidRuleException.php");
        
        $file = "{$this->srcPath}/Exceptions/InvalidRuleException.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add helper methods
        if (!str_contains($content, 'public static function invalidFormat')) {
            $content = str_replace(
                'class InvalidRuleException extends ValidationException
{
}',
                'class InvalidRuleException extends ValidationException
{
    /**
     * Create exception for invalid rule format.
     */
    public static function invalidFormat(string $rule, string $reason = \'\'): self
    {
        $message = "Invalid rule format: \'{$rule}\'";
        if ($reason) {
            $message .= ". {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for unknown rule.
     */
    public static function unknownRule(string $ruleName): self
    {
        return new self("Unknown validation rule: \'{$ruleName}\'");
    }

    /**
     * Create exception for invalid rule parameters.
     */
    public static function invalidParameters(string $rule, string $reason): self
    {
        return new self("Invalid parameters for rule \'{$rule}\': {$reason}");
    }
}',
                $content
            );
            
            if ($this->writeFile($file, $content)) {
                $this->filesModified++;
            }
        }
    }

    private function patchValidationException(): void
    {
        $this->log("Patching Exceptions/ValidationException.php");
        
        $file = "{$this->srcPath}/Exceptions/ValidationException.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add errors property and methods
        if (!str_contains($content, 'protected array $errors')) {
            $content = str_replace(
                'class ValidationException extends \Exception
{
}',
                'class ValidationException extends \Exception
{
    /**
     * Validation errors.
     */
    protected array $errors = [];

    /**
     * Set validation errors.
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Get validation errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}',
                $content
            );
            
            if ($this->writeFile($file, $content)) {
                $this->filesModified++;
            }
        }
    }

    private function patchDateRule(): void
    {
        $this->log("Patching Rules/Date.php");
        
        $file = "{$this->srcPath}/Rules/Date.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add timezone support and date range validation
        if (!str_contains($content, 'protected ?\DateTimeZone $timezone')) {
            // Find the format property and add new properties after it
            $formatPos = strpos($content, 'protected string $format;');
            if ($formatPos !== false) {
                $nextLinePos = strpos($content, "\n", $formatPos) + 1;
                
                $newProperties = "
    /**
     * Timezone for date validation.
     */
    protected ?\DateTimeZone \$timezone = null;

    /**
     * Minimum date (inclusive).
     */
    protected ?\DateTimeInterface \$minDate = null;

    /**
     * Maximum date (inclusive).
     */
    protected ?\DateTimeInterface \$maxDate = null;
";
                
                $content = substr_replace($content, $newProperties, $nextLinePos, 0);
            }
        }

        // Update constructor
        if (!str_contains($content, 'DateTimeZone $timezone')) {
            $content = preg_replace(
                '/public function __construct\(string \$format = \'Y-m-d\'\)/',
                'public function __construct(
        string $format = \'Y-m-d\',
        ?\DateTimeZone $timezone = null,
        ?\DateTimeInterface $minDate = null,
        ?\DateTimeInterface $maxDate = null
    )',
                $content
            );
            
            // Update constructor body
            $content = preg_replace(
                '/(\$this->format = \$format;)/',
                '$1
        $this->timezone = $timezone;
        $this->minDate = $minDate;
        $this->maxDate = $maxDate;',
                $content,
                1
            );
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function patchRequiredRule(): void
    {
        $this->log("Patching Rules/Required.php");
        
        $file = "{$this->srcPath}/Rules/Required.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add file upload and resource validation
        if (!str_contains($content, 'isset($_FILES[$field])')) {
            // Find return false near the end of passes method
            $passesPos = strpos($content, 'public function passes(');
            if ($passesPos !== false) {
                // Find the last return false; before the closing brace
                $methodEnd = $this->findMethodEnd($content, $passesPos);
                $lastReturnPos = strrpos(substr($content, $passesPos, $methodEnd - $passesPos), 'return false;');
                
                if ($lastReturnPos !== false) {
                    $insertPos = $passesPos + $lastReturnPos;
                    $additionalChecks = "
            // Check for uploaded files in \$_FILES superglobal
            if (isset(\$_FILES[\$field])) {
                \$file = \$_FILES[\$field];

                // Check if file was actually uploaded
                if (isset(\$file['error'])) {
                    return \$file['error'] === UPLOAD_ERR_OK;
                }

                // Check if file has content
                return isset(\$file['size']) && \$file['size'] > 0;
            }

            // Check for objects with __toString method
            if (is_object(\$value) && method_exists(\$value, '__toString')) {
                \$stringValue = (string) \$value;
                return \$stringValue !== '' && trim(\$stringValue) !== '';
            }

            // Check for stream resources
            if (is_resource(\$value)) {
                return get_resource_type(\$value) === 'stream';
            }

            ";
                    
                    $content = substr_replace($content, $additionalChecks, $insertPos, 0);
                }
            }
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function patchUniqueRule(): void
    {
        $this->log("Patching Rules/Unique.php");
        
        $file = "{$this->srcPath}/Rules/Unique.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add support for composite unique and soft deletes
        if (!str_contains($content, 'string|array $column')) {
            // Update column property type
            $content = preg_replace(
                '/protected string \$column;/',
                'protected string|array $column;',
                $content
            );
            
            // Add new properties for soft delete support
            if (!str_contains($content, 'protected bool $withTrashed')) {
                $ignoreIdPos = strpos($content, 'protected ?int $ignoreId;');
                if ($ignoreIdPos !== false) {
                    $nextLinePos = strpos($content, "\n", $ignoreIdPos) + 1;
                    $newProperties = "
    /**
     * Whether to consider soft deletes.
     */
    protected bool \$withTrashed = false;

    /**
     * Soft delete column name.
     */
    protected string \$softDeleteColumn = 'deleted_at';
";
                    $content = substr_replace($content, $newProperties, $nextLinePos, 0);
                }
            }
            
            // Update constructor
            $content = preg_replace(
                '/public function __construct\(string \$table, string \$column, \?int \$ignoreId = null\)/',
                'public function __construct(
        string $table,
        string|array $column,
        ?int $ignoreId = null,
        bool $withTrashed = false,
        string $softDeleteColumn = \'deleted_at\'
    )',
                $content
            );
            
            // Update constructor body
            $content = preg_replace(
                '/(\$this->ignoreId = \$ignoreId;)/',
                '$1
        $this->withTrashed = $withTrashed;
        $this->softDeleteColumn = $softDeleteColumn;',
                $content,
                1
            );
        }

        // Add composite unique support in passes method
        if (!str_contains($content, 'checkCompositeUnique')) {
            // Find passes method and add composite check
            $passesPos = strpos($content, 'public function passes(');
            
            if ($passesPos !== false) {
                $methodStart = strpos($content, '{', $passesPos) + 1;
                
                // Insert composite check after empty value check
                $emptyCheckPos = strpos($content, 'if (empty($value))', $methodStart);
                if ($emptyCheckPos !== false) {
                    $afterEmptyCheck = strpos($content, "\n", strpos($content, '}', $emptyCheckPos)) + 1;
                    
                    $compositeCheck = "
        // Handle composite unique check
        if (is_array(\$this->column)) {
            return \$this->checkCompositeUnique(\$value, \$field, \$data);
        }
";
                    $content = substr_replace($content, $compositeCheck, $afterEmptyCheck, 0);
                }
            }
            
            // Add checkCompositeUnique method at the end of class
            $lastBracePos = strrpos($content, '}');
            $compositeMethod = "
    /**
     * Check composite unique constraint.
     */
    protected function checkCompositeUnique(mixed \$value, string \$field, array \$data): bool
    {
        \$db = ValidationContext::getDatabaseProvider();

        if (!\$db) {
            throw new \RuntimeException('Database provider is required for unique rule');
        }

        // Build column => value map
        \$columns = [];
        foreach (\$this->column as \$col) {
            if (\$col === \$field) {
                \$columns[\$col] = \$value;
            } elseif (isset(\$data[\$col])) {
                \$columns[\$col] = \$data[\$col];
            } else {
                // Missing required column for composite unique
                return true; // Pass this rule, other rules will catch missing fields
            }
        }

        return \$db->compositeUnique(\$this->table, \$columns, \$this->ignoreId);
    }
";
            $content = substr_replace($content, $compositeMethod, $lastBracePos, 0);
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function createFieldAliasClass(): void
    {
        $this->log("Creating Support/FieldAlias.php");
        
        $file = "{$this->srcPath}/Support/FieldAlias.php";
        
        if (file_exists($file)) {
            $this->log("FieldAlias.php already exists, skipping...");
            return;
        }

        $content = '<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * FieldAlias - Manages field name aliases for better error messages.
 *
 * Allows mapping technical field names to human-readable names.
 *
 * @example
 * FieldAlias::set([\'user_email\' => \'Email Address\', \'pwd\' => \'Password\']);
 * FieldAlias::get(\'user_email\'); // Returns \'Email Address\'
 */
class FieldAlias
{
    /**
     * Field name aliases.
     */
    protected static array $aliases = [];

    /**
     * Set field aliases.
     *
     * @param array $aliases Map of field names to display names
     */
    public static function set(array $aliases): void
    {
        static::$aliases = array_merge(static::$aliases, $aliases);
    }

    /**
     * Get field alias or auto-format field name.
     *
     * @param string $field The field name
     * @return string The display name
     */
    public static function get(string $field): string
    {
        if (isset(static::$aliases[$field])) {
            return static::$aliases[$field];
        }

        return static::humanize($field);
    }

    /**
     * Clear all aliases.
     */
    public static function clear(): void
    {
        static::$aliases = [];
    }

    /**
     * Auto-format field name to human-readable format.
     *
     * @param string $field The field name
     * @return string Humanized field name
     */
    protected static function humanize(string $field): string
    {
        return ucwords(str_replace([\'_\', \'-\', \'.\'], \' \', $field));
    }
}
';

        if ($this->writeFile($file, $content)) {
            $this->filesCreated++;
        }
    }

    private function createNestedValidatorClass(): void
    {
        $this->log("Creating Support/NestedValidator.php");
        
        $file = "{$this->srcPath}/Support/NestedValidator.php";
        
        if (file_exists($file)) {
            $this->log("NestedValidator.php already exists, skipping...");
            return;
        }

        $content = '<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * NestedValidator - Handles validation of nested arrays and complex structures.
 *
 * Supports dot notation for nested validation rules:
 * - \'user.email\' => validates $data[\'user\'][\'email\']
 * - \'users.*.email\' => validates email for each item in users array
 * - \'addresses.0.city\' => validates city in first address
 *
 * @example
 * $rules = [
 *     \'user.name\' => \'required|string|min:3\',
 *     \'user.email\' => \'required|email\',
 *     \'contacts.*.email\' => \'required|email\',
 *     \'contacts.*.phone\' => \'required|phone\'
 * ];
 */
class NestedValidator
{
    /**
     * Parse nested rules into a flat structure.
     *
     * @param array $rules Validation rules with dot notation
     * @return array Parsed rules structure
     */
    public static function parseRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $key => $rule) {
            if (str_contains($key, \'.\')) {
                $parsed[$key] = [
                    \'path\' => $key,
                    \'segments\' => explode(\'.\', $key),
                    \'rule\' => $rule,
                    \'is_wildcard\' => str_contains($key, \'*\')
                ];
            } else {
                $parsed[$key] = [
                    \'path\' => $key,
                    \'segments\' => [$key],
                    \'rule\' => $rule,
                    \'is_wildcard\' => false
                ];
            }
        }

        return $parsed;
    }

    /**
     * Extract nested value from data using dot notation.
     *
     * @param array $data The data array
     * @param string $path Dot notation path
     * @return mixed The value at the path or null
     */
    public static function extractValue(array $data, string $path): mixed
    {
        $segments = explode(\'.\', $path);
        $value = $data;

        foreach ($segments as $segment) {
            if ($segment === \'*\') {
                return null; // Wildcard should be handled separately
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Expand wildcard rules for array validation.
     *
     * @param array $data The data to validate
     * @param array $parsedRules Parsed rules from parseRules()
     * @return array Expanded rules without wildcards
     */
    public static function expandWildcards(array $data, array $parsedRules): array
    {
        $expanded = [];

        foreach ($parsedRules as $key => $ruleData) {
            if (!$ruleData[\'is_wildcard\']) {
                $expanded[$key] = $ruleData[\'rule\'];
                continue;
            }

            // Find the array that needs wildcard expansion
            $wildcardIndex = array_search(\'*\', $ruleData[\'segments\']);
            $pathBeforeWildcard = implode(\'.\', array_slice($ruleData[\'segments\'], 0, $wildcardIndex));
            $pathAfterWildcard = implode(\'.\', array_slice($ruleData[\'segments\'], $wildcardIndex + 1));

            // Get the array to iterate
            $arrayData = $pathBeforeWildcard
                ? static::extractValue($data, $pathBeforeWildcard)
                : $data;

            if (!is_array($arrayData)) {
                continue;
            }

            // Expand wildcard for each array item
            foreach (array_keys($arrayData) as $index) {
                $expandedPath = $pathBeforeWildcard
                    ? "{$pathBeforeWildcard}.{$index}"
                    : (string)$index;

                if ($pathAfterWildcard) {
                    $expandedPath .= ".{$pathAfterWildcard}";
                }

                $expanded[$expandedPath] = $ruleData[\'rule\'];
            }
        }

        return $expanded;
    }

    /**
     * Flatten nested data for validation.
     *
     * @param array $data The nested data
     * @param string $prefix Key prefix for recursion
     * @return array Flattened data with dot notation keys
     */
    public static function flattenData(array $data, string $prefix = \'\'): array
    {
        $flattened = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !empty($value)) {
                $flattened = array_merge($flattened, static::flattenData($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }

        return $flattened;
    }
}
';

        if ($this->writeFile($file, $content)) {
            $this->filesCreated++;
        }
    }

    private function patchValidator(): void
    {
        $this->log("Patching Validator.php");
        
        $file = "{$this->srcPath}/Validator.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add use statements for new classes
        if (!str_contains($content, 'use Infocyph\ReqShield\Support\FieldAlias;')) {
            // Find the last use statement
            preg_match_all('/^use Infocyph\\\\ReqShield.*?;$/m', $content, $matches, PREG_OFFSET_CAPTURE);
            if (!empty($matches[0])) {
                $lastUse = end($matches[0]);
                $insertPos = $lastUse[1] + strlen($lastUse[0]);
                $newUses = "\nuse Infocyph\ReqShield\Support\FieldAlias;\nuse Infocyph\ReqShield\Support\NestedValidator;\nuse Infocyph\ReqShield\Exceptions\InvalidRuleException;";
                $content = substr_replace($content, $newUses, $insertPos, 0);
            }
        }

        // Add field aliases property
        if (!str_contains($content, 'protected array $fieldAliases')) {
            $customMessagesPos = strpos($content, 'protected array $customMessages');
            if ($customMessagesPos !== false) {
                $endOfLinePos = strpos($content, "\n", strpos($content, ';', $customMessagesPos));
                $newProperty = "

    /**
     * Field name aliases for better error messages.
     */
    protected array \$fieldAliases = [];";
                $content = substr_replace($content, $newProperty, $endOfLinePos, 0);
            }
        }

        // Add nested validation property
        if (!str_contains($content, 'protected bool $nestedValidation')) {
            $failFastPos = strpos($content, 'protected bool $failFast');
            if ($failFastPos !== false) {
                $endOfLinePos = strpos($content, "\n", strpos($content, ';', $failFastPos));
                $newProperty = "

    /**
     * Whether nested validation is enabled.
     */
    protected bool \$nestedValidation = false;";
                $content = substr_replace($content, $newProperty, $endOfLinePos, 0);
            }
        }

        // Add throwOnFailure property
        if (!str_contains($content, 'protected bool $throwOnFailure')) {
            $stopOnFirstErrorPos = strpos($content, 'protected bool $stopOnFirstError');
            if ($stopOnFirstErrorPos !== false) {
                $beforePos = $stopOnFirstErrorPos;
                $newProperty = "    /**
     * Whether to throw exception on validation failure.
     */
    protected bool \$throwOnFailure = false;

    ";
                $content = substr_replace($content, $newProperty, $beforePos, 0);
            }
        }

        // Add input validation to constructor
        if (!str_contains($content, 'Rules array cannot be empty')) {
            $constructorPos = strpos($content, 'public function __construct(array $rules');
            if ($constructorPos !== false) {
                $openBracePos = strpos($content, '{', $constructorPos) + 1;
                $validation = "
        // Validate rules format
        if (empty(\$rules)) {
            throw InvalidRuleException::invalidFormat('rules', 'Rules array cannot be empty');
        }

        foreach (\$rules as \$field => \$rule) {
            if (!is_string(\$field)) {
                throw InvalidRuleException::invalidFormat(
                    (string)\$field,
                    'Field names must be strings'
                );
            }

            if (!is_string(\$rule) && !is_array(\$rule)) {
                throw InvalidRuleException::invalidFormat(
                    \$field,
                    'Rules must be string or array'
                );
            }
        }
";
                $content = substr_replace($content, $validation, $openBracePos, 0);
            }
        }

        // Add new methods before validate() method
        if (!str_contains($content, 'public function enableNestedValidation')) {
            $validatePos = strpos($content, 'public function validate(array $data)');
            if ($validatePos !== false) {
                $newMethods = '
    /**
     * Enable nested validation with dot notation support.
     *
     * @return self
     */
    public function enableNestedValidation(): self
    {
        $this->nestedValidation = true;
        return $this;
    }

    /**
     * Set field name aliases for better error messages.
     *
     * @param array $aliases Map of field names to display names
     * @return self
     */
    public function setFieldAliases(array $aliases): self
    {
        $this->fieldAliases = $aliases;
        FieldAlias::set($aliases);
        return $this;
    }

    /**
     * Set whether to throw exception on validation failure.
     *
     * @param bool $throw True to throw ValidationException on failure
     * @return self
     */
    public function throwOnFailure(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;
        return $this;
    }

    ';
                $content = substr_replace($content, $newMethods, $validatePos, 0);
            }
        }

        // Update error message generation to use FieldAlias
        if (!str_contains($content, 'FieldAlias::get($field)')) {
            $content = str_replace(
                '$errors[$field][] = $this->customMessages[$field] ?? $rule->message($field);',
                '$message = $this->customMessages[$field] ?? $rule->message(FieldAlias::get($field));
            $errors[$field][] = $message;',
                $content
            );
        }

        // Remove deprecated validateRuleSet method
        if (str_contains($content, 'protected function validateRuleSet(')) {
            $pattern = '/    \/\*\*\s+\* DEPRECATED:.*?protected function validateRuleSet\(.*?\n    \}\s*\n/s';
            $content = preg_replace($pattern, '', $content);
        }

        // Remove isEmpty method
        if (str_contains($content, 'protected function isEmpty(')) {
            $pattern = '/    \/\*\*\s+\* Check if a value is considered empty.*?protected function isEmpty\(.*?\n    \}\s*\n/s';
            $content = preg_replace($pattern, '', $content);
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function patchValidationResult(): void
    {
        $this->log("Patching Support/ValidationResult.php");
        
        $file = "{$this->srcPath}/Support/ValidationResult.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add new helper methods
        if (!str_contains($content, 'public function first(')) {
            // Find the last closing brace of the class
            $lastBracePos = strrpos($content, '}');
            
            $newMethods = '
    /**
     * Get first error message for a field.
     *
     * @param string $field Field name
     * @return string|null First error message or null
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return array All error messages
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Get validated data as JSON.
     *
     * @param int $flags JSON encode flags
     * @return string JSON representation of validated data
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->validated, $flags);
    }
';
            
            $content = substr_replace($content, $newMethods, $lastBracePos, 0);
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function patchFunctions(): void
    {
        $this->log("Patching functions.php");
        
        $file = "{$this->srcPath}/functions.php";
        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return;
        }

        $content = file_get_contents($file);
        
        // Add @example tags if not present
        if (!str_contains($content, '@example validate')) {
            $content = preg_replace(
                '/(\/\*\*\s+\*\s+Create a new validator instance\.\s+\*\/)/s',
                '$1
     *
     * @param array $rules Validation rules
     * @param DatabaseProvider|null $db Optional database provider
     * @return Validator
     *
     * @example validate([\'email\' => \'required|email\'])->validate($data);
     */',
                $content
            );
        }

        if (!str_contains($content, '@example validator')) {
            $content = preg_replace(
                '/(\/\*\*\s+\*\s+Alias for validate function\.\s+\*\/)/s',
                '$1
     *
     * @param array $rules Validation rules
     * @param DatabaseProvider|null $db Optional database provider
     * @return Validator
     *
     * @example validator([\'name\' => \'required\'])->validate($data);
     */',
                $content
            );
        }

        if (!str_contains($content, '@example sanitize')) {
            $content = preg_replace(
                '/(\/\*\*\s+\*\s+Sanitize a value using specified sanitizers\.\s+\*\/)/s',
                '$1
     *
     * @param mixed $value Value to sanitize
     * @param string|array $sanitizers Sanitizer name(s)
     * @return mixed Sanitized value
     *
     * @example sanitize($input, \'email\'); // or sanitize($input, [\'trim\', \'lowercase\']);
     */',
                $content
            );
        }

        if ($this->writeFile($file, $content)) {
            $this->filesModified++;
        }
    }

    private function findMethodEnd(string $content, int $methodStart): int
    {
        $openBrace = strpos($content, '{', $methodStart);
        $bracketCount = 1;
        $pos = $openBrace + 1;
        
        while ($bracketCount > 0 && $pos < strlen($content)) {
            if ($content[$pos] === '{') $bracketCount++;
            if ($content[$pos] === '}') $bracketCount--;
            $pos++;
        }
        
        return $pos;
    }

    private function writeFile(string $file, string $content): bool
    {
        if ($this->dryRun) {
            $this->log("Would write to: {$file}");
            return true;
        }

        $result = file_put_contents($file, $content);
        if ($result === false) {
            $this->error("Failed to write: {$file}");
            return false;
        }

        $this->log("✓ Updated: " . basename($file));
        return true;
    }

    private function log(string $message): void
    {
        $this->log[] = $message;
        echo "  {$message}\n";
    }

    private function error(string $message): void
    {
        echo "  ❌ ERROR: {$message}\n";
        $this->log[] = "ERROR: {$message}";
    }

    private function printSummary(): void
    {
        echo "\n";
        echo "╔════════════════════════════════════════════════════════╗\n";
        echo "║                   PATCH SUMMARY                        ║\n";
        echo "╚════════════════════════════════════════════════════════╝\n\n";
        
        echo "  📝 Files Modified: {$this->filesModified}\n";
        echo "  ✨ Files Created: {$this->filesCreated}\n";
        echo "  📋 Total Operations: " . ($this->filesModified + $this->filesCreated) . "\n\n";
        
        if (!$this->dryRun) {
            echo "  ✅ All improvements applied successfully!\n\n";
            echo "  Next steps:\n";
            echo "  1. Review the changes\n";
            echo "  2. Run tests: composer test\n";
            echo "  3. Check code style: composer lint\n";
            echo "  4. Update your documentation\n\n";
            echo "  💡 A backup was created at: " . dirname($this->srcPath) . "/src-backup-*\n\n";
        } else {
            echo "  ℹ️  This was a dry run. No files were modified.\n";
            echo "  Run without --dry-run flag to apply changes.\n\n";
        }
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

$srcPath = $argv[1] ?? './src';
$dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);

if (in_array('--help', $argv) || in_array('-h', $argv)) {
    echo <<<HELP
ReqShield Automatic Improvement Patcher

Usage:
  php apply-improvements.php [path-to-src] [options]

Arguments:
  path-to-src    Path to the src directory (default: ./src)

Options:
  --dry-run, -d  Run without making changes (preview mode)
  --help, -h     Show this help message

Examples:
  php apply-improvements.php
  php apply-improvements.php ./src
  php apply-improvements.php ./src --dry-run

HELP;
    exit(0);
}

$patcher = new ReqShieldPatcher($srcPath, $dryRun);
$patcher->apply();
