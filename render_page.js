/*
function zdb_copy_element(src, dst) {
    var src = document.getElementById(src);
    var dst = document.getElementById(dst);

    // append the inside of the element src into the element dst
    dst.innerHTML += src.innerHTML;
}
 */
function zdb_copy_element(prototype, spawn) {

    /*
    // if the prototypes name attribute ends with [1] then we need to increment the number
    var name = jQuery(prototype).find('*[name]').first().attr('name');
    var name_parts = name.split(/\[(\d+)\]$/);
    console.log("zdb_copy_element: name_parts = " + name_parts);

    if(name_parts[1] != undefined) {
        let new_name = name_parts[0] + "[" + (parseInt(name_parts[1]) + 1) + "]";
        console.log("zdb_copy_element: new_name = " + new_name);
        jQuery(prototype).find('*[name]').first().attr('name', new_name);
    }
     */

    jQuery(spawn).append(jQuery(prototype).html());
    console.log("zdb_copy_element: " + prototype + " -> " + spawn);
}

function zdb_spawn_prototype(button) {
    // get the .zdb-prototype which is before this button
    var prototype = jQuery(button).parent().prev('.zdb-prototype').first();
    var spawn = jQuery(button).parent().find('.zdb-spawn').first();
    zdb_copy_element(prototype, spawn);
}