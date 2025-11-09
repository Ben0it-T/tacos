
$( document ).ready(function() {

    $("#team_edit_form_colorselector").on('click', '.btn-select-color', function(event){
        let color = $(event.currentTarget).data("color");
        $("#team_edit_form_color").val(color);
    });

    $("#team_edit_form_users").change(function(){
        let memberId = $("#team_edit_form_users").val();
        let memberName = $("#team_edit_form_users option:selected").text();
        $("#team_edit_form_users").val("");
        $('#team_edit_form_users option[value="'+memberId+'"]').prop('hidden', true);

        let field = "\
        <div class=\"row mb-3 mx-0\" id=\"team_edit_form_members_"+memberId+"\">\
            <div class=\"col-sm-9 offset-sm-3 border border-secondary-subtle\">\
                <div class=\"form-check py-2\">\
                    <input type=\"hidden\" id=\"team_edit_form_members_"+memberId+"_user\" name=\"team_edit_form[members]["+memberId+"][user]\" value=\"1\" required=\"required\">\
                    <input class=\"form-check-input\" type=\"checkbox\" id=\"team_edit_form_members_"+memberId+"_teamlead\" name=\"team_edit_form[members]["+memberId+"][teamlead]\" value=\"1\">\
                    <label class=\"form-check-label\" for=\"team_edit_form_members_"+memberId+"_teamlead\">"+memberName+"</label>\
                    <button type=\"button\" class=\"btn-remove-member btn btn-light btn-sm d-inline-flex float-end \" value=\""+memberId+"\">\
                        <i class=\"bi bi-trash\"></i>\
                    </button>\
                </div>\
            </div>\
        </div>";
        $("#team_edit_form_members").append(field);
    });

    $("#team_edit_form_members").on('click', '.btn-remove-member', function(event){
        let memberId = $(event.currentTarget).val();
        $("#team_edit_form_members_"+memberId).remove();
        $('#team_edit_form_users option[value="'+memberId+'"]').prop('hidden', false);
    });

    new DataTable('#teams', {
        info: false,
        ordering: false,
        paging: false,
        columnDefs: [
            {targets: 'nosearch', searchable: false},
            {targets: '_all', type: 'string-utf8'}
        ],
        language: {
            search: '<i class="bi bi-search"></i>',
            zeroRecords: '...',
            emptyTable: '...'
        }
    });

});
