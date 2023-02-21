<?php
/** @var TYPE_NAME $page */

echo "<h1>" . $page["title"] . " hinzufügen</h1>";

echo "<form method='post' action='?page=" . $_GET["page"] . "'>";

// for all fields
$fields = $page["fields"];

// display inputs for all fields
foreach ($fields as $field) {

    $name = $field["name"];

    // if field is not hidden
    if ($field["type"] != "hidden") {
        echo "<div class='form-field'>";
        echo "<label for='" . $name . "'>" . $field["title"];
        if (!empty($field["required"])) {
            echo "<span class='description'> (Pflichtfeld)</span>";
        }
        echo "</label>";

    }

    // if field is a text input
    if ($field["type"] == "text") {
        echo "<input type='text' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    }

    // if field is a textarea
    if ($field["type"] == "textarea") {
        echo "<textarea name='" . $name . "' id='" . $name . "' rows='5' cols='40'>" . ($field["value"] ?? "") . "</textarea>";
    }

    // if field is a wysiwyg
    if ($field["type"] == "wysiwyg") {
        wp_editor(($field["value"] ?? ""), $name, array(
            "textarea_name" => $name,
            "textarea_rows" => 10,
            "media_buttons" => false,
            "teeny" => true,
            "quicktags" => false,
        ));
    }

    // if field is a date
    if ($field["type"] == "date") {
        echo "<input type='date' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    }

    // if field is a time
    if ($field["type"] == "time") {
        echo "<input type='time' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    }

    // if field is a datetime
    if ($field["type"] == "datetime") {
        echo "<input type='datetime-local' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "' size='40' aria-required='true' autocapitalize='none' autocorrect='off' maxlength='60'>";
    }

    // if field is an image use the wp mediathek medie uploader and show a mini preview
    if ($field["type"] == "image") {
        echo "<input type='hidden' name='" . $name . "' id='" . $name . "' value='" . ($field["value"] ?? "") . "'>";
        echo "<img src='" . ($field["value"] ?? "") . "' style='max-width: 100px;'>";
        echo "<input type='button' class='button button-primary' value='Bild auswählen' onclick='wp.media.editor.send.attachment = function(props, attachment) { jQuery(\"#" . $name . "\").val(attachment.url); }; wp.media.editor.open(this); return false;'>";
    }

    // if field is a select
    if ($field["type"] == "enum") {
        echo "<select name='" . $name . "' id='" . $name . "'>";
        foreach ($field["options"] as $option) {
            echo "<option value='" . $option . "'>" . $option . "</option>";
        }
        echo "</select>";
    }

    // if field is a checkbox
    if ($field["type"] == "checkbox") {
        echo "<input type='checkbox' name='" . $name . "' id='" . $name . "' value='1'>";
    }

    // print
    print_r($field);

    echo "</div>";


}
echo "<input type='submit' value='Speichern' class='button button-primary'>";
echo "</form>";

?>
<script>

    // via jquery, show all the form field values
    jQuery(document).ready(function () {
        jQuery("form").submit(function (e) {
            e.preventDefault();
            var data = jQuery(this).serializeArray();
            console.log(data);
        });
    });
</script>


