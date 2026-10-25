# Known issues (out of scope)

## The "email sent" flag is client-controlled

`confirmation.php` takes `sent=1` from the query string. The patient can append
it and make the page claim the confirmation email was sent even if it was not.

Status: out of scope; cosmetic only, with no data or access impact. A session
flag set by `booking.php`, or an `email_sent` column, would make it trustworthy.
