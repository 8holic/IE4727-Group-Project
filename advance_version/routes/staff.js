const express = require('express');
const db = require('../lib/db');
const rules = require('../lib/rules');
const { dateTimeLabel } = require('../lib/format');
const {
  isDoctor,
  loginDoctor,
  comparePassword,
  destroySession,
} = require('../lib/auth');
const { sendCancelEmail, sendRescheduleEmail } = require('../lib/emails');

const router = express.Router();

function renderLogin(res, error) {
  return res.render('staff-login', {
    title: 'Employee Login',
    active: 'staff',
    error,
  });
}

async function renderDashboard(res, { notice = '', error = '' } = {}) {
  const [appointments] = await db.execute(
    'SELECT id, patient_name, patient_email, phone, doctor, slot_start, status, manage_token'
      + ' FROM appointments ORDER BY slot_start'
  );

  return res.render('staff-dashboard', {
    title: 'Appointments',
    active: 'staff',
    appointments,
    notice,
    error,
    minDate: rules.todaySGT(),
    maxDate: rules.maxDateSGT(),
    slotTimes: rules.slot_times(),
  });
}

router.get('/staff', async (req, res) => {
  if (req.query.logout === '1') {
    return destroySession(req, res, '/staff');
  }

  if (!isDoctor(req)) {
    return renderLogin(res, '');
  }

  const notice = req.session.flash || '';
  delete req.session.flash;

  try {
    return await renderDashboard(res, { notice });
  } catch (error) {
    console.error('Staff dashboard failed:', error.message);
    return res.status(500).send('Could not load appointments. Please try again.');
  }
});

router.post('/staff', async (req, res) => {
  const action = String(req.body.action || '');

  if (action === 'login') {
    const email = String(req.body.email || '').trim();
    const password = typeof req.body.password === 'string' ? req.body.password : '';

    try {
      const [rows] = await db.execute(
        'SELECT id, name, password_hash FROM doctors WHERE email = ?',
        [email]
      );
      const doctor = rows[0];

      if (doctor && await comparePassword(password, doctor.password_hash)) {
        await loginDoctor(req, doctor);
        return res.redirect('/staff');
      }

      return renderLogin(res, 'Invalid email or password.');
    } catch (error) {
      console.error('Staff login failed:', error.message);
      return renderLogin(res, 'Could not sign you in. Please try again.');
    }
  }

  if (!isDoctor(req)) {
    return res.redirect('/staff');
  }

  if (action === 'cancel') {
    const id = Number(req.body.id || 0);
    const reason = String(req.body.reason || '').trim();
    let error = '';

    if (id <= 0) {
      error = 'That appointment could not be found.';
    } else if (reason === '') {
      error = 'Please enter a reason for the cancellation.';
    } else if (reason.length > 500) {
      error = 'The reason must be 500 characters or fewer.';
    } else {
      try {
        const [rows] = await db.execute(
          "SELECT id, patient_name, patient_email, doctor, slot_start"
            + " FROM appointments WHERE id = ? AND status = 'booked'",
          [id]
        );
        const row = rows[0];

        if (!row) {
          error = 'That appointment is no longer booked.';
        } else {
          const [result] = await db.execute(
            "UPDATE appointments SET status = 'cancelled'"
              + " WHERE id = ? AND status = 'booked'",
            [id]
          );

          if (result.affectedRows === 1) {
            const sent = await sendCancelEmail({
              name: row.patient_name,
              email: row.patient_email,
              doctor: row.doctor,
              start: row.slot_start,
              reason,
            });

            req.session.flash = `Appointment for ${row.patient_name} cancelled.`
              + (sent ? ' The patient has been emailed.' : ' The cancellation email could not be sent.');

            return res.redirect('/staff');
          }

          error = 'That appointment is no longer booked.';
        }
      } catch (queryError) {
        console.error('Staff cancellation failed:', queryError.message);
        error = 'Could not cancel the appointment. Please try again.';
      }
    }

    try {
      return await renderDashboard(res, { error });
    } catch (error) {
      return res.status(500).send('Could not load appointments. Please try again.');
    }
  }

  if (action === 'reschedule') {
    const id = Number(req.body.id || 0);
    const date = String(req.body.date || '');
    const time = String(req.body.start || '');
    const newStart = `${date} ${time}:00`;
    let error = '';

    if (id <= 0) {
      error = 'That appointment could not be found.';
    } else if (date === '' || time === '') {
      error = 'Please choose a date and time.';
    } else {
      try {
        const [rows] = await db.execute(
          "SELECT id, patient_name, patient_email, doctor, slot_start, manage_token"
            + " FROM appointments WHERE id = ? AND status = 'booked'",
          [id]
        );
        const row = rows[0];

        if (!row) {
          error = 'That appointment is no longer booked.';
        } else {
          error = rules.validate_slot(row.doctor, newStart);

          if (error === '' && newStart === row.slot_start) {
            error = 'That is already the appointment time.';
          }

          if (error === '') {
            try {
              const [result] = await db.execute(
                "UPDATE appointments SET slot_start = ?"
                  + " WHERE id = ? AND status = 'booked'",
                [newStart, id]
              );

              if (result.affectedRows === 1) {
                const sent = await sendRescheduleEmail({
                  name: row.patient_name,
                  email: row.patient_email,
                  doctor: row.doctor,
                  start: newStart,
                  code: row.manage_token,
                });

                req.session.flash = `Appointment for ${row.patient_name} moved to ${dateTimeLabel(newStart)}.`
                  + (sent ? ' The patient has been emailed.' : ' The update email could not be sent.');

                return res.redirect('/staff');
              }

              error = 'That appointment is no longer booked.';
            } catch (updateError) {
              if (updateError.code === 'ER_DUP_ENTRY' || updateError.errno === 1062) {
                error = 'That time slot is already booked. Please choose another time.';
              } else {
                console.error('Staff reschedule failed:', updateError.message);
                error = 'Could not reschedule the appointment. Please try again.';
              }
            }
          }
        }
      } catch (queryError) {
        console.error('Staff reschedule failed:', queryError.message);
        error = 'Could not reschedule the appointment. Please try again.';
      }
    }

    try {
      return await renderDashboard(res, { error });
    } catch (error) {
      return res.status(500).send('Could not load appointments. Please try again.');
    }
  }

  return res.redirect('/staff');
});

module.exports = router;
