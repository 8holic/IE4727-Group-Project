const nodemailer = require('nodemailer');
const config = require('../config');

const transport = nodemailer.createTransport({
  host: config.mail.host,
  port: config.mail.port,
  secure: false,
  ignoreTLS: true,
  connectionTimeout: 5000,
  greetingTimeout: 5000,
  socketTimeout: 10000,
});

function isLocalDomain(email) {
  const domain = email.split('@').pop().toLowerCase();
  return config.mail.allowedDomains.includes(domain);
}

async function sendMail(to, subject, body) {
  if (!isLocalDomain(to)) {
    return false;
  }

  try {
    await transport.sendMail({
      from: config.mail.from,
      to,
      subject,
      text: body,
    });
    return true;
  } catch (error) {
    console.error('Mail delivery failed:', error.message);
    return false;
  }
}

module.exports = { sendMail };
