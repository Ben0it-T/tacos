
$( document ).ready(function() {

    $("#project_edit_form_colorselector").on('click', '.btn-select-color', function(event){
        let color = $(event.currentTarget).data("color");
        $("#project_edit_form_color").val(color);
    });

    $("#project_edit_form_teams").change(function(){
        let teamId = $("#project_edit_form_teams").val();
        let teamName = $("#project_edit_form_teams option:selected").text();
        $("#project_edit_form_teams").val("");
        $('#project_edit_form_teams option[value="'+teamId+'"]').prop('hidden', true);

        let field = "\
        <div class=\"row mb-3 mx-0\" id=\"project_edit_form_teams_"+teamId+"\">\
            <div class=\"col-sm-9 offset-sm-3 border border-secondary-subtle\">\
                <div class=\"form py-2\">\
                    <input type=\"hidden\" name=\"project_edit_form[selectedTeams][]\" value=\""+teamId+"\" required=\"required\">\
                    <span class=\"form-label\">"+teamName+"</span>\
                    <button type=\"button\" class=\"btn-remove-member btn btn-light btn-sm d-inline-flex float-end\" value=\""+teamId+"\">\
                        <i class=\"bi bi-trash\"></i>\
                    </button>\
                </div>\
            </div>\
        </div>";
        $("#project_edit_form_selectedTeams").append(field);
    });

    $("#project_edit_form_selectedTeams").on('click', '.btn-remove-member', function(event){
        let teamId = $(event.currentTarget).val();
        $("#project_edit_form_teams_"+teamId).remove();
        $('#project_edit_form_teams option[value="'+teamId+'"]').prop('hidden', false);
    });

    $("#project_edit_form_globalactivities").click(function(){
        let checked = $("#project_edit_form_globalactivities").prop('checked');
        if (checked) {
            $("#project_edit_form_global_activities").show();
        }
        else {
            $("#project_edit_form_global_activities").hide();
        }
    });



    const dateTimePickerDisplayOptions = {
        icons: tempusIcons,
        viewMode: 'calendar',
        placement: 'top',
        calendarWeeks: true,
        buttons: {
            today: true,
            clear: true,
            close: true
        },
        components: {
            calendar: true,
            date: true,
            month: true,
            year: true,
            decades: true,
            clock: false,
            hours: false,
            minutes: false,
            seconds: false,
            useTwentyfourHour: undefined
        },
        theme: tempusTheme,
    };

    const dateTimePickerLocalization = {
        dateFormats: {
            L: $("#dateFormats_L").val(),
        },
        startOfTheWeek: $("#dateFormats_startOfTheWeek").val(),
        format: 'L',
    };

    new tempusDominus.TempusDominus(document.getElementById('datetimepicker1'), {
        allowInputToggle: true,
        display: dateTimePickerDisplayOptions,
        localization: dateTimePickerLocalization,
    });

    new tempusDominus.TempusDominus(document.getElementById('datetimepicker2'), {
        allowInputToggle: true,
        display: dateTimePickerDisplayOptions,
        localization: dateTimePickerLocalization,
    });

});
