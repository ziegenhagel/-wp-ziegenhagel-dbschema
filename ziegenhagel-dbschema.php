<?php
/**
 * Plugin Name: Ziegenhagel DBSchema
 * Description: A plugin for creating a database schema
 * Version: 0.0.4
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
    $table_name = zdb_tablename($schema['name']); // CAVE there didnt used to be a "s" appended

    // push the table object to the tables array
    $fields = zdb_fields_to_sql_object($schema['fields'], $table_name);

    // register the table
    $table_name = zdb_tablename($schema['name']);
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

function zdb_fieldname($table_name)
{
    // the fallback name for wp_zdb_author_favouriteWords is favouriteWords
    $fallback_name = preg_replace('/^.*_/', '', $table_name);
    $fallback_name = preg_replace('/s$/', '', $fallback_name);
    return $fallback_name;
}

function zdb_field_to_sql_object($field, $table_name)
{
    $sql_object = [];

    $sql_object['name'] = zdb_validate_fieldname($field['name'] ?? zdb_fieldname($table_name));
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

/*
$zdb_log = [];

function clg($data)
{
    global $zdb_log;
    $zdb_log[] = $data;
}

function print_clg()
{
    global $zdb_log;
    echo "<pre style='margin-left:180px;margin-top:20px'>CLG:<br>";
    print_r($zdb_log);
    echo "</pre>";
}

// when admin page and all plugins are loaded, print the clg
add_action('admin_body_class', 'print_clg');
*/

function zdb_register_new_table($table_name, $fields = [])
{
    global $schemas, $tables;

    // if we are having an acutual document table, we need to add the _status field
    // add the _status field to the end of the fields array
    // so do this only if its in the schemas array
    if (in_array(zdb_slug($table_name), $schemas)) {
        $status_field = [
            'name' => '_status',
            'type' => 'enum',
            'required' => true,
            'options' => ['publish', 'draft', 'inherit', 'trash']
        ];
        $fields[] = $status_field;
    }

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

    // $schemas[] = $table_name; // TODO clean
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
            function () {
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
            $page["title"] . " hinzuf??gen",
            "Hinzuf??gen",
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
    global $tables, $wpdb;

    // get the table name
    $table_name = $page['table'];

    // get the fields
    $fields = $tables[$table_name];

    // get the data
    $data = zdb_get_data($page);

    // render the page
    echo "<div class='wrap'>";

    $action = $_GET['action'] ?? null;
    if ($action == 'edit') {
        require_once('views/edit.php');
    } else if ($action == "delete") {

        $success = $wpdb->delete($table_name, array('id' => $_GET['id']));
        // print alert, that the entry was deleted
        echo '<div class="notice notice-' . ($success ? 'success' : 'error') . ' is-dismissible">
            <p>' . __($success ? 'Eintrag wurde gel??scht.' : 'Eintrag konnte nicht gel??scht werden.') . '</p>
        </div>';

        // remove the corresponding entry from the data
        foreach ($data as $key => $row) {
            if ($row['id'] == $_GET['id']) {
                unset($data[$key]);
                break;
            }
        }

        require_once('views/list.php');

    } else if ($_GET["page"] == $page['slug'] . "-add") {
        require_once('views/edit.php');
    } else {
        require_once('views/list.php');
    }
    echo "</div>";

    // load the style sheet
    wp_enqueue_style('zdb', plugins_url('render_page.css', __FILE__));

    // load the script
    wp_enqueue_script('zdb', plugins_url('render_page.js', __FILE__));
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
            if (empty($row[$field['name']]) && empty($row[$field['name'] . "Id"]))
                continue;
            if (isset($field['to'])) {
                $ref_page = zdb_page_by_slug($field['to']['type']);
                $prepared = $wpdb->prepare("SELECT " . $ref_page["preview_fields"][0] . " FROM " . $ref_page["table"] . " WHERE id = %d", $row[$field['name'] . "Id"]);
                $data[$key][$field['name']] = $wpdb->get_col($prepared)[0];
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

// a function that takes a field description and returns the html for the input field
function zdb_render_field($field, $context = [])
{

    // deconstruct context
    $parent = $context["parent"] ?? null;

    // if it has a parent, add the parent name to the field name
    if ($parent) {
        // when the name is not defined, use the parent name
        if (empty($field["name"]))
            $field["name"] = $parent["name"];
        else
            $field["name"] = $parent["name"] . "." . $field["name"];

        $field["name"] .= "[0]";
    }

    $name = $field["name"];

    // if field is a text input
    if ($field["type"] == "text") {
        echo "<input type='text' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    } // if field is a textarea
    else if ($field["type"] == "textarea") {
        echo "<textarea name='" . $name . "' id='" . $name . "' rows='5' cols='40'>" . ($field["value"] ?? "") . "</textarea>";
    } // if field is a wysiwyg
    else if ($field["type"] == "wysiwyg") {
        wp_editor(($field["value"] ?? ""), $name, array(
            "textarea_name" => $name,
            "textarea_rows" => 10,
            "media_buttons" => false,
            "teeny" => true,
            "quicktags" => false,
        ));
    } // if field is a date
    else if ($field["type"] == "date") {
        echo "<input type='date' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    } // if field is a time
    else if ($field["type"] == "time") {
        echo "<input type='time' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    } // if field is a datetime
    else if ($field["type"] == "datetime") {
        echo "<input type='datetime-local' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    } // if field is an image use the wp mediathek medie uploader and show a mini preview
    else if ($field["type"] == "image") {
        echo "<input type='hidden' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "'>";
        echo "<img src='" . ($field["value"] ?? "") . "' style='max-width: 100px;'>";
        echo "<input type='button' class='button button-primary' value='Bild ausw??hlen' onclick='wp.media.editor.send.attachment = function(props, attachment) { jQuery(\"#" . $name . "\").val(attachment.url); }; wp.media.editor.open(this); return false;'>";
    } // if field is a select
    else if ($field["type"] == "enum") {
        echo "<select name='" . $name . "' id='" . $name . "'>";
        foreach ($field["options"] as $option) {
            echo "<option value='" . $option . "'>" . $option . "</option>";
        }
        echo "</select>";
    } // if field is a checkbox
    else if ($field["type"] == "checkbox") {
        echo "<input type='checkbox' name='" . $name . "' id='" . $name . "' value='1'>";
    } else if ($field["type"] == "reference") {
        $ref_page = zdb_page_by_slug($field["to"]["type"]);
        $ref_data = zdb_get_objects($ref_page["slug"]);
        echo "<select name='" . $name . "' id='" . $name . "'>";
        foreach ($ref_data as $ref_row) {
            echo "<option value='" . $ref_row["id"] . "'>" . $ref_row[$ref_page["preview_fields"][0]] . "</option>";
        }
        echo "</select>";
    } else if ($field["type"] == "object") {
        echo "<div class='zdb-object-container'>";
        foreach ($field["fields"] as $subfield) {
            zdb_render_field($subfield, ["parent" => $field]);
        }
        echo "</div>";
    } else if ($field["type"] == "array") {

        $uniqid = uniqid();
        echo "<div class='zdb-prototype' style='display: none;'>";
        echo "<div class='zdb-array-element'>";
        zdb_render_field($field["of"], ["parent" => $field]);
        echo "</div>";
        echo "</div>";
        echo "<div class='zdb-array-container'><div  class='zdb-spawn'></div>";
        echo "<input type='button' class='button button-secondary' value='Neues Element hinzuf??gen' id='zdb_spawn_prototype_btn" . $uniqid . "' onclick='zdb_spawn_prototype(this)'>";
        echo "</div>";
        echo "<script>document.addEventListener('DOMContentLoaded', function() {
                // press the button to spawn the prototype
                document.getElementById('zdb_spawn_prototype_btn" . $uniqid . "').click();
            });</script>";

    } else {
        echo "Field type not found.";
    }
}

// query function to get the data from the database
function zdb_get_object($slug, $id)
{
    global $wpdb;
    $prepared = $wpdb->prepare("SELECT * FROM " . zdb_tablename($slug) . " WHERE id = %d", $id);
    $object = $wpdb->get_row($prepared, ARRAY_A);

    // populate the references
    zdb_populate_references($object, $slug);

    return $object;
}

function zdb_get_objects($slug)
{
    global $wpdb;
    $objects = $wpdb->get_results("SELECT * FROM " . zdb_tablename($slug), ARRAY_A);

    // populate the references for each object using the zdb_populate_references function
    foreach ($objects as &$object) {
        zdb_populate_references($object, $slug);
    }

    return $objects;
}

function zdb_tablename($slug)
{
    global $wpdb;
    return $wpdb->prefix . "zdb_" . $slug . "s";
}

function zdb_slug($tablename)
{
    global $wpdb;
    $plural = str_replace($wpdb->prefix . "zdb_", "", $tablename);
    // remove the last s
    return substr($plural, 0, -1);
}

function zdb_populate_references(&$object, $slug)
{
    global $wpdb;

    $page = zdb_page_by_slug($slug);

    // populate the references
    foreach ($page["fields"] as $field) {
        if (isset($field["to"]) && isset($object[$field["name"] . "Id"])) {
            $prepared = $wpdb->prepare("SELECT * FROM " . zdb_tablename($field["to"]["type"]) . " WHERE id = %d", $object[$field["name"] . "Id"]);
            $object[$field["name"]] = $wpdb->get_row($prepared, ARRAY_A);
        }
    }
}

// register all the pages for backend
add_action('admin_menu', 'zdb_admin_menu');

// register the activation hook
register_activation_hook(__FILE__, 'zdb_install');

