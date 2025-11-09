
$( document ).ready(function() {

    $("#tag_edit_form_colorselector").on('click', '.btn-select-color', function(event){
        let color = $(event.currentTarget).data("color");
        $("#tag_edit_form_color").val(color);
    });

});
