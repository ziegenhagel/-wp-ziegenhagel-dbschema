<?php
/** @var TYPE_NAME $page */

echo "<h1>" . $page["titlePlural"] . "</h1>";
echo "<p>" . ($page["description"] ?? "Hier können Sie alle " . $page["titlePlural"] . " verwalten und bearbeiten.") . "</p>";

// make a table with all the data
echo "<table class='wp-list-table widefat fixed striped table-view-excerpt pages'>";

// display the header for the preview_fields
echo "<thead>";
echo "<tr>";
foreach ($page["preview_fields"] as $col) {
    // pretty format $col, e.g. "created_at" => "Erstellt am"
    // seperate camelCase words
    $col = preg_replace('/(?<!^)([A-Z])/', ' $1', $col);
    $col = str_replace("_", " ", $col);
    $col = str_replace("Id", "ID", $col);
    $col = str_replace("flname", "Vor und Nachname", $col);
    $col = ucfirst($col);

    echo "<th scope='col' id='title' class='manage-column column-title column-primary page-title'>" . __($col) . "</th>";
}
echo "</tr>";
echo "</thead>";

// display the data
echo "<tbody id='the-list'>";
foreach ($data as $key => $row) {
    echo "<tr>";

    // for all preview_fields
    foreach ($page["preview_fields"] as $index => $col) {

        if(empty($row[$col])) {
            $row[$col] = "—";
        }

        // if looks like a datetime (2023-02-21 19:14:26), format it
        if (preg_match("/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/", $row[$col])) {
            $row[$col] = date("d.m.Y \u\m H:i", strtotime($row[$col])) . " Uhr";
        }

        if ($index == 0) {
            echo "<td class='title column-title has-row-actions column-primary page-title' data-colname='Title'>";
            echo "<strong>";
            echo "<a class='row-title' href='?page=" . $_GET["page"] . "&id=" . $row["id"] . "&action=edit'>" . $row[$col] . "</a>";
            echo "</strong>";
            echo "<div class='row-actions'>";
            echo "<span class='edit'><a href='?page=" . $_GET["page"] . "&id=" . $row["id"] . "&action=edit'>" . __("Edit") . "</a></span> | ";
            echo "<span class='trash'><a href='?page=" . $_GET["page"] . "&id=" . $row["id"] . "&action=delete'>" . __("Delete") . "</a></span>";
            echo "</div>";

        } else {
            echo "<td>";
            echo $row[$col];
        }
        echo "</td>";
    }
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";

