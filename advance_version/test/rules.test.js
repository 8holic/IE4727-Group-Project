const test = require('node:test');
const assert = require('node:assert/strict');
const rules = require('../lib/rules');

test('slot grid runs 09:00 to 17:40 every 20 minutes', () => {
  const slots = rules.slot_times();
  assert.equal(slots.length, 27);
  assert.equal(slots[0], '09:00');
  assert.equal(slots.at(-1), '17:40');
  assert.ok(slots.includes('12:00'));
});

test('time labels use 12-hour format', () => {
  assert.equal(rules.time_label('00:00'), '12:00 AM');
  assert.equal(rules.time_label('09:00'), '9:00 AM');
  assert.equal(rules.time_label('12:00'), '12:00 PM');
  assert.equal(rules.time_label('17:40'), '5:40 PM');
});

test('valid_date rejects impossible calendar dates', () => {
  assert.equal(rules.valid_date('2024-02-29'), true);
  assert.equal(rules.valid_date('2026-02-30'), false);
  assert.equal(rules.valid_date('2026-1-01'), false);
  assert.equal(rules.valid_date('not-a-date'), false);
});

test('doctor days text matches the schedule', () => {
  assert.equal(
    rules.doctor_days_text('Dr Bryan'),
    'Mondays, Tuesdays, Wednesdays, Thursdays, Fridays'
  );
  assert.equal(
    rules.doctor_days_text('Dr Javier'),
    'Tuesdays, Wednesdays, Thursdays, Fridays, Saturdays'
  );
  assert.equal(rules.doctor_days_text('Nobody'), '');
});

test('schedule_error rejects unknown doctors and bad dates', () => {
  assert.equal(rules.schedule_error('Nobody', rules.todaySGT()), 'Please choose a doctor.');
  assert.equal(rules.schedule_error('Dr Bryan', 'not-a-date'), 'Please choose a valid date.');
  assert.equal(
    rules.schedule_error('Dr Bryan', '2020-01-01'),
    'Date must be within the next 2 days.'
  );
});

test('email_error catches the common mistakes', () => {
  assert.equal(rules.email_error(''), 'Please enter your email.');
  assert.equal(rules.email_error('a b@example.com'), 'Email cannot contain spaces.');
  assert.equal(rules.email_error('aexample.com'), 'Email must contain an @ symbol.');
  assert.equal(rules.email_error('a@@example.com'), 'Email can only contain one @ symbol.');
  assert.equal(rules.email_error('@example.com'), 'Enter the part before the @ symbol.');
  assert.equal(rules.email_error('a@'), 'Enter the part after the @ symbol.');
  assert.equal(
    rules.email_error('a@example'),
    'Add a domain after the @ symbol (e.g. example.com).'
  );
  assert.equal(rules.email_error('a@example.c'), 'Check the ending of the email domain.');
  assert.equal(rules.email_error('name@example.com'), '');
  assert.equal(rules.email_error('name@localhost'), '');
});

test('validate_phone accepts Singapore numbers only', () => {
  assert.equal(rules.validate_phone(''), '');
  assert.equal(rules.validate_phone('91234567'), '');
  assert.equal(rules.validate_phone('+65 9123 4567'), '');
  assert.equal(
    rules.validate_phone('1234'),
    'Please enter a valid Singapore phone number.'
  );
  assert.equal(
    rules.validate_phone('71234567'),
    'Please enter a valid Singapore phone number.'
  );
});
