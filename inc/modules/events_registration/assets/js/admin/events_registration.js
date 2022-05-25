$(document).ready(function () {
    function to_seconds(hh, mm, ss)
    {
        let h = parseInt(hh);
        let m = parseInt(mm);
        let s = parseInt(ss);
        if (isNaN(h)) {
            h = 0;
        }
        if (isNaN(m)) {
            m = 0;
        }
        if (isNaN(s)) {
            s = 0;
        }

        return h * 60 * 60 +
            m * 60 +
            s;
    }

    // expects 1d 11h 11m, or 1d 11h,
    // or 11h 11m, or 11h, or 11m, or 1d
    // returns a number of seconds.
    function parseDuration(sDuration)
    {
        if (sDuration == null || sDuration === '') {
            return 0;
        }
        let hrx = new RegExp(/([0-9][0-9]?)[ ]?h/);
        let mrx = new RegExp(/([0-9][0-9]?)[ ]?m/);
        let srx = new RegExp(/([0-9][0-9]?)[ ]?s/);

        let hours = 0;
        let minutes = 0;
        let seconds = 0;

        if (mrx.test(sDuration)) {
            minutes = mrx.exec(sDuration)[1];
        }
        if (hrx.test(sDuration)) {
            hours = hrx.exec(sDuration)[1];
        }
        if (srx.test(sDuration)) {
            seconds = srx.exec(sDuration)[1];
        }

        return to_seconds(hours, minutes, seconds);
    }

    /*
     * Outputs a duration string based on the number of seconds provided.
     * Rounded off to the nearest 1 minute.
     */
    function toDurationString(iDuration)
    {
        if (iDuration <= 0) {
            return '';
        }
        let h = Math.floor((iDuration / 3600) % 24);
        let m = Math.floor((iDuration / 60) % 60);
        let s = Math.floor(iDuration % 60);
        let result = ''
        if (h > 0) {
            result = result + h + "h ";
        }
        if (m > 0) {
            result = result + m + "m ";
        }
        if (s > 0) {
            result = result + s + "s ";
        }
        return result.substring(0, result.length - 1);
    }

    (function () {
        const selectElement = document.querySelector('form input.duration');
        selectElement.addEventListener("change", (event) => {
            event.preventDefault();
            let sd = selectElement.value;
            let seconds = parseDuration(sd);
            if (sd !== '' && seconds === 0) {
                selectElement.style.color = "red";
                selectElement.focus();
            } else {
                document.getElementById("duration").value = seconds;
                selectElement.value = toDurationString(seconds);
                selectElement.style.color = "green";
            }
        });
    }());
});

function displayOpponents()
{
    const checkBox = document.getElementById("race");
    let input = document.getElementById("raceOpponents").parentElement;

    if (checkBox.checked === true) {
        input.style.display = "block";
    } else {
        input.style.display = "none";
    }
}
