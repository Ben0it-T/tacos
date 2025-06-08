
$( document ).ready(function() {
    const pickerDateFilter = new tempusDominus.TempusDominus(document.getElementById('pickerDateFilter'), {
        allowInputToggle: true,
        dateRange: true,
        multipleDatesSeparator: ' - ',
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

    $("#btnSubmit").click(function(){
        let dates = pickerDateFilter.dates.picked;
        let dateStart = dates[0];
        let dateEnd = (typeof dates[1] !== 'undefined') ? dates[1] : dates[0];

        dateStart = dateStart.getFullYear() + "-" + zerofilled(dateStart.getMonth() + 1) + "-" + zerofilled(dateStart.getDate());
        dateEnd = dateEnd.getFullYear() + "-" + zerofilled(dateEnd.getMonth() + 1) + "-" + zerofilled(dateEnd.getDate());

        $("#date").val(dateStart + " - " + dateEnd);
        $("form").submit();
    });
});
