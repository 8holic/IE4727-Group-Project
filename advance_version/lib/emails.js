const config = require('../config');
const { sendMail } = require('./mail');
const { dateLabel, dateTimeLabel } = require('./format');

async function sendWelcomeEmail({ name, email }) {
  const body = `Hi ${name},\n\n`
    + 'Your J&B Family Clinic patient account has been created.\n\n'
    + `Email: ${email}\n`
    + `Sign in here: ${config.siteBase}/login\n\n`
    + 'You can now book and manage appointments.\n\n'
    + 'J&B Family Clinic\n';

  return sendMail(email, 'Welcome to J&B Family Clinic', body);
}

async function sendBookingEmail({ name, email, doctor, start, code }) {
  const body = `Hi ${name},\n\n`
    + 'Your appointment at J&B Family Clinic is confirmed.\n\n'
    + `Doctor: ${doctor}\n`
    + `Date and time: ${dateTimeLabel(start)} (SGT)\n`
    + `Booking code: ${code}\n\n`
    + 'To change the date of your appointment, log in and open:\n'
    + `${config.siteBase}/my-appointments\n\n`
    + 'See you soon,\n'
    + 'J&B Family Clinic\n';

  return sendMail(email, `Booking confirmed - ${code}`, body);
}

async function sendRescheduleEmail({ name, email, doctor, start, code }) {
  let body = `Hi ${name},\n\n`
    + 'Your appointment at J&B Family Clinic has been rescheduled.\n\n'
    + `Doctor: ${doctor}\n`
    + `New date and time: ${dateTimeLabel(start)} (SGT)\n\n`;

  if (code) {
    body += `Your booking code: ${code}\n\n`;
  }

  body += 'J&B Family Clinic\n';

  return sendMail(email, `Appointment rescheduled - ${dateLabel(start)}`, body);
}

async function sendCancelEmail({ name, email, doctor, start, reason }) {
  let body = `Hi ${name},\n\n`
    + 'Your appointment at J&B Family Clinic has been cancelled.\n\n'
    + `Doctor: ${doctor}\n`
    + `Date and time: ${dateTimeLabel(start)} (SGT)\n`;

  if (reason) {
    body += `Reason: ${reason}\n`;
  } else {
    body += `\nIf this was a mistake, you can book again here:\n${config.siteBase}/booking\n`;
  }

  body += '\nJ&B Family Clinic\n';

  return sendMail(email, `Appointment cancelled - ${dateLabel(start)}`, body);
}

module.exports = {
  sendWelcomeEmail,
  sendBookingEmail,
  sendRescheduleEmail,
  sendCancelEmail,
};
