<?php
/**
 * Plugin Name: Ziegenhagel DBSchema
 * Description: A plugin for creating a database schema
 * Version: 1.0.0
 * Author: Ziegenhagel
 * Author URI: https://ziegenhagel.com
 * Text Domain: zdb
 */

// load the list of schemas_sanity from schemas/*.json
$schemas = array_map(function ($file) {
    return basename($file, '.json');
}, glob(__DIR__ . '/schemas/*.json'));

// db prefix
$prefix = 'wp_zdb_';

// create the database tables
$tables = [];

$registered_types = [];

// load all the schemas_sanity
foreach ($schemas as $schema) {

    // schema filename
    $schemaFile = __DIR__ . '/schemas/' . $schema . '.json';

    // load the schema
    $schema = json_decode(file_get_contents($schemaFile), true);

    // add the table name to the table object
    $table_name = $prefix . $schema['name'];

    // push the table object to the tables array
    $fields = fields_to_sql_object($schema['fields'], $table_name);
    register_new_table($table_name, $fields);

}

function fields_to_sql_object($fields, $table_name)
{
    $sql_object = [];

    foreach ($fields as $field) {
        $sql_object[] = field_to_sql_object($field, $table_name);
    }

    return $sql_object;
}

function field_to_sql_object($field, $table_name)
{
    $sql_object = [];

    $sql_object['name'] = validate_fieldname($field['name']);
    $sql_object['type'] = $field['type'];
    $sql_object['default'] = $field['default'];
    $sql_object['required'] = $field['required'];

    if ($field['type'] === 'text') {
        $sql_object['length'] = 250;
    } else if ($field['type'] === 'image') {
        $sql_object['type'] = 'int(11)';
        $sql_object['name'] = $field['name'].'_attachmentId';
        $sql_object['length'] = 11;
    } else if ($field['type'] === 'number') {
        $sql_object['type'] = 'int(11)';
        $sql_object['length'] = 11;
    } else if ($field['type'] === 'boolean') {
        $sql_object['type'] = 'tinyint(1)';
        $sql_object['length'] = 1;
    } else if ($field['type'] === 'enum') {
        $sql_object['type'] = 'enum';
        $sql_object['options'] = $field['options'];
    } else if ($field['type'] === 'reference') {
        apply_field_reference($sql_object, $field);
    } else if ($field['type'] === 'array') {
        apply_field_array($sql_object, $field, $table_name);
    } else if ($field['type'] === 'object') {
        apply_field_object($sql_object, $field, $table_name);
    }

    return $sql_object;
}

function apply_field_reference(&$sql_object, $field)
{
    if ($field['type'] !== 'reference')
        return "Field is not a reference";

    // do we know this type? is it a book, is it an author?
    global $schemas;
    if (in_array($field['to']['type'], $schemas)) {
        $sql_object['type'] = 'int(11)';
        $sql_object['name'] = $field['to']['type'] . 'Id';
    } else {
        echo "Reference type '" . $field['to']['type'] . "' not found in schemas";
    }
}

function apply_field_array(&$sql_object, $field, $table_name)
{
    if ($field['type'] !== 'array')
        return "Field is not an array";

    // create a new table name and get the fields for the new table
    $new_table_name = $table_name . "_" . validate_fieldname($field['name']);

    $fields[] = field_to_sql_object($field["of"], $new_table_name);

    // make the field a reference to the new table
    $sql_object['_resolved'] = true;
    // $sql_object = null;

    // we want an array? so we need a new table schema, referencing the current table via id and connecting to the referenced table or embedding the content
    // we need to know the name of the current table, the name of the referenced table and the name of the new table
    global $prefix;

    if ($field['of']['type'] !== 'object')
        register_new_table($new_table_name, $fields);

}

function apply_field_object(&$sql_object, $fields, $table_name)
{
    if ($fields['type'] !== 'object')
        return "Field is not an object";

    // create a new table name and get the fields for the new table
    $new_table_name = $table_name;
    $fields = fields_to_sql_object($fields["fields"], $new_table_name);

    // make the field a reference to the table it belongs to
    global $prefix;
    $clean_table_name = str_replace($prefix, '', $table_name);
    // remove between the last underscore and the end
    $clean_table_name = preg_replace('/_[^_]+$/', '', $clean_table_name);
    $fields[] = [
        'name' => $clean_table_name . 'Id',
        'type' => 'int(11)',
        'required' => true,
    ];

    // we want an array? so we need a new table schema, referencing the current table via id and connecting to the referenced table or embedding the content
    // we need to know the name of the current table, the name of the referenced table and the name of the new table
    global $prefix;
    register_new_table($new_table_name, $fields);

}


function register_new_table($table_name, $fields = [])
{
    global $schemas, $tables;

    // check if the table already exists
    if (in_array($table_name, $schemas))
        return;

    // add the id field to the beginning of the fields array
    $id_field = [
        'name' => 'id',
        'type' => 'int(11)',
        'required' => true,
        'length' => 11,
        'auto_increment' => true,
        'primary_key' => true
    ];
    // array_unshift($fields, $id_field); // TODO uncomment this

    $schemas[] = $table_name;
    $tables[$table_name] = $fields;

}

function validate_fieldname($field_name)
{
    if (in_array($field_name, ['id', 'type', 'name', 'default', 'length', 'options', 'required', 'auto_increment', 'primary_key']))
        die("Field name '" . $field_name . "' is not allowed");

    // fields may not start with a number
    if (is_numeric(substr($field_name, 0, 1)))
        die("Field name '" . $field_name . "' may not start with a number");

    // fields may not contain a space or a dash or a dot or a comma or a semicolon or a underline
    if (preg_match('/[ -.,;_]/', $field_name))
        die("Field name '" . $field_name . "' may not contain a space or a dash or a dot or a comma or a semicolon or a underline");

    return $field_name;
}

// output the tables as json
echo '<pre>';
echo json_encode($tables, JSON_PRETTY_PRINT);
echo '</pre>';

die();
