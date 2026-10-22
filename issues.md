# Known issues (out of scope)

## Non-grid appointment times can be booked directly

The booking page only offers 20-minute slots, but the server validates only
that the time falls between opening and closing. A hand-made POST such as
`start=2026-10-23 11:01:00` is accepted, even though the UI cannot produce it.

Status: out of scope for the assignment. If needed later, reject times whose
minutes are not `00`, `20` or `40` in `validate_slot()`.

## The "email sent" flag is client-controlled

`confirmation.php` takes `sent=1` from the query string. Anyone with a booking
code can append it and make the page claim the confirmation email was sent
even if it was not.

Status: out of scope; cosmetic only, with no data or access impact. A session
flag set by `api/db.php`, or an `email_sent` column, would make it trustworthy.
