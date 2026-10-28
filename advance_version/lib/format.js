const TIME_ZONE = 'Asia/Singapore';

function parseSlot(value) {
  if (value instanceof Date) {
    return value;
  }
  return new Date(`${String(value).replace(' ', 'T')}+08:00`);
}

function dateLabel(value) {
  return new Intl.DateTimeFormat('en-GB', {
    timeZone: TIME_ZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  }).format(parseSlot(value));
}

function timeLabel12(value) {
  return new Intl.DateTimeFormat('en-US', {
    timeZone: TIME_ZONE,
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  }).format(parseSlot(value));
}

function dateTimeLabel(value) {
  return `${dateLabel(value)}, ${timeLabel12(value)}`;
}

function isFuture(value) {
  return parseSlot(value).getTime() >= Date.now();
}

module.exports = {
  parseSlot,
  dateLabel,
  timeLabel12,
  dateTimeLabel,
  isFuture,
};
