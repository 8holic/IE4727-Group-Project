const crypto = require('crypto');
const bcrypt = require('bcrypt');

const ALLOWED_NEXT = ['/', '/booking', '/my-appointments'];

function isLoggedIn(req) {
  return Boolean(req.session && req.session.user && req.session.user.role);
}

function isPatient(req) {
  return isLoggedIn(req) && req.session.user.role === 'patient';
}

function isDoctor(req) {
  return Boolean(req.session && req.session.doctor && req.session.doctor.id);
}

function allowedNext(value) {
  return ALLOWED_NEXT.includes(value) ? value : '/';
}

function requirePatient(nextPath) {
  return (req, res, next) => {
    if (!isPatient(req)) {
      const target = allowedNext(nextPath || req.originalUrl);
      return res.redirect(`/login?next=${encodeURIComponent(target)}&required=1`);
    }
    return next();
  };
}

function csrfToken(req) {
  if (!req.session.csrf) {
    req.session.csrf = crypto.randomBytes(32).toString('hex');
  }
  return req.session.csrf;
}

function csrfOk(req) {
  const expected = req.session && req.session.csrf;
  const actual = typeof req.body.csrf === 'string' ? req.body.csrf : '';

  if (!expected || !actual || expected.length !== actual.length) {
    return false;
  }

  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(actual));
}

async function comparePassword(password, hash) {
  const normalized = hash.startsWith('$2y$') ? `$2b$${hash.slice(4)}` : hash;
  return bcrypt.compare(password, normalized);
}

async function hashPassword(password) {
  return bcrypt.hash(password, 10);
}

function loginPatient(req, patient, nextPath) {
  return new Promise((resolve, reject) => {
    req.session.regenerate((error) => {
      if (error) {
        reject(error);
        return;
      }

      req.session.user = {
        id: Number(patient.id),
        name: patient.name,
        email: patient.email,
        role: 'patient',
      };

      req.session.save((saveError) => {
        if (saveError) {
          reject(saveError);
          return;
        }
        resolve(nextPath);
      });
    });
  });
}

function loginDoctor(req, doctor) {
  return new Promise((resolve, reject) => {
    req.session.regenerate((error) => {
      if (error) {
        reject(error);
        return;
      }

      req.session.doctor = {
        id: Number(doctor.id),
        name: doctor.name,
      };

      req.session.save((saveError) => {
        if (saveError) {
          reject(saveError);
          return;
        }
        resolve();
      });
    });
  });
}

function destroySession(req, res, redirectTo) {
  req.session.destroy(() => {
    res.clearCookie('jnb.sid');
    res.redirect(redirectTo);
  });
}

function logoutUser(req, res) {
  destroySession(req, res, '/');
}

module.exports = {
  isLoggedIn,
  isPatient,
  isDoctor,
  allowedNext,
  requirePatient,
  csrfToken,
  csrfOk,
  comparePassword,
  hashPassword,
  loginPatient,
  loginDoctor,
  destroySession,
  logoutUser,
};
