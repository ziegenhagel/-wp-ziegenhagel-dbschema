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

<pre id="form-values" style="background-color: #eee; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px;white-space: pre-wrap;word-wrap: break-word;">

</pre>
<script>

    // via jquery, show all the form field values
    jQuery(document).ready(function () {
        // every time the form changes, show the values into the #form-values div
        jQuery("form").change(function () {
            // pretty print the values
            jQuery("#form-values").html(JSON.stringify(jQuery(this).serializeArray(), null, 2));
        });
    });
</script>


