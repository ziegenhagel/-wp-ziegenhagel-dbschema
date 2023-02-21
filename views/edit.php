<?php
/** @var TYPE_NAME $page */

echo "<h1>" . $page["title"] . " " . (isset($_GET["id"]) ? "bearbeiten" : "hinzufügen") . "</h1>";

echo "<form method='post' action='?page=" . $_GET["page"] . "'>";

// for all fields
$fields = $page["fields"];

// display inputs for all fields
foreach ($fields as $field) {

    // if field is not hidden
    echo "<label for='" . $field["name"] . "'>" . $field["title"];
    if (!empty($field["required"])) {
        echo "<span class='description'> (Pflichtfeld)</span>";
    }
    echo "</label>";

    echo zdb_render_field($field);

}

echo "<br><br>";
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


