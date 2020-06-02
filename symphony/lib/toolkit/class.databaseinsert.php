<?php

/**
 * @package toolkit
 */

/**
 * This DatabaseStatement specialization class allows creation of INSERT INTO statements.
 */
final class DatabaseInsert extends DatabaseStatement
{
    /**
     * Creates a new DatabaseInsert statement on table $table.
     *
     * @see Database::insert()
     * @param Database $db
     *  The underlying database connection
     * @param string $table
     *  The name of the table to act on, including the tbl prefix which will be changed
     *  to the Database table prefix.
     */
    public function __construct(Database $db, $table)
    {
        parent::__construct($db, 'INSERT INTO');
        $table = $this->replaceTablePrefix($table);
        $table = $this->asTickedString($table);
        $this->unsafeAppendSQLPart('table', $table);
    }

    /**
     * Returns the parts statement structure for this specialized statement.
     *
     * @see DatabaseStatement::getStatementStructure()
     * @return array
     */
    protected function getStatementStructure()
    {
        return [
            'statement',
            'table',
            'cols',
            'values',
            'on duplicate',
        ];
    }

    /**
     * Gets the proper separator string for the given $type SQL part type, when
     * generating a formatted SQL statement.
     *
     * @see DatabaseStatement::getSeparatorForPartType()
     * @param string $type
     *  The SQL part type.
     * @return string
     *  The string to use to separate the formatted SQL parts.
     */
    public function getSeparatorForPartType($type)
    {
        General::ensureType([
            'type' => ['var' => $type, 'type' => 'string'],
        ]);
        if (in_array($type, ['values', 'on duplicate'])) {
            return self::FORMATTED_PART_DELIMITER;
        }
        return self::STATEMENTS_DELIMITER;
    }

    /**
     * Appends one or multiple values into the insert statements.
     * Can only be called once in the lifetime of the object.
     *
     * @see extended()
     * @throws DatabaseStatementException
     * @param array $values
     *  The values to append. Keys are columns names and values will be added
     *  to the value array and be substituted with SQL parameters.
     * @return DatabaseInsert
     *  The current instance
     */
    public function values(array $values)
    {
        if ($this->containsSQLParts('values')) {
            throw new DatabaseStatementException('DatabaseInsert can not hold more than one values clause');
        }
        $cols = '(' . $this->asTickedList(array_keys($values)) . ')';
        $this->unsafeAppendSQLPart('cols', $cols);
        $v = 'VALUES (' . $this->asPlaceholdersList($values) . ')';
        $this->unsafeAppendSQLPart('values', $v);
        $this->appendValues($values);
        return $this;
    }

    /**
     * Appends one or multiple rows of values into the insert statements.
     * Can only be called once in the lifetime of the object.
     *
     * @see values()
     * @throws DatabaseStatementException
     * @param array $rows
     *  An array of rows to append. Keys of the first row are columns names and values will be added
     *  to the value array and be substituted with SQL parameters.
     * @return DatabaseInsert
     *  The current instance
     */
    public function extended(array $rows)
    {
        if ($this->containsSQLParts('values')) {
            throw new DatabaseStatementException('DatabaseInsert can not hold more than one values clause');
        }
        if (empty($rows)) {
            throw new DatabaseStatementException('No rows to insert found');
        }
        $cols = '(' . $this->asTickedList(array_keys($rows[0])) . ')';
        $this->unsafeAppendSQLPart('cols', $cols);
        $v = [];
        foreach ($rows as $values) {
            $v[] = '(' . $this->asPlaceholdersList($values) . ')';
            $this->appendValues($values);
        }
        $this->unsafeAppendSQLPart('values', 'VALUES '. implode(', ', $v));
        return $this;
    }

    /**
     * Creates a ON DUPLICATE KEY UPDATE statement, based on values already appended.
     * Can only be called once in the lifetime of the object.
     *
     * @param array $values
     *  The values to use for the statement. Note that the array keys are used, not the values.
     *  If left empty, it will use all the available values in this statement.
     *  This is especially useful when using `extended()` inserts.
     * @throws DatabaseStatementException
     * @return DatabaseInsert
     *  The current instance
     */
    public function updateOnDuplicateKey(array $values = [])
    {
        if ($this->containsSQLParts('on duplicate')) {
            throw new DatabaseStatementException('DatabaseInsert can not hold more than one on duplicate clause');
        } elseif (empty($this->getValues())) {
            throw new DatabaseStatementException('updateOnDuplicateKey() needs values() to be called first');
        }
        if (empty($values)) {
            $values = $this->getValues();
        }
        $update = implode(self::LIST_DELIMITER, General::array_map(function ($key, $value) {
            $key = $this->asTickedString($key);
            return "$key = VALUES($key)";
        }, $values));
        $this->unsafeAppendSQLPart('on duplicate', "ON DUPLICATE KEY UPDATE $update");
        return $this;
    }
}
