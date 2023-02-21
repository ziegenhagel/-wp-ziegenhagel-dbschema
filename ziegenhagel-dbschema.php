<?php
/**
 * Plugin Name: Ziegenhagel DBSchema
 * Description: A plugin for creating a database schema
 * Version: 0.0.2
 * Author: Ziegenhagel
 * Author URI: https://ziegenhagel.com
 * Text Domain: zdb
 */

// get plugin version from the plugin header
$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$version = $plugin_data['Version'];

// load the list of schemas_sanity from schemas/*.json
$schemas = array_map(function ($file) {
    return basename($file, '.json');
}, glob(__DIR__ . '/schemas/*.json'));

$pages = [];

// db prefix
/** @var TYPE_NAME $wpdb */
$prefix = $wpdb->prefix . 'zdb_';

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
    $fields = zdb_fields_to_sql_object($schema['fields'], $table_name);

    // register the table
    $table_name = $table_name . "s";
    zdb_register_new_table($table_name, $fields);

    // register for the admin menu
    $pages[] = [
        "title" => $schema['title'],
        "titlePlural" => $schema['titlePlural'],
        "preview_fields" => $schema['preview_fields'],
        "table" => $table_name,
        "slug" => $schema['name'],
        "wordpress" => $schema['wordpress'] ?? [],
        "fields" => $schema['fields'],
        "columns" => $fields,
    ];
}

function zdb_fields_to_sql_object($fields, $table_name)
{
    $sql_object = [];

    foreach ($fields as $field) {
        $sql_object[] = zdb_field_to_sql_object($field, $table_name);
    }

    return $sql_object;
}

function zdb_field_to_sql_object($field, $table_name)
{
    $sql_object = [];

    // the fallback name for wp_zdb_author_favouriteWords is favouriteWords
    $fallback_name = preg_replace('/^.*_/', '', $table_name);

    // remove the s at the end
    $fallback_name = preg_replace('/s$/', '', $fallback_name);

    $sql_object['name'] = zdb_validate_fieldname($field['name'] ?? $fallback_name);
    $sql_object['type'] = $field['type'];
    $sql_object['default'] = $field['default'] ?? '';
    $sql_object['required'] = $field['required'] ?? false;

    if ($field['type'] === 'text') {
        $sql_object['length'] = 250;
    } else if ($field['type'] === 'image') {
        $sql_object['type'] = 'int';
        $sql_object['name'] = $field['name'] . '_attachmentId';
        $sql_object['length'] = 11;
    } else if ($field['type'] === 'wysiwyg') {
        $sql_object['type'] = 'text';
        $sql_object['length'] = 10000;
    } else if ($field['type'] === 'number') {
        $sql_object['type'] = 'int';
        $sql_object['length'] = 11;
    } else if ($field['type'] === 'boolean') {
        $sql_object['type'] = 'tinyint';
        $sql_object['length'] = 1;
    } else if ($field['type'] === 'enum') {
        $sql_object['type'] = 'enum';
        $sql_object['options'] = $field['options'];
    } else if ($field['type'] === 'reference') {
        zdb_apply_field_reference($sql_object, $field);
    } else if ($field['type'] === 'array') {
        zdb_apply_field_array($sql_object, $field, $table_name);
    } else if ($field['type'] === 'object') {
        zdb_apply_field_object($sql_object, $field, $table_name);
    }

    return $sql_object;
}

function zdb_apply_field_reference(&$sql_object, $field)
{
    if ($field['type'] !== 'reference')
        return "Field is not a reference";

    // do we know this type? is it a book, is it an author?
    global $schemas;
    if (in_array($field['to']['type'], $schemas)) {
        $sql_object['type'] = 'int';
        $sql_object['name'] = $field['to']['type'] . 'Id';
    } else {
        echo "Reference type '" . $field['to']['type'] . "' not found in schemas";
    }
}

function zdb_apply_field_array(&$sql_object, $field, $table_name)
{
    if ($field['type'] !== 'array')
        return "Field is not an array";

    // create a new table name and get the fields for the new table
    $new_table_name = $table_name . "_" . zdb_validate_fieldname($field['name']);

    $fields[] = zdb_field_to_sql_object($field["of"], $new_table_name);

    // make the field a reference to the new table
    $sql_object['_resolved'] = true;
    // $sql_object = null;

    // we want an array? so we need a new table schema, referencing the current table via id and connecting to the referenced table or embedding the content
    // we need to know the name of the current table, the name of the referenced table and the name of the new table
    global $prefix;

    if ($field['of']['type'] !== 'object')
        zdb_register_new_table($new_table_name, $fields);

}

function zdb_apply_field_object(&$sql_object, $fields, $table_name)
{
    if ($fields['type'] !== 'object')
        return "Field is not an object";

    // create a new table name and get the fields for the new table
    $new_table_name = $table_name;
    $fields = zdb_fields_to_sql_object($fields["fields"], $new_table_name);

    // make the field a reference to the table it belongs to
    global $prefix;
    $clean_table_name = str_replace($prefix, '', $table_name);
    // remove between the last underscore and the end
    $clean_table_name = preg_replace('/_[^_]+$/', '', $clean_table_name);
    $fields[] = [
        'name' => $clean_table_name . 'Id',
        'type' => 'int',
        'required' => true,
    ];

    // we want an array? so we need a new table schema, referencing the current table via id and connecting to the referenced table or embedding the content
    // we need to know the name of the current table, the name of the referenced table and the name of the new table
    global $prefix;
    zdb_register_new_table($new_table_name, $fields);

}


function zdb_register_new_table($table_name, $fields = [])
{
    global $schemas, $tables;

    // check if the table already exists
    if (in_array($table_name, $schemas))
        return;

    // add the id field to the beginning of the fields array
    $id_field = [
        'name' => 'id',
        'type' => 'int',
        'required' => true,
        'length' => 11,
        'auto_increment' => true,
        'primary_key' => true
    ];
    array_unshift($fields, $id_field);

    $schemas[] = $table_name;
    $tables[$table_name] = $fields;

}

function zdb_validate_fieldname($field_name)
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

function zdb_create_sql_statements($tables)
{
    $sql_statements = [];

    foreach ($tables as $table_name => $fields) {
        $sql_statements[] = zdb_create_sql_statement($table_name, $fields);
    }

    return implode(PHP_EOL, $sql_statements);
}

function zdb_create_sql_statement($table_name, $fields)
{
    global $wpdb;

    $sql_statement = "CREATE TABLE `" . $table_name . "` (" . PHP_EOL; //  IF NOT EXISTS

    // add the fields
    foreach ($fields as $field) {
        if ($field['_resolved'] === true)
            continue;
        $sql_statement .= '  ' . zdb_create_sql_field($field) . PHP_EOL;
    }

    // remove the last PHP_EOL and comma
    $sql_statement = substr($sql_statement, 0, -3);

    $sql_statement .= PHP_EOL . ")" . $wpdb->get_charset_collate() . ";" . PHP_EOL;

    return $sql_statement;
}

function zdb_create_sql_field($field)
{
    $sql_field = "`" . $field['name'] . "` " . $field['type'];

    if (isset($field['options']))
        $sql_field .= "('" . implode("','", $field['options']) . "')";

    else if (isset($field['length']))
        $sql_field .= "(" . $field['length'] . ")";

    if (isset($field['required']) && $field['required'] === true)
        $sql_field .= " NOT NULL";

    if (isset($field['auto_increment']) && $field['auto_increment'] === true)
        $sql_field .= " AUTO_INCREMENT";

    if (isset($field['primary_key']) && $field['primary_key'] === true)
        $sql_field .= " PRIMARY KEY";

    // default value
    if ($field['default'])
        $sql_field .= " DEFAULT '" . $field['default'] . "'";

    // if the field is a reference to another table, add the foreign key
    if (isset($field['to'])) {
        $sql_field .= ", FOREIGN KEY (`" . $field['name'] . "`) REFERENCES `" . $field['to']['table'] . "`(`id`)";
    }

    $sql_field .= ", ";

    return $sql_field;
}

// output the tables as json
// echo '<pre>';
// echo json_encode($tables, JSON_PRETTY_PRINT);
// echo '</pre>';

function zdb_install()
{
    global $version, $tables;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta(zdb_create_sql_statements($tables));

    update_option('zdb_version', $version);
}

function zdb_update_db_check()
{
    global $version;
    if (get_option('zdb_version') != $version) {
        zdb_install();
    }
}

add_action('plugins_loaded', 'zdb_update_db_check');

function zdb_admin_menu()
{
    global $pages;

    // add the menu page for each schema
    foreach ($pages as $page) {

        $options = $page["wordpress"];


        // add the menu page
        add_menu_page(
            $page['title'],
            $page['title'],
            $options['capability'] ?? 'manage_options',
            $page['slug'],
            $function = function () use ($page) {
                zdb_render_page($page);
            },
            $options['menu_icon'],
            $options['menu_position'] ?? 100
        );

        // add submenu pages for Alle and Neu
        add_submenu_page(
            $page['slug'],
            $page['titlePlural'],
            'Alle ' . $page['titlePlural'],
            $options['capability'] ?? 'manage_options',
            $page['slug'],
            $function = function () use ($page) {
                zdb_render_page($page);
            }
        );

        add_submenu_page(
            $page['slug'],
            $page["title"] . " hinzufügen",
            "Hinzufügen",
            $options['capability'] ?? 'manage_options',
            $page['slug'] . "-add",
            $function = function () use ($page) {
                zdb_render_page($page);
            }
        );

    }
}

// render page
function zdb_render_page($page)
{
    global $tables;

    // get the table name
    $table_name = $page['table'];

    // get the fields
    $fields = $tables[$table_name];

    // get the data
    $data = zdb_get_data($page);

    extract($page);

    // render the page
    require_once('views/page.php');
}

// get the data from the database
function zdb_get_data($page)
{
    global $wpdb, $prefix, $fields, $schemas, $pages;

    $table_name = $page['table'];

    // get the data and fetch the references as well
    $data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    // get the data for the references
    foreach ($data as $key => $row) {
        foreach ($page["fields"] as $field) {
            // skip empty fields
            if (empty($row[$field['name']]))
                continue;
            if (isset($field['to'])) {
                $ref_page = zdb_page_by_slug($field['to']['type']);
                $prepared = $wpdb->prepare("SELECT " . $ref_page["preview_fields"][0] . " FROM " . $ref_page["table"] . " WHERE id = %d", $row[$field['name'] . "Id"]);
                $data[$key][$field['name']] = $wpdb->get_col($prepared)[0] ?? "—";
            }
        }
    }

    return $data;
}

function zdb_page_by_slug($type)
{
    global $pages;

    foreach ($pages as $page) {
        if ($page['slug'] === $type) {
            return $page;
        }
    }
}

// register all the pages for backend
add_action('admin_menu', 'zdb_admin_menu');

// register the activation hook
register_activation_hook(__FILE__, 'zdb_install');

