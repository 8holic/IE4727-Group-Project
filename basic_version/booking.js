// Doctor schedules (working days, opening hours) come from api/doctors.json,
// the same file api/db.php reads. Edit that one file to change availability.

const timeSelect = document.getElementById("timeSlot");
const dateInput = document.getElementById("date");
const doctorSelect = document.getElementById("doctor");

let doctorDays = {};       // { "Dr Bryan": [1,2,3,4,5], ... }
let closedDays = [];       // ISO weekdays the clinic is shut (7 = Sunday)
let openStart = "09:00";
let openEnd = "17:40";
let takenSlots = [];       // times already booked for the chosen doctor + date

const formFields = ["name", "email", "phone", "doctor", "date", "timeSlot"];

function sgDate(offsetDays) {
    const d = new Date(Date.now() + offsetDays * 86400000);
    return d.toLocaleDateString("en-CA", { timeZone: "Asia/Singapore" });
}

function toLocalDate(d) {
    return d.getFullYear() + "-" +
           String(d.getMonth() + 1).padStart(2, "0") + "-" +
           String(d.getDate()).padStart(2, "0");
}

// ISO weekday for a yyyy-mm-dd value: 1 = Mon ... 7 = Sun
function weekdayOf(dateStr) {
    const day = new Date(dateStr + "T00:00:00").getDay();   // 0 = Sun
    return day === 0 ? 7 : day;
}

const dayNames = {
    1: "Mondays", 2: "Tuesdays", 3: "Wednesdays", 4: "Thursdays",
    5: "Fridays", 6: "Saturdays", 7: "Sundays"
};

function scheduleError() {
    const doctor = doctorSelect.value;
    const date = dateInput.value;
    if (!doctor || !date || !doctorDays[doctor]) return "";

    const day = weekdayOf(date);
    if (closedDays.includes(day)) return "The clinic is closed on " + dayNames[day] + ".";
    if (!doctorDays[doctor].includes(day)) return doctor + " is not available on " + dayNames[day] + ".";
    return "";
}

function isPastTime(value) {
    if (!value || dateInput.value !== sgDate(0)) return false;

    const now = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Singapore" }));
    const nowHM = String(now.getHours()).padStart(2, "0") + ":" + String(now.getMinutes()).padStart(2, "0");
    return value <= nowHM;
}

function emailError(value) {
    if (!value) return "Please enter your email.";
    if (/\s/.test(value)) return "Email cannot contain spaces.";

    const at = value.indexOf("@");
    if (at === -1) return "Email must contain an @ symbol.";
    if (value.indexOf("@", at + 1) !== -1) return "Email can only contain one @ symbol.";

    const local = value.slice(0, at);
    const domain = value.slice(at + 1);

    if (!local) return "Please enter the part before the @ symbol.";
    if (!domain) return "Please enter the part after the @ symbol.";
    if (!domain.includes(".")) return "Please check your email ending is correct";

    const tld = domain.slice(domain.lastIndexOf(".") + 1);
    if (!/^[a-zA-Z]{2,}$/.test(tld)) return "Please check your email ending is correct";
    if (!/^[a-zA-Z0-9.-]+$/.test(domain)) return "Please check the part after the @ symbol.";
    if (!/^[a-zA-Z0-9._%+-]+$/.test(local)) return "Please check the part before the @ symbol.";

    return "";
}

const validators = {
    name: function (value) {
        return value ? "" : "Please enter your name.";
    },
    email: emailError,
    phone: function (value) {
        return !value || /^[+()\d\s-]{6,}$/.test(value) ? "" : "Please enter a valid phone number.";
    },
    doctor: function (value) {
        return value ? "" : "Please choose a doctor.";
    },
    date: function (value) {
        return value ? "" : "Please choose a date.";
    },
    timeSlot: function (value) {
        const schedule = scheduleError();
        if (schedule) return schedule;
        if (!value) return "Please choose a time.";
        if (isPastTime(value)) return "That time has already passed. Please choose a later time.";
        if (takenSlots.includes(value)) return "That time slot is already booked. Please choose another time.";
        return "";
    }
};

function showError(id, message) {
    document.getElementById("err-" + id).textContent = message;
}

function checkField(id) {
    const message = validators[id](document.getElementById(id).value.trim());
    showError(id, message);
    return !message;
}

function validate() {
    return formFields.map(checkField).every(Boolean);
}

function markTaken() {
    const date = dateInput.value;
    const doctor = doctorSelect.value;
    const options = timeSelect.options;
    const closed = scheduleError() !== "";

    if (closed) {
        timeSelect.value = "";
    }

    if (!date || !doctor) {
        takenSlots = [];
        for (let i = 0; i < options.length; i++) {
            options[i].disabled = false;
        }
        return;
    }

    fetch(`api/taken.php?date=${date}&doctor=${encodeURIComponent(doctor)}`)
        .then(r => r.text())
        .then(text => {
            const taken = text ? text.split(",") : [];
            takenSlots = taken;

            for (let i = 0; i < options.length; i++) {
                options[i].disabled = closed || taken.includes(options[i].value);
            }

            const now = new Date(
                new Date().toLocaleString("en-US", { timeZone: "Asia/Singapore" })
            );
            const todayStr = sgDate(0);

            if (!closed && date === todayStr) {
                const nowHM =
                    String(now.getHours()).padStart(2, "0") + ":" +
                    String(now.getMinutes()).padStart(2, "0");

                for (let i = 0; i < options.length; i++) {
                    if (options[i].value && options[i].value <= nowHM) {
                        options[i].disabled = true;
                    }
                }
            }
        });
}

async function init() {
    const config = await (await fetch("api/doctors.json")).json();

    // Build the doctor dropdown and the day map from the shared config.
    config.doctors.forEach(function (doc) {
        doctorDays[doc.name] = doc.days;

        const opt = document.createElement("option");
        opt.value = doc.name;
        opt.textContent = doc.label || doc.name;
        doctorSelect.appendChild(opt);
    });

    closedDays = config.closed;
    openStart = config.hours.start;
    openEnd = config.hours.end;

    // Booking window: today up to 2 days ahead.
    const today = new Date();
    const maxDay = new Date();
    maxDay.setDate(today.getDate() + 2);
    dateInput.min = toLocalDate(today);
    dateInput.max = toLocalDate(maxDay);

    // 20-minute slots from opening to closing time.
    const startHour = Number(openStart.slice(0, 2));
    const endHour = Number(openEnd.slice(0, 2));
    const endMin = Number(openEnd.slice(3, 5));

    for (let hour = startHour; hour <= endHour; hour++) {
        for (let min = 0; min < 60; min += 20) {
            if (hour === endHour && min > endMin) break;

            const value = String(hour).padStart(2, "0") + ":" + String(min).padStart(2, "0");
            const opt = document.createElement("option");
            opt.value = value;
            opt.textContent = value;
            timeSelect.appendChild(opt);
        }
    }

    // Check a field when the user clicks off it, clear the error while they edit.
    formFields.forEach(function (id) {
        const field = document.getElementById(id);
        field.addEventListener("blur", function () {
            checkField(id);
        });
        field.addEventListener("input", function () {
            showError(id, "");
        });
    });

    dateInput.addEventListener("change", function () {
        checkField("date");
        checkField("timeSlot");
        markTaken();
    });
    doctorSelect.addEventListener("change", function () {
        checkField("doctor");
        checkField("timeSlot");
        markTaken();
    });

    // On submit, validate, then combine date + time into one value and POST it.
    document.getElementById("bookingForm").addEventListener("submit", async function (e) {
        e.preventDefault();

        const msg = document.getElementById("msg");

        if (!validate()) {
            msg.textContent = "";
            msg.className = "form-msg";
            return;
        }

        const data = new FormData(this);

        // PHP wants "2026-10-10 09:00:00", but we have date and time separately
        const start = data.get("date") + " " + data.get("start") + ":00";
        data.set("start", start);
        data.delete("date");

        const response = await fetch("api/db.php", {
            method: "POST",
            body: data
        });
        const result = await response.text();

        msg.textContent = result;
        msg.className = "form-msg " + (response.ok ? "success" : "error");
        markTaken();
    });

    markTaken();
}

init().catch(function () {
    const msg = document.getElementById("msg");
    msg.textContent = "Could not load doctor schedule.";
    msg.className = "form-msg error";
});
