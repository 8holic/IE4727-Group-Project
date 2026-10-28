const path = require('path');
const express = require('express');
const session = require('express-session');

const config = require('./config');
const rules = require('./lib/rules');
const { csrfToken, csrfOk } = require('./lib/auth');
const { dateLabel, timeLabel12, dateTimeLabel, isFuture } = require('./lib/format');
const authRoutes = require('./routes/auth');
const patientRoutes = require('./routes/patient');
const staffRoutes = require('./routes/staff');

const app = express();
const PUBLIC_DIR = path.join(__dirname, 'public');

app.disable('x-powered-by');
app.set('views', path.join(__dirname, 'views'));
app.set('view engine', 'ejs');

app.locals.dateLabel = dateLabel;
app.locals.timeLabel12 = timeLabel12;
app.locals.dateTimeLabel = dateTimeLabel;
app.locals.isFuture = isFuture;
app.locals.timeLabel = rules.time_label;

app.use(express.static(PUBLIC_DIR, { extensions: ['html'] }));
app.use(express.urlencoded({ extended: false }));
app.use(session({
  name: 'jnb.sid',
  secret: config.sessionSecret,
  resave: false,
  saveUninitialized: false,
  cookie: {
    httpOnly: true,
    sameSite: 'lax',
    secure: false,
  },
}));

app.use((req, res, next) => {
  res.locals.csrfToken = csrfToken(req);
  res.locals.currentUser = req.session.user || null;
  res.locals.currentDoctor = req.session.doctor || null;
  next();
});

app.use((req, res, next) => {
  if (req.method === 'POST' && !csrfOk(req)) {
    return res.status(403).send('Your session expired. Please reload the page and try again.');
  }
  return next();
});

const routeAliases = {
  '/login.php': '/login',
  '/register.php': '/register',
  '/logout.php': '/logout',
  '/booking.php': '/booking',
  '/my_appointments.php': '/my-appointments',
  '/manage.php': '/manage',
  '/cancel.php': '/cancel',
  '/confirmation.php': '/confirmation',
  '/doctor.php': '/staff',
};

for (const [from, to] of Object.entries(routeAliases)) {
  app.get(from, (req, res) => {
    const queryIndex = req.originalUrl.indexOf('?');
    const query = queryIndex === -1 ? '' : req.originalUrl.slice(queryIndex);
    return res.redirect(to + query);
  });
}

app.use(authRoutes);
app.use(patientRoutes);
app.use(staffRoutes);

app.use((req, res) => {
  res.status(404).send('Page not found.');
});

app.listen(config.port, () => {
  console.log(`J&B Family Clinic running at http://localhost:${config.port}`);
});
