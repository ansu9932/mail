<?php
require __DIR__ . '/SmtpMailer.php';
$config = require __DIR__ . '/config.php';

$sent    = false;
$error   = '';
$to      = '';
$subject = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid recipient email address.';
    } elseif ($subject === '') {
        $error = 'Subject cannot be empty.';
    } elseif ($message === '') {
        $error = 'Message cannot be empty.';
    } else {
        try {
            $mailer = new SmtpMailer(
                $config['SMTP_HOST'],
                $config['SMTP_PORT'],
                $config['SMTP_USER'],
                $config['SMTP_PASS'],
                $config['SMTP_TIMEOUT']
            );

            $mailer->send(
                $config['MAIL_FROM'],
                $config['MAIL_FROM_NAME'],
                $to,
                $subject,
                $message
            );

            $sent = true;
            // Clear fields after a successful send.
            $to = $subject = $message = '';
        } catch (Exception $e) {
            $error = 'Failed to send: ' . $e->getMessage();
        }
    }
}

function h($v)
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Mail</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            width: 100%;
            max-width: 520px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            padding: 32px;
        }
        h1 {
            margin: 0 0 4px;
            font-size: 24px;
            color: #1a202c;
        }
        p.sub {
            margin: 0 0 24px;
            color: #718096;
            font-size: 14px;
        }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }
        input, textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            margin-bottom: 18px;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
        }
        textarea { resize: vertical; min-height: 140px; }
        button {
            width: 100%;
            padding: 13px;
            background: #667eea;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
        }
        button:hover { background: #5a67d8; }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
        .alert.error   { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Send an Email</h1>
        <p class="sub">Powered by Brevo SMTP</p>

        <?php if ($sent): ?>
            <div class="alert success">Your email was sent successfully.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <label for="to">Recipient email</label>
            <input type="email" id="to" name="to" placeholder="someone@example.com"
                   value="<?= h($to) ?>" required>

            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Subject line"
                   value="<?= h($subject) ?>" required>

            <label for="message">Message</label>
            <textarea id="message" name="message" placeholder="Write your message here..."
                      required><?= h($message) ?></textarea>

            <button type="submit">Send Mail</button>
        </form>
    </div>
</body>
</html>
