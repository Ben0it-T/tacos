
$( document ).ready(function() {
    $("#activity_edit_form_color").change(function(){
        let color = ($("#activity_edit_form_color").val() === "") ? "#ffffff" : $("#activity_edit_form_color").val();
        $("#activity_color").css("background-color", color);
    });

    $("#activity_edit_form_teams").change(function(){
        let teamId = $("#activity_edit_form_teams").val();
        let teamName = $("#activity_edit_form_teams option:selected").text();
        $("#activity_edit_form_teams").val("");
        $('#activity_edit_form_teams option[value="'+teamId+'"]').prop('hidden', true);

        let field = "\
        <div class=\"row mb-3 mx-0\" id=\"activity_edit_form_teams_"+teamId+"\">\
            <div class=\"col-sm-9 offset-sm-3 border border-secondary-subtle\">\
                <div class=\"form py-2\">\
                    <input type=\"hidden\" name=\"activity_edit_form[selectedTeams][]\" value=\""+teamId+"\" required=\"required\">\
                    <span class=\"form-label\">"+teamName+"</span>\
                    <button type=\"button\" class=\"btn-remove-member btn btn-light btn-sm d-inline-flex float-end\" value=\""+teamId+"\">\
                        <i class=\"bi bi-trash\"></i>\
                    </button>\
                </div>\
            </div>\
        </div>";
        $("#activity_edit_form_selectedTeams").append(field);
    });

    $("#activity_edit_form_selectedTeams").on('click', '.btn-remove-member', function(event){
        let teamId = $(event.currentTarget).val();
        $("#activity_edit_form_teams_"+teamId).remove();
        $('#activity_edit_form_teams option[value="'+teamId+'"]').prop('hidden', false);
    });

});
