function init()
{
    displayRunsFromLocalStorage();
    const registrationSelector = document.querySelector('#registration [type="submit"]');

    if (typeof(registrationSelector) != 'undefined' && registrationSelector != null) {
        registrationSelector.addEventListener('click', onFormSubmit);
        document.getElementById("registration").onsubmit = onFormSubmit;

        const durationElement = document.querySelector('form input.duration');
        durationElement.addEventListener("change", (event) => {
            event.preventDefault();
            let sd = durationElement.value;
            let seconds = parseDuration(sd);

            if (sd !== '' && seconds === 0) {
                durationElement.style.color = "red";
                durationElement.focus();
            } else {
                document.getElementById("duration").value = seconds;
                durationElement.value = toDurationString(seconds);
                durationElement.style.color = "green";
            }
        });
    }
}

function onFormSubmit()
{
    addRunToLocalStorage();
}

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

    let hrx = new RegExp(/([0-9]{1,2})[ ]?h/);
    let mrx = new RegExp(/([0-9]{1,2})[ ]?m/);
    let srx = new RegExp(/([0-9]{1,2})[ ]?s/);
    let rem = new RegExp(/[0-9]{1,2}[ ]?(?:h|m|s)[ ]*([0-9]{1,2})\W*$/);

    let hours = 0;
    let minutes = 0;
    let seconds = 0;
    let found = {
        hours: false,
        minutes: false,
        seconds: false
    };

    if (hrx.test(sDuration)) {
        hours = hrx.exec(sDuration)[1];
        found.hours = true;
    }

    if (mrx.test(sDuration)) {
        minutes = mrx.exec(sDuration)[1];
        found.minutes = true;
    }

    if (srx.test(sDuration)) {
        seconds = srx.exec(sDuration)[1];
        found.seconds = true;
    }

    if (rem.test(sDuration) && !found.seconds) {
        if (found.minutes) {
            seconds = rem.exec(sDuration)[1];
        } else if (found.hours) {
            minutes = rem.exec(sDuration)[1];
        }
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

function displayRunsFromLocalStorage()
{
    if (localStorage.getItem("runs")) {
        const existingRuns = JSON.parse(localStorage.getItem("runs"));
        let runsSelect = document.getElementById("existingRuns");

        if (runsSelect !== null && existingRuns !== undefined && existingRuns !== []) {
            existingRuns.forEach(function (existingRun, index) {
                let runOption = document.createElement("option");

                // Ajoute un nœud texte à la cellule
                runOption.value = index;
                runOption.text = existingRun.gameName + ' - ' + existingRun.gameCategory + ' - ' + existingRun.estimatedTime;
                runOption.dataset.runnerName = existingRun.runnerName;
                runOption.dataset.gameName = existingRun.gameName;
                runOption.dataset.gameCategory = existingRun.gameCategory;
                runOption.dataset.estimatedTime = existingRun.estimatedTime;
                runsSelect.add(runOption);
            });
        }
    }
}

function addRunToLocalStorage()
{
    const existingRuns = localStorage.getItem("runs") ? JSON.parse(localStorage.getItem("runs"))  : [];
    const submittedRunnerName = document.getElementById("runnerName").value;
    const submittedGameName = document.getElementById("gameName").value;
    const submittedGameCategory = document.getElementById("gameCategory").value;
    const submittedEstimatedTime = document.getElementById("estimatedTime").value;
    let newRun = true;

    existingRuns.forEach(function (existingRun) {
        if (
            existingRun.gameName === submittedGameName
            && existingRun.gameCategory === submittedGameCategory
        ) {
            // Updating estimated time (and runner name, in case of) to an already-saved run
            existingRun.runnerName = submittedRunnerName
            existingRun.estimatedTime = submittedEstimatedTime;
            newRun = false;
        }
    });

    if (newRun) {
        let run = {
            runnerName: submittedRunnerName,
            gameName: submittedGameName,
            gameCategory: submittedGameCategory,
            estimatedTime: submittedEstimatedTime,
        };
        existingRuns.push(run);
    }

    window.localStorage.setItem("runs", JSON.stringify(existingRuns));
}

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

function populateForm()
{
    const existingRunsSelect = document.getElementById("existingRuns");
    let runnerName = '';
    let gameName = '';
    let gameCategory = '';
    let estimatedTime = '';

    if (existingRunsSelect.selectedIndex > 0) {
        runnerName = existingRunsSelect.options[existingRunsSelect.selectedIndex].getAttribute('data-runner-name');
        gameName = existingRunsSelect.options[existingRunsSelect.selectedIndex].getAttribute('data-game-name');
        gameCategory = existingRunsSelect.options[existingRunsSelect.selectedIndex].getAttribute('data-game-category');
        estimatedTime = existingRunsSelect.options[existingRunsSelect.selectedIndex].getAttribute('data-estimated-time');
    }

    document.getElementById("runnerName").setAttribute('value', runnerName);
    document.getElementById("gameName").setAttribute('value', gameName);
    document.getElementById("gameCategory").setAttribute('value', gameCategory);
    document.getElementById("estimatedTime").setAttribute('value', estimatedTime);
    const event = new Event('change', {
        'view': window,
        'bubbles': true,
        'cancelable': true
    });
    document.getElementById("estimatedTime").dispatchEvent(event);
}

function deleteJsonItem(input, key)
{
    delete input[key];
    return input.filter(function (x) {
        return x !== null
    });
}

function deleteRunFromLocalStorage()
{
    const existingRunsSelect = document.getElementById("existingRuns");
    if (existingRunsSelect.selectedIndex !== 0) {
        let confirm = window.confirm("Souhaitez-vous supprimer la run sélectionnée ?");
        if (confirm === true) {
            document.getElementById("existingRuns").remove(existingRunsSelect.selectedIndex);

            localStorage.setItem(
                "runs",
                JSON.stringify(deleteJsonItem(JSON.parse(localStorage.getItem("runs")), existingRunsSelect.selectedIndex))
            );
            document.location.reload();
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    init();
});
