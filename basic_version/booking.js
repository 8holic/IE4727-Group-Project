// 1. Fill the time dropdown with 20-minute slots (09:00 – 17:00)
const timeSelect = document.getElementById("timeSlot");

const dateInput = document.getElementById("date");
function sgDate(offsetDays) {
    const d = new Date(Date.now() + offsetDays * 86400000);
    return d.toLocaleDateString("en-CA", { timeZone: "Asia/Singapore" });
}

function toLocalDate(d) {
    return d.getFullYear() + "-" +
           String(d.getMonth() + 1).padStart(2, "0") + "-" +
           String(d.getDate()).padStart(2, "0");
}

const today = new Date();
const nextWeek = new Date();
nextWeek.setDate(today.getDate() + 7);

dateInput.min = toLocalDate(today);
dateInput.max = toLocalDate(nextWeek);

for (let hour = 9; hour < 17; hour++) {
    for (let min = 0; min < 60; min += 20) {
        const hh = String(hour).padStart(2, "0");
        const mm = String(min).padStart(2, "0");
        const value = hh + ":" + mm;

        const opt = document.createElement("option");
        opt.value = value;
        opt.textContent = value;
        timeSelect.appendChild(opt);
    }
}

// 2. On submit, combine date + time into one value and POST it
document.getElementById("bookingForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const data = new FormData(this);

    // PHP wants "2026-10-10 09:00:00", but we have date and time separately
    const start = data.get("date") + " " + data.get("start") + ":00";
    data.set("start", start);
    data.delete("date");

    fetch("api/db.php", {
        method: "POST",
        body: data
    })
    .then(response => response.text())
    .then(result => {
        document.getElementById("msg").innerText = result;
        markTaken();
    });

});

function markTaken() {
    const date = document.getElementById("date").value;
    const location = document.getElementById("location").value;
    if (!date) return;

    fetch(`api/taken.php?date=${date}&location=${encodeURIComponent(location)}`)
        .then(r => r.text())
        .then(text => {
            const taken = text ? text.split(",") : [];
            const options = document.getElementById("timeSlot").options;

            for (let i = 0; i < options.length; i++) {
                options[i].disabled = taken.includes(options[i].value);
            }

            const now = new Date(
                new Date().toLocaleString("en-US", { timeZone: "Asia/Singapore" })
            );
            const todayStr = sgDate(0);

            if (date === todayStr) {
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

document.getElementById("date").addEventListener("change", markTaken);
document.getElementById("location").addEventListener("change", markTaken);
markTaken();