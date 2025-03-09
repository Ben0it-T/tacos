
$( document ).ready(function() {

    $("#customer_edit_form_colorselector").on('click', '.btn-select-color', function(event){
        let color = $(event.currentTarget).data("color");
        $("#customer_edit_form_color").val(color);
    });

    $("#customer_edit_form_teams").change(function(){
        let teamId = $("#customer_edit_form_teams").val();
        let teamName = $("#customer_edit_form_teams option:selected").text();
        $("#customer_edit_form_teams").val("");
        $('#customer_edit_form_teams option[value="'+teamId+'"]').prop('hidden', true);

        let field = "\
        <div class=\"row mb-3 mx-0\" id=\"customer_edit_form_teams_"+teamId+"\">\
            <div class=\"col-sm-9 offset-sm-3 border border-secondary-subtle\">\
                <div class=\"form py-2\">\
                    <input type=\"hidden\" name=\"customer_edit_form[selectedTeams][]\" value=\""+teamId+"\" required=\"required\">\
                    <span class=\"form-label\">"+teamName+"</span>\
                    <button type=\"button\" class=\"btn-remove-member btn btn-light btn-sm d-inline-flex float-end\" value=\""+teamId+"\">\
                        <i class=\"bi bi-trash\"></i>\
                    </button>\
                </div>\
            </div>\
        </div>";
        $("#customer_edit_form_selectedTeams").append(field);
    });

    $("#customer_edit_form_selectedTeams").on('click', '.btn-remove-member', function(event){
        let teamId = $(event.currentTarget).val();
        $("#customer_edit_form_teams_"+teamId).remove();
        $('#customer_edit_form_teams option[value="'+teamId+'"]').prop('hidden', false);
    });

});
