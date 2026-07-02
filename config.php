<?php
/**
 * Brevo (Sendinblue) SMTP configuration.
 *
 * IMPORTANT:
 *  - SMTP_PASS is your Brevo "SMTP key / master password", NOT your Brevo
 *    account login password. Get it from Brevo dashboard:
 *    Settings -> SMTP & API -> SMTP tab -> "Generate a new SMTP key".
 *  - MAIL_FROM must be an email address that is verified as a sender in Brevo
 *    (Senders, Domains & Dedicated IPs -> Senders). Otherwise Brevo will reject it.
 */

return [
    // Brevo SMTP relay settings
    'SMTP_HOST' => 'smtp-relay.brevo.com',
    'SMTP_PORT' => 587,
    'SMTP_USER' => '8a24d3001@smtp-brevo.com',

    // >>> REPLACE THIS with your Brevo SMTP key <<<
    'SMTP_PASS' => 'PUT-YOUR-BREVO-SMTP-KEY-HERE',

    // The "From" address shown to recipients. Must be a verified sender in Brevo.
    'MAIL_FROM'      => 'your-verified-sender@yourdomain.com',
    'MAIL_FROM_NAME' => 'My Website',

    // Connection timeout in seconds
    'SMTP_TIMEOUT' => 30,
];
