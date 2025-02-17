
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
}

$( document ).ready(function() {
    if ($("#currentActiveTimeSheet").length) {
        setDuration();
        setInterval(function () {
            setDuration();
        }, 5000);
    }

    $("#flash-message").fadeTo(3000, 500).slideUp(500, function(){
        $("#flash-message").slideUp(500);
    });
});
