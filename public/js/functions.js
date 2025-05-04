
function zerofilled(i) {
    if (i < 10) {i = "0" + i}
    return i;
}

function timeToString(time) {
    let seconds = time;
    let minutes = Math.floor(seconds/60);
    let hours = Math.floor(minutes/60);

    return zerofilled(hours) + ":" + zerofilled(minutes%60);
}

function setDuration() {
    if ($("#currentActiveTimeSheet").length) {
        let startTS = $("#currentActiveTimeSheet").attr("start");
        let nowTS = new Date();
        nowTS.setSeconds(0);
        nowTS.setMilliseconds(0);
        let diff = (nowTS.getTime() / 1000) - startTS;
        let minus = (diff < 0) ? "- " : "";

        $("#currentActiveTimeSheet").text(minus + timeToString(Math.abs(diff)));
    }

    if ($("[data-since]").length) {
        let nowTS = new Date();
        nowTS.setSeconds(0);
        nowTS.setMilliseconds(0);
        $("[data-since]").each( function () {
            let startTS = new Date($(this).attr("data-since"));
            startTS.setSeconds(0);
            startTS.setMilliseconds(0);
            let diff = (nowTS.getTime() - startTS.getTime()) / 1000;
            let minus = (diff < 0) ? "- " : "";

            $(this).text(minus + timeToString(Math.abs(diff)));
        });
    }
}

$( document ).ready(function() {
    if ($("#currentActiveTimeSheet").length || $("[data-since]").length) {
        setDuration();
        setInterval(function () {
            setDuration();
        }, 5000);
    }

    var animateIcon = function() {
        $(".animate-icon").animate({
                opacity: 0.7
            }, 1000, 'linear', function() {
                $(".animate-icon").fadeTo(1000, 1);
                animateIcon();
            }
        );
    }
    animateIcon();

    $("#flash-message").fadeTo(3000, 500).slideUp(500, function(){
        $("#flash-message").slideUp(500);
    });

    $("#toast-message").fadeTo(3000, 500).fadeOut(500, function(){
        $("#toast-message").fadeOut(500);
    });

});
