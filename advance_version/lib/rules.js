const SGT_OFFSET = '+08:00';
const BOOKING_WINDOW_DAYS = 2;
const SLOT_MINUTES = 20;
const OPEN_START = '09:00';
const OPEN_END = '17:40';
const CLOSED_DAYS = [7];

const doctors = {
  'Dr Bryan': { label: 'Dr Bryan (Mon-Fri)', days: [1, 2, 3, 4, 5] },
  'Dr Javier': { label: 'Dr Javier (Tue-Sat)', days: [2, 3, 4, 5, 6] },
};

const doctorDays = {};
for (const [name, doctor] of Object.entries(doctors)) {
  doctorDays[name] = doctor.days;
}

const dayNames = {
  1: 'Mondays',
  2: 'Tuesdays',
  3: 'Wednesdays',
  4: 'Thursdays',
  5: 'Fridays',
  6: 'Saturdays',
  7: 'Sundays',
};

function pad(value) {
  return String(value).padStart(2, '0');
}

function todaySGT() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Singapore',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());

  const value = (type) => parts.find((part) => part.type === type).value;
  return `${value('year')}-${value('month')}-${value('day')}`;
}

function addDays(date, days) {
  const [year, month, day] = date.split('-').map(Number);
  const result = new Date(Date.UTC(year, month - 1, day + days));
  return `${result.getUTCFullYear()}-${pad(result.getUTCMonth() + 1)}-${pad(result.getUTCDate())}`;
}

function maxDateSGT() {
  return addDays(todaySGT(), BOOKING_WINDOW_DAYS);
}

function weekday(date) {
  const [year, month, day] = date.split('-').map(Number);
  const value = new Date(Date.UTC(year, month - 1, day)).getUTCDay();
  return value === 0 ? 7 : value;
}

function valid_date(date) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
    return false;
  }

  const [year, month, day] = date.split('-').map(Number);
  const parsed = new Date(Date.UTC(year, month - 1, day));

  return parsed.getUTCFullYear() === year
    && parsed.getUTCMonth() === month - 1
    && parsed.getUTCDate() === day;
}

function slot_times() {
  const start = Number(OPEN_START.slice(0, 2)) * 60 + Number(OPEN_START.slice(3, 5));
  const end = Number(OPEN_END.slice(0, 2)) * 60 + Number(OPEN_END.slice(3, 5));

  const times = [];
  for (let minutes = start; minutes <= end; minutes += SLOT_MINUTES) {
    times.push(`${pad(Math.floor(minutes / 60))}:${pad(minutes % 60)}`);
  }
  return times;
}

function time_label(hm) {
  const hour = Number(hm.slice(0, 2));
  const suffix = hour < 12 ? 'AM' : 'PM';
  const hour12 = hour % 12 === 0 ? 12 : hour % 12;
  return `${hour12}:${hm.slice(3, 5)} ${suffix}`;
}

function doctor_days_text(doctor) {
  const days = doctorDays[doctor];
  if (!days) {
    return '';
  }
  return days.map((day) => dayNames[day]).join(', ');
}

function schedule_error(doctor, date) {
  if (!Object.prototype.hasOwnProperty.call(doctorDays, doctor)) {
    return 'Please choose a doctor.';
  }
  if (!valid_date(date)) {
    return 'Please choose a valid date.';
  }

  const today = todaySGT();
  const max = maxDateSGT();

  if (date < today || date > max) {
    return 'Date must be within the next 2 days.';
  }

  const day = weekday(date);
  if (CLOSED_DAYS.includes(day)) {
    return `The clinic is closed on ${dayNames[day]}.`;
  }
  if (!doctorDays[doctor].includes(day)) {
    return `${doctor} is not available on ${dayNames[day]}.`;
  }

  return '';
}

function slot_date(date, time) {
  return new Date(`${date}T${time}:00${SGT_OFFSET}`);
}

function validate_slot(doctor, start) {
  const date = start.slice(0, 10);
  const time = start.slice(11, 16);

  const error = schedule_error(doctor, date);
  if (error !== '') {
    return error;
  }

  if (!slot_times().includes(time)) {
    return 'Please choose a valid appointment time.';
  }

  if (slot_date(date, time).getTime() < Date.now()) {
    return 'That time has already passed. Please choose a later time.';
  }

  return '';
}

async function taken_times(db, doctor, date, excludeId = 0) {
  let sql = "SELECT TIME_FORMAT(slot_start, '%H:%i') AS slot_time"
    + " FROM appointments"
    + " WHERE doctor = ? AND DATE(slot_start) = ? AND status = 'booked'";
  const params = [doctor, date];

  if (excludeId > 0) {
    sql += ' AND id <> ?';
    params.push(excludeId);
  }

  const [rows] = await db.execute(sql, params);
  return rows.map((row) => row.slot_time);
}

async function available_times(db, doctor, date, excludeId = 0) {
  if (schedule_error(doctor, date) !== '') {
    return [];
  }

  const taken = await taken_times(db, doctor, date, excludeId);
  const now = Date.now();
  const available = [];

  for (const time of slot_times()) {
    if (taken.includes(time)) {
      continue;
    }
    if (slot_date(date, time).getTime() <= now) {
      continue;
    }
    available.push(time);
  }

  return available;
}

function email_error(email) {
  if (email === '') {
    return 'Please enter your email.';
  }
  if (/\s/.test(email)) {
    return 'Email cannot contain spaces.';
  }

  const at = email.indexOf('@');
  if (at === -1) {
    return 'Email must contain an @ symbol.';
  }
  if (email.indexOf('@', at + 1) !== -1) {
    return 'Email can only contain one @ symbol.';
  }

  const local = email.slice(0, at);
  const domain = email.slice(at + 1);

  if (local === '') {
    return 'Enter the part before the @ symbol.';
  }
  if (domain === '') {
    return 'Enter the part after the @ symbol.';
  }
  if (!/^[a-zA-Z0-9._%+-]+$/.test(local)) {
    return 'Use only letters, numbers, dots, and _ % + - before the @ symbol.';
  }
  if (!/^[a-zA-Z0-9.-]+$/.test(domain)) {
    return 'Check the domain after the @ symbol.';
  }

  if (domain.toLowerCase() !== 'localhost') {
    if (!domain.includes('.')) {
      return 'Add a domain after the @ symbol (e.g. example.com).';
    }
    if (!/\.[a-zA-Z]{2,}$/.test(domain)) {
      return 'Check the ending of the email domain.';
    }
  }

  return '';
}

function validate_phone(phone) {
  const value = phone.trim();
  if (value === '') {
    return '';
  }

  const digits = value.replace(/[\s\-().]/g, '');

  if (!/^(?:\+?65)?[89]\d{7}$/.test(digits)) {
    return 'Please enter a valid Singapore phone number.';
  }

  return '';
}

module.exports = {
  doctors,
  openStart: OPEN_START,
  openEnd: OPEN_END,
  todaySGT,
  maxDateSGT,
  valid_date,
  slot_times,
  time_label,
  doctor_days_text,
  schedule_error,
  validate_slot,
  available_times,
  email_error,
  validate_phone,
};
