/*
function zdb_copy_element(src, dst) {
    var src = document.getElementById(src);
    var dst = document.getElementById(dst);

    // append the inside of the element src into the element dst
    dst.innerHTML += src.innerHTML;
}
 */
function zdb_copy_element(prototype, spawn) {
    jQuery(spawn).append(jQuery(prototype).html());
    console.log("zdb_copy_element: " + prototype + " -> " + spawn);
}

function zdb_spawn_prototype(button) {
    // get the .zdb-prototype which is before this button
    var prototype = jQuery(button).parent().prev('.zdb-prototype').first();
    var spawn = jQuery(button).parent().find('.zdb-spawn').first();
    zdb_copy_element(prototype, spawn);
}