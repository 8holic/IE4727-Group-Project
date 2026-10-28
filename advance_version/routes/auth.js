const express = require('express');
const db = require('../lib/db');
const rules = require('../lib/rules');
const { sendWelcomeEmail } = require('../lib/emails');
const {
  isPatient,
  allowedNext,
  loginPatient,
  comparePassword,
  hashPassword,
  logoutUser,
} = require('../lib/auth');

const router = express.Router();

router.get('/register', (req, res) => {
  if (isPatient(req)) {
    return res.redirect(allowedNext(req.query.next));
  }

  return res.render('register', {
    title: 'Patient Registration',
    active: 'login',
    next: allowedNext(req.query.next),
    message: '',
    messageClass: '',
    form: {},
  });
});

router.post('/register', async (req, res) => {
  const name = String(req.body.name || '').trim();
  const email = String(req.body.email || '').trim();
  const phone = String(req.body.phone || '').trim();
  const password = typeof req.body.password === 'string' ? req.body.password : '';
  const confirm = typeof req.body.confirm_password === 'string' ? req.body.confirm_password : '';
  const next = allowedNext(req.body.next);

  const render = (message, messageClass) => res.render('register', {
    title: 'Patient Registration',
    active: 'login',
    next,
    message,
    messageClass,
    form: { name, email, phone },
  });

  if (name === '' || email === '' || phone === '' || password === '') {
    return render('Please complete all fields.', 'error');
  }

  const emailError = rules.email_error(email);
  if (emailError !== '') {
    return render(emailError, 'error');
  }

  if (rules.validate_phone(phone) !== '') {
    return render('Please enter a valid Singapore phone number (8 digits, starting with 8 or 9).', 'error');
  }

  if (password.length < 8) {
    return render('Password must be at least 8 characters.', 'error');
  }

  if (password !== confirm) {
    return render('Passwords do not match.', 'error');
  }

  try {
    const [existing] = await db.execute(
      'SELECT 1 FROM patients WHERE email = ? UNION SELECT 1 FROM doctors WHERE email = ? LIMIT 1',
      [email, email]
    );

    if (existing.length > 0) {
      return render('An account with this email already exists.', 'error');
    }

    const hash = await hashPassword(password);
    await db.execute(
      'INSERT INTO patients (name, email, phone, password_hash) VALUES (?, ?, ?, ?)',
      [name, email, phone, hash]
    );

    await sendWelcomeEmail({ name, email });

    return res.redirect(`/login?registered=1&next=${encodeURIComponent(next)}`);
  } catch (error) {
    console.error('Registration failed:', error.message);
    return render('Could not create your account. Please try again.', 'error');
  }
});

router.get('/login', (req, res) => {
  if (isPatient(req)) {
    return res.redirect(allowedNext(req.query.next));
  }

  const next = allowedNext(req.query.next);
  const required = req.query.required === '1';

  let message = '';
  let messageClass = '';

  if (req.query.registered) {
    message = 'Registration successful. Please sign in.';
    messageClass = 'success';
  } else if (required && next === '/booking') {
    message = 'Please sign in or create an account to book an appointment.';
    messageClass = 'info';
  } else if (required && next === '/my-appointments') {
    message = 'Please sign in to view or manage your appointments.';
    messageClass = 'info';
  }

  return res.render('login', {
    title: 'Patient Login',
    active: 'login',
    next,
    message,
    messageClass,
    email: '',
  });
});

router.post('/login', async (req, res) => {
  const email = String(req.body.email || '').trim();
  const password = typeof req.body.password === 'string' ? req.body.password : '';
  const next = allowedNext(req.body.next);

  const render = (message, messageClass) => res.render('login', {
    title: 'Patient Login',
    active: 'login',
    next,
    message,
    messageClass,
    email,
  });

  if (email === '' || password === '') {
    return render('Please enter your email and password.', 'error');
  }

  try {
    const [rows] = await db.execute(
      'SELECT id, name, email, password_hash FROM patients WHERE email = ?',
      [email]
    );
    const patient = rows[0];

    if (patient && await comparePassword(password, patient.password_hash)) {
      await loginPatient(req, patient, next);
      return res.redirect(next);
    }

    return render('Incorrect email or password.', 'error');
  } catch (error) {
    console.error('Login failed:', error.message);
    return render('Could not sign you in. Please try again.', 'error');
  }
});

router.get('/logout', (req, res) => {
  return logoutUser(req, res);
});

module.exports = router;
