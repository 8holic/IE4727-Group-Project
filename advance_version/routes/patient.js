const express = require('express');
const crypto = require('crypto');
const db = require('../lib/db');
const rules = require('../lib/rules');
const { requirePatient, destroySession } = require('../lib/auth');
const { isFuture } = require('../lib/format');
const {
  sendBookingEmail,
  sendRescheduleEmail,
  sendCancelEmail,
} = require('../lib/emails');

const router = express.Router();
const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

async function newBookingCode() {
  for (;;) {
    let code = '';
    for (let i = 0; i < 8; i += 1) {
      code += CODE_ALPHABET[crypto.randomInt(CODE_ALPHABET.length)];
    }

    const [rows] = await db.execute(
      'SELECT 1 FROM appointments WHERE manage_token = ?',
      [code]
    );

    if (rows.length === 0) {
      return code;
    }
  }
}

async function loadPatient(id) {
  const [rows] = await db.execute(
    'SELECT name, email, phone FROM patients WHERE id = ?',
    [id]
  );
  return rows[0];
}

async function findAppointment(id, patientId) {
  const [rows] = await db.execute(
    'SELECT id, patient_id, patient_name, patient_email, doctor, slot_start, status'
      + ' FROM appointments WHERE id = ? AND patient_id = ?',
    [id, patientId]
  );
  return rows[0];
}

function renderBooking(res, data) {
  return res.render('booking', {
    title: 'Book an Appointment',
    active: 'booking',
    doctors: rules.doctors,
    doctorDaysText: rules.doctor_days_text,
    timeLabel: rules.time_label,
    openStart: rules.openStart,
    openEnd: rules.openEnd,
    today: rules.todaySGT(),
    maxDate: rules.maxDateSGT(),
    selectedDoctor: '',
    selectedDate: '',
    selectedTime: '',
    available: [],
    scheduleError: '',
    displayError: '',
    patient: null,
    phoneValue: '',
    ...data,
  });
}

router.get('/booking', requirePatient('/booking'), async (req, res) => {
  try {
    const patient = await loadPatient(req.session.user.id);
    if (!patient) {
      return destroySession(req, res, '/login');
    }

    const selectedDoctor = String(req.query.doctor || '');
    const selectedDate = String(req.query.date || '');
    let scheduleError = '';
    let available = [];

    if (selectedDoctor !== '' && selectedDate !== '') {
      scheduleError = rules.schedule_error(selectedDoctor, selectedDate);
      if (scheduleError === '') {
        available = await rules.available_times(db, selectedDoctor, selectedDate);
      }
    }

    return renderBooking(res, {
      selectedDoctor,
      selectedDate,
      available,
      scheduleError,
      displayError: scheduleError,
      patient,
      phoneValue: patient.phone || '',
    });
  } catch (error) {
    console.error('Booking page failed:', error.message);
    return res.status(500).send('Could not load the booking page. Please try again.');
  }
});

router.post('/booking', requirePatient('/booking'), async (req, res) => {
  try {
    const patient = await loadPatient(req.session.user.id);
    if (!patient) {
      return destroySession(req, res, '/login');
    }

    const selectedDoctor = String(req.body.doctor || '').trim();
    const selectedDate = String(req.body.date || '').trim();
    const selectedTime = String(req.body.start || '').trim();
    const phoneValue = String(req.body.phone || '').trim();

    let displayError = '';
    if (phoneValue === '') {
      displayError = 'Please enter your phone number.';
    } else {
      displayError = rules.validate_phone(phoneValue);
    }

    let scheduleError = '';
    let available = [];
    if (selectedDoctor !== '' && selectedDate !== '') {
      scheduleError = rules.schedule_error(selectedDoctor, selectedDate);
      if (scheduleError === '') {
        available = await rules.available_times(db, selectedDoctor, selectedDate);
      }
    }

    if (displayError === '') {
      const start = `${selectedDate} ${selectedTime}:00`;
      displayError = rules.validate_slot(selectedDoctor, start);
    }

    if (displayError === '' && scheduleError === '' && !available.includes(selectedTime)) {
      displayError = 'That appointment time is no longer available. Please choose another time.';
    }

    if (displayError === '') {
      const start = `${selectedDate} ${selectedTime}:00`;

      try {
        const code = await newBookingCode();
        await db.execute(
          'INSERT INTO appointments'
            + ' (patient_id, patient_name, patient_email, phone, doctor, slot_start, manage_token)'
            + ' VALUES (?, ?, ?, ?, ?, ?, ?)',
          [
            req.session.user.id,
            patient.name,
            patient.email,
            phoneValue,
            selectedDoctor,
            start,
            code,
          ]
        );

        try {
          await db.execute(
            'UPDATE patients SET phone = ? WHERE id = ?',
            [phoneValue, req.session.user.id]
          );
        } catch (updateError) {
          console.error('Phone update failed:', updateError.message);
        }

        const sent = await sendBookingEmail({
          name: patient.name,
          email: patient.email,
          doctor: selectedDoctor,
          start,
          code,
        });

        return res.redirect(`/confirmation?code=${encodeURIComponent(code)}&sent=${sent ? '1' : '0'}`);
      } catch (error) {
        if (error.code === 'ER_DUP_ENTRY' || error.errno === 1062) {
          displayError = 'Sorry, that time slot is already booked. Please choose another time.';
        } else {
          console.error('Booking failed:', error.message);
          displayError = 'Could not save the appointment. Please try again.';
        }
      }
    }

    return renderBooking(res, {
      selectedDoctor,
      selectedDate,
      selectedTime,
      available,
      scheduleError,
      displayError: displayError || scheduleError,
      patient,
      phoneValue,
    });
  } catch (error) {
    console.error('Booking failed:', error.message);
    return res.status(500).send('Could not save the appointment. Please try again.');
  }
});

router.get('/confirmation', requirePatient('/my-appointments'), async (req, res) => {
  try {
    const code = String(req.query.code || '').toUpperCase();
    let booking = null;

    if (code !== '') {
      const [rows] = await db.execute(
        'SELECT id, patient_name, patient_email, doctor, slot_start, status'
          + ' FROM appointments WHERE manage_token = ? AND patient_id = ?',
        [code, req.session.user.id]
      );
      booking = rows[0] || null;
    }

    return res.render('confirmation', {
      title: 'Booking Confirmed',
      active: 'booking',
      booking,
      code,
      sent: String(req.query.sent || ''),
    });
  } catch (error) {
    console.error('Confirmation failed:', error.message);
    return res.status(500).send('Could not load the confirmation. Please try again.');
  }
});

router.get('/my-appointments', requirePatient('/my-appointments'), async (req, res) => {
  try {
    const [appointments] = await db.execute(
      'SELECT id, doctor, slot_start, status FROM appointments'
        + ' WHERE patient_id = ? ORDER BY slot_start DESC',
      [req.session.user.id]
    );

    return res.render('my-appointments', {
      title: 'My Appointments',
      active: 'appointments',
      appointments,
      message: String(req.query.message || ''),
    });
  } catch (error) {
    console.error('My appointments failed:', error.message);
    return res.status(500).send('Could not load your appointments. Please try again.');
  }
});

router.get('/manage', requirePatient('/my-appointments'), async (req, res) => {
  const id = Number(req.query.id || 0);

  try {
    const booking = await findAppointment(id, req.session.user.id);
    if (!booking) {
      res.status(404);
      return res.render('manage', {
        title: 'Manage Booking',
        active: 'appointments',
        booking: null,
        error: 'We could not find that appointment on your account.',
        available: [],
        selectedDate: '',
        selectedTime: '',
        minDate: rules.todaySGT(),
        maxDate: rules.maxDateSGT(),
      });
    }

    let selectedDate = String(req.query.date || '');
    if (selectedDate === '') {
      selectedDate = booking.slot_start.slice(0, 10);
    }

    let selectedTime = '';
    if (selectedDate === booking.slot_start.slice(0, 10)) {
      selectedTime = booking.slot_start.slice(11, 16);
    }

    const available = booking.status === 'booked'
      ? await rules.available_times(db, booking.doctor, selectedDate, booking.id)
      : [];

    return res.render('manage', {
      title: 'Manage Booking',
      active: 'appointments',
      booking,
      error: '',
      available,
      selectedDate,
      selectedTime,
      minDate: rules.todaySGT(),
      maxDate: rules.maxDateSGT(),
    });
  } catch (error) {
    console.error('Manage page failed:', error.message);
    return res.status(500).send('Could not load the booking. Please try again.');
  }
});

router.post('/manage', requirePatient('/my-appointments'), async (req, res) => {
  const id = Number(req.body.id || 0);
  const selectedDate = String(req.body.date || '');
  const selectedTime = String(req.body.start || '');

  try {
    const booking = await findAppointment(id, req.session.user.id);
    if (!booking) {
      res.status(404);
      return res.render('manage', {
        title: 'Manage Booking',
        active: 'appointments',
        booking: null,
        error: 'We could not find that appointment on your account.',
        available: [],
        selectedDate: '',
        selectedTime: '',
        minDate: rules.todaySGT(),
        maxDate: rules.maxDateSGT(),
      });
    }

    let error = '';
    if (booking.status !== 'booked') {
      error = 'This appointment can no longer be changed.';
    } else {
      const start = `${selectedDate} ${selectedTime}:00`;
      error = rules.validate_slot(booking.doctor, start);

      if (error === '') {
        const available = await rules.available_times(db, booking.doctor, selectedDate, booking.id);
        if (!available.includes(selectedTime)) {
          error = 'That appointment time is no longer available. Please choose another time.';
        }
      }

      if (error === '' && start === booking.slot_start) {
        error = 'That is already the appointment time.';
      }

      if (error === '') {
        try {
          const [result] = await db.execute(
            "UPDATE appointments SET slot_start = ?"
              + " WHERE id = ? AND patient_id = ? AND status = 'booked'",
            [start, booking.id, req.session.user.id]
          );

          if (result.affectedRows !== 1) {
            error = 'This appointment is no longer booked.';
          } else {
            const sent = await sendRescheduleEmail({
              name: booking.patient_name,
              email: booking.patient_email,
              doctor: booking.doctor,
              start,
            });

            let message = 'Appointment rescheduled.';
            if (!sent) {
              message += ' The confirmation email could not be sent.';
            }

            return res.redirect(`/my-appointments?message=${encodeURIComponent(message)}`);
          }
        } catch (updateError) {
          if (updateError.code === 'ER_DUP_ENTRY' || updateError.errno === 1062) {
            error = 'Sorry, that time slot is already booked. Please choose another time.';
          } else {
            console.error('Reschedule failed:', updateError.message);
            error = 'Could not update the booking. Please try again.';
          }
        }
      }
    }

    let renderDate = selectedDate === '' ? booking.slot_start.slice(0, 10) : selectedDate;
    let renderTime = selectedTime;
    if (renderTime === '' && renderDate === booking.slot_start.slice(0, 10)) {
      renderTime = booking.slot_start.slice(11, 16);
    }

    const available = booking.status === 'booked'
      ? await rules.available_times(db, booking.doctor, renderDate, booking.id)
      : [];

    return res.render('manage', {
      title: 'Manage Booking',
      active: 'appointments',
      booking,
      error,
      available,
      selectedDate: renderDate,
      selectedTime: renderTime,
      minDate: rules.todaySGT(),
      maxDate: rules.maxDateSGT(),
    });
  } catch (error) {
    console.error('Reschedule page failed:', error.message);
    return res.status(500).send('Could not update the booking. Please try again.');
  }
});

router.get('/cancel', requirePatient('/my-appointments'), async (req, res) => {
  const id = Number(req.query.id || 0);

  try {
    const booking = await findAppointment(id, req.session.user.id);
    let error = '';

    if (!booking) {
      res.status(404);
      error = 'We could not find that appointment on your account.';
    } else if (booking.status !== 'booked') {
      error = 'This appointment is no longer booked.';
    } else if (!isFuture(booking.slot_start)) {
      error = 'Past appointments cannot be cancelled.';
    }

    const cancellable = Boolean(booking)
      && booking.status === 'booked'
      && isFuture(booking.slot_start);

    return res.render('cancel', {
      title: 'Cancel Appointment',
      active: 'appointments',
      booking,
      cancellable,
      error,
    });
  } catch (error) {
    console.error('Cancel page failed:', error.message);
    return res.status(500).send('Could not load the appointment. Please try again.');
  }
});

router.post('/cancel', requirePatient('/my-appointments'), async (req, res) => {
  const id = Number(req.body.id || 0);

  try {
    const booking = await findAppointment(id, req.session.user.id);
    let error = '';

    if (!booking) {
      res.status(404);
      error = 'We could not find that appointment on your account.';
    } else if (booking.status !== 'booked') {
      error = 'This appointment is no longer booked.';
    } else if (!isFuture(booking.slot_start)) {
      error = 'Past appointments cannot be cancelled.';
    }

    let cancellable = Boolean(booking)
      && booking.status === 'booked'
      && isFuture(booking.slot_start);

    if (cancellable && req.body.action === 'cancel') {
      const [result] = await db.execute(
        "UPDATE appointments SET status = 'cancelled'"
          + " WHERE id = ? AND patient_id = ? AND status = 'booked'",
        [booking.id, req.session.user.id]
      );

      if (result.affectedRows === 1) {
        const sent = await sendCancelEmail({
          name: booking.patient_name,
          email: booking.patient_email,
          doctor: booking.doctor,
          start: booking.slot_start,
        });

        let message = 'Appointment cancelled.';
        if (!sent) {
          message += ' The cancellation email could not be sent.';
        }

        return res.redirect(`/my-appointments?message=${encodeURIComponent(message)}`);
      }

      error = 'This appointment is no longer booked.';
      cancellable = false;
    }

    return res.render('cancel', {
      title: 'Cancel Appointment',
      active: 'appointments',
      booking,
      cancellable,
      error,
    });
  } catch (error) {
    console.error('Cancel failed:', error.message);
    return res.status(500).send('Could not cancel the appointment. Please try again.');
  }
});

module.exports = router;
