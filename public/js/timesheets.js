
function toggleProjectsOptions(data) {
    $("#timesheet_edit_form_project option").prop('hidden', true);
    $("#timesheet_edit_form_project option").prop('disabled', true);
    $("#timesheet_edit_form_project option[value='']").prop('hidden', false);
    $("#timesheet_edit_form_project option[value='']").prop('disabled', false);
    $("#timesheet_edit_form_project").val('');

    $("#timesheet_edit_form_activity option").prop('hidden', true);
    $("#timesheet_edit_form_activity option").prop('disabled', true);
    $("#timesheet_edit_form_activity").val('');

    $("#timesheet_edit_form_project option").each(function() {
        if (data.includes(parseInt($(this).val()))) {
            $('#timesheet_edit_form_project option[value="'+$(this).val()+'"]').prop('hidden', false);
            $('#timesheet_edit_form_project option[value="'+$(this).val()+'"]').prop('disabled', false);
        }
    });
}

function toggleActivitiesOptions(data) {
    $("#timesheet_edit_form_activity option").prop('hidden', true);
    $("#timesheet_edit_form_activity option").prop('disabled', true);
    $("#timesheet_edit_form_activity option[value='']").prop('hidden', false);
    $("#timesheet_edit_form_activity option[value='']").prop('disabled', false);
    $("#timesheet_edit_form_activity").val('');

    $("#timesheet_edit_form_activity option").each(function() {
        if (data.includes(parseInt($(this).val()))) {
            $('#timesheet_edit_form_activity option[value="'+$(this).val()+'"]').prop('hidden', false);
            $('#timesheet_edit_form_activity option[value="'+$(this).val()+'"]').prop('disabled', false);
        }
    });
}

$( document ).ready(function() {
    const basePath = $("body").attr("basepath");

    const pickerStartDate = new tempusDominus.TempusDominus(document.getElementById('pickerStartDate'), {
        allowInputToggle: false,
        display: {
            icons: tempusIcons,
            viewMode: 'calendar',
            placement: 'bottom',
            calendarWeeks: true,
            buttons: {
                today: true,
                clear: false,
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
        },
        localization: {
            dateFormats: {
                L: $("#dateFormats_L").val(),
            },
            startOfTheWeek: $("#dateFormats_startOfTheWeek").val(),
            format: 'L',
        },
    });

    const pickerStartTime = new tempusDominus.TempusDominus(document.getElementById('pickerStartTime'), {
        allowInputToggle: false,
        display: {
            icons: tempusIcons,
            viewMode: 'clock',
            placement: 'bottom',
            buttons: {
                today: false,
                clear: false,
                close: true
            },
            components: {
                calendar: false,
                date: false,
                month: false,
                year: false,
                decades: false,
                clock: true,
                hours: true,
                minutes: true,
                seconds: false,
                useTwentyfourHour: undefined
            },
            theme: tempusTheme,
        },
        localization: {
            hourCycle: 'h23',
            dateFormats: {
                LT: $("#dateFormats_LT").val(),
            },
            format: 'LT',

        },
    });

    const pickerDuration = new tempusDominus.TempusDominus(document.getElementById('pickerDuration'), {
        allowInputToggle: false,
        display: {
            icons: tempusIcons,
            viewMode: 'clock',
            placement: 'bottom',
            buttons: {
                today: false,
                clear: true,
                close: true
            },
            components: {
                calendar: false,
                date: false,
                month: false,
                year: false,
                decades: false,
                clock: true,
                hours: true,
                minutes: true,
                seconds: false,
                useTwentyfourHour: undefined
            },
            theme: tempusTheme,
        },
        localization: {
            hourCycle: 'h23',
            dateFormats: {
                LT: $("#dateFormats_LT").val(),
            },
            format: 'LT',
        },
        useCurrent: false,
    });

    const pickerEndTime = new tempusDominus.TempusDominus(document.getElementById('pickerEndTime'), {
        allowInputToggle: false,
        display: {
            icons: tempusIcons,
            viewMode: 'clock',
            placement: 'bottom',
            buttons: {
                today: false,
                clear: true,
                close: true
            },
            components: {
                calendar: false,
                date: false,
                month: false,
                year: false,
                decades: false,
                clock: true,
                hours: true,
                minutes: true,
                seconds: false,
                useTwentyfourHour: undefined
            },
            theme: tempusTheme,
        },
        localization: {
            hourCycle: 'h23',
            dateFormats: {
                LT: $("#dateFormats_LT").val(),
            },
            format: 'LT',
        },
        useCurrent: false,
    });


    // Default dates
    let minDate = new Date($("#startDate").val());
    minDate.setHours(0);
    minDate.setMinutes(0);
    minDate.setSeconds(0);
    minDate.setMilliseconds(0);

    let maxDate = new Date($("#startDate").val());
    maxDate.setHours(23);
    maxDate.setMinutes(59);
    maxDate.setSeconds(59);
    maxDate.setMilliseconds(0);

    let currentDate = new Date($("#startDate").val());
    currentDate.setSeconds(0);
    currentDate.setMilliseconds(0);

    let endDate = new Date($("#endDate").val());
    endDate.setSeconds(0);
    endDate.setMilliseconds(0);

    let maxDuration = minDate.valueOf() + maxDate.valueOf() - currentDate.valueOf();
    let maxDurationDate = new Date(maxDuration);
    maxDurationDate.setSeconds(0);
    maxDurationDate.setMilliseconds(0);

    // Update td dates
    pickerStartTime.dates.setValue(pickerStartTime.dates.parseInput(currentDate));

    if ($("#timesheet_edit_form_duration").val() == "") {
        pickerDuration.dates.setValue(pickerDuration.dates.parseInput(minDate));
        pickerDuration.dates.clear();
    }
    else {
        let duration = minDate.valueOf() + endDate.valueOf() - currentDate.valueOf();
        pickerDuration.dates.setValue(pickerDuration.dates.parseInput(new Date(duration)));
    }

    if ($("#timesheet_edit_form_end_time").val() == "") {
        pickerEndTime.dates.setValue(pickerEndTime.dates.parseInput(currentDate));
        pickerEndTime.dates.clear();
    }
    else {
        pickerEndTime.dates.setValue(pickerEndTime.dates.parseInput(endDate));
    }

    // Update td restrictions
    pickerStartTime.updateOptions({
        restrictions: {
          minDate: minDate,
          maxDate: maxDate,
        },
    });

    pickerEndTime.updateOptions({
        restrictions: {
          minDate: currentDate,
          maxDate: maxDate,
        },
    });

    pickerDuration.updateOptions({
        restrictions: {
          minDate: minDate,
          maxDate: maxDurationDate,
        },
    });

    // Events
    $("#pickerStartTime").on("change.td", function (e) {
        maxDuration = minDate.valueOf() + maxDate.valueOf() - e.date.valueOf();
        maxDurationDate = new Date(maxDuration);

        pickerDuration.updateOptions({
            restrictions: {
              maxDate: maxDurationDate,
            },
        });

        pickerEndTime.updateOptions({
            restrictions: {
              minDate: e.date,
            },
        });

        if (pickerEndTime.dates.lastPicked !== undefined) {
            let startTime = pickerStartTime.dates.lastPicked;
            startTime.setSeconds(0);
            startTime.setMilliseconds(0);

            let endTime = pickerEndTime.dates.lastPicked;
            endTime.setSeconds(0);
            endTime.setMilliseconds(0);

            let diff = minDate.valueOf() + endTime.valueOf() - startTime.valueOf();
            let diffDate = new Date(diff);
            pickerDuration.dates.add(pickerDuration.dates.parseInput(diffDate));
            pickerDuration.viewDate = pickerDuration.dates.parseInput(diffDate);
            $("#timesheet_edit_form_duration").val(zerofilled(diffDate.getHours()) + ":" + zerofilled(diffDate.getMinutes()));
        }
        else {
            $("#timesheet_edit_form_duration").val('');
        }
    });

    $("#pickerEndTime").on("change.td", function (e) {
        pickerStartTime.updateOptions({
            restrictions: {
              maxDate: e.date,
            },
        });

        if (pickerEndTime.dates.lastPicked !== undefined) {
            let startTime = pickerStartTime.dates.lastPicked;
            startTime.setSeconds(0);
            startTime.setMilliseconds(0);

            let endTime = pickerEndTime.dates.lastPicked;
            endTime.setSeconds(0);
            endTime.setMilliseconds(0);

            let diff = minDate.valueOf() + endTime.valueOf() - startTime.valueOf();
            let diffDate = new Date(diff);
            pickerDuration.dates.add(pickerDuration.dates.parseInput(diffDate));
            pickerDuration.viewDate = pickerDuration.dates.parseInput(diffDate);
            $("#timesheet_edit_form_duration").val(zerofilled(diffDate.getHours()) + ":" + zerofilled(diffDate.getMinutes()));
        }
        else {
            $("#timesheet_edit_form_duration").val('');
        }
    });

    $("#pickerDuration").on("change.td", function (e) {
        if (pickerDuration.dates.lastPicked !== undefined) {
            let startTime = pickerStartTime.dates.lastPicked;
            startTime.setSeconds(0);
            startTime.setMilliseconds(0);

            let duration = pickerDuration.dates.lastPicked;
            duration.setSeconds(0);
            duration.setMilliseconds(0);

            let diff = startTime.valueOf() + duration.valueOf() - minDate.valueOf();
            let diffDate = new Date(diff);
            pickerEndTime.dates.add(pickerEndTime.dates.parseInput(diffDate));
            $("#timesheet_edit_form_end_time").val(zerofilled(diffDate.getHours()) + ":" + zerofilled(diffDate.getMinutes()));
        }
        else {
            $("#timesheet_edit_form_end_time").val('');
        }
    });







    $("#timesheet_edit_form_tags").change(function(){
        let memberId = $("#timesheet_edit_form_tags").val();
        let memberName = $("#timesheet_edit_form_tags option:selected").text();
        let memberColor = $("#timesheet_edit_form_tags option:selected").attr('color');
        $("#timesheet_edit_form_tags").val("");
        $('#timesheet_edit_form_tags option[value="'+memberId+'"]').prop('hidden', true);

        let field = "\
        <div class=\"me-2\" id=\"timesheet_edit_form_tags_"+memberId+"\">\
            <input type=\"hidden\" name=\"timesheet_edit_form[selectedTags][]\" value=\""+memberId+"\" required=\"required\">\
            <span class=\"btn-remove-member badge\" style=\"background-color: "+memberColor+";\" value=\""+memberId+"\">"+memberName+"<i class=\"bi bi-x ms-1 border-start\"></i></span>\
        </div>";


        $("#timesheet_edit_form_selectedTags").append(field);
    });

    $("#timesheet_edit_form_selectedTags").on('click', '.btn-remove-member', function(event){
        let memberId = $(event.currentTarget).attr('value');
        $("#timesheet_edit_form_tags_"+memberId).remove();
        $('#timesheet_edit_form_tags option[value="'+memberId+'"]').prop('hidden', false);
    });

    $("#timesheet_edit_form_customer").change(function(){
        $.ajax({
            url: basePath+'/xhr/projects/' + $("#timesheet_edit_form_customer").val(),
            type: 'get',
            timeout: 15000,
            dataType: 'json',
            success: function(data) {
                toggleProjectsOptions(data);
            },
            error: function() {
                //
                console.log("error");
            }
        });
    });

    $("#timesheet_edit_form_project").change(function(){
        $.ajax({
            url:  basePath+'/xhr/activities/' + $("#timesheet_edit_form_project").val(),
            type: 'get',
            timeout: 15000,
            dataType: 'json',
            success: function(data) {
                toggleActivitiesOptions(data);
            },
            error: function() {
                //
                console.log("error");
            }
        });
    });

});
