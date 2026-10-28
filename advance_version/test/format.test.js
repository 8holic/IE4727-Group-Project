const test = require('node:test');
const assert = require('node:assert/strict');
const { dateLabel, timeLabel12, dateTimeLabel, isFuture } = require('../lib/format');

test('formats appointment times in Singapore time', () => {
  assert.equal(dateLabel('2026-10-28 09:00:00'), 'Wed, 28 Oct 2026');
  assert.equal(timeLabel12('2026-10-28 09:00:00'), '9:00 AM');
  assert.equal(dateTimeLabel('2026-10-28 17:40:00'), 'Wed, 28 Oct 2026, 5:40 PM');
});

test('isFuture compares appointment times against now', () => {
  assert.equal(isFuture('2999-01-01 00:00:00'), true);
  assert.equal(isFuture('2000-01-01 00:00:00'), false);
});
