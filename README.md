# Send Mail (PHP + Brevo SMTP)

A simple mail page where you enter a **recipient email**, a **subject**, and a
**message**, and it sends the email through the **Brevo SMTP relay**
(`smtp-relay.brevo.com:587`). No frameworks, no Composer, no build step — just
upload the files to Hostinger and it works.

## Files

| File            | Purpose                                                        |
|-----------------|----------------------------------------------------------------|
| `index.php`     | The form page + send handling                                  |
| `SmtpMailer.php`| Self-contained SMTP client (STARTTLS + AUTH LOGIN)             |
| `config.php`    | Your Brevo credentials and sender settings                     |

## Setup

1. Open `config.php` and fill in:
   - **`SMTP_PASS`** — your Brevo **SMTP key** (NOT your Brevo login password).
     Get it in Brevo: **Settings → SMTP & API → SMTP tab → Generate a new SMTP key**.
   - **`MAIL_FROM`** — an email address that is **verified as a sender** in Brevo
     (**Senders, Domains & Dedicated IPs → Senders**). Brevo rejects unverified
     "From" addresses.
   - **`MAIL_FROM_NAME`** — the display name recipients see.

   The host, port, and login are already set to the values you provided:
   ```
   SMTP_HOST = smtp-relay.brevo.com
   SMTP_PORT = 587
   SMTP_USER = 8a24d3001@smtp-brevo.com
   ```

## Deploy on Hostinger

1. Log in to **hPanel → Files → File Manager**.
2. Go to your site's **`public_html`** folder.
3. Upload `index.php`, `SmtpMailer.php`, and `config.php` there.
   (Or upload a zip and use "Extract".)
4. Visit `https://yourdomain.com/` (or `.../index.php`).
5. Fill in the form and click **Send Mail**.

## Notes & Troubleshooting

- **"Failed to send: SMTP error: expected 235 ..."** → wrong `SMTP_PASS`
  (SMTP key) or wrong login. Re-generate the SMTP key in Brevo and paste it in.
- **"expected 250" after MAIL FROM** → your `MAIL_FROM` address is not a
  verified sender in Brevo.
- **Connection/TLS errors** → make sure outbound port `587` is allowed. On
  Hostinger shared hosting it normally is.
- Keep `config.php` private — it holds your SMTP key. Don't commit real
  credentials to a public repository.
- This sends **plain-text** emails. If you later need HTML emails or
  attachments, the `SmtpMailer` class can be extended for that.
