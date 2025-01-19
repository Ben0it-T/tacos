
$( document ).ready(function() {
    $("#tag_edit_form_color").change(function(){
        let color = ($("#tag_edit_form_color").val() === "") ? "#ffffff" : $("#tag_edit_form_color").val();
        $("#tag_color").css("background-color", color);
    });
});
