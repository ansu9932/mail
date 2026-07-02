<?php
require __DIR__ . '/SmtpMailer.php';
$config = require __DIR__ . '/config.php';

$sent    = false;
$error   = '';
$to      = '';
$subject = '';
$message = ''; // raw HTML from the editor

/**
 * Keep a safe subset of HTML so pasted formatting (bold, lists, links...) is
 * preserved, while stripping anything dangerous (scripts, event handlers).
 */
function sanitize_html($html)
{
    // Remove whole script/style/head blocks.
    $html = preg_replace('#<\s*(script|style|head|title|meta|link)[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html);
    $html = preg_replace('#<\s*(script|style|head|title|meta|link)[^>]*/?>#i', '', $html);

    // Allow common formatting tags only.
    $allowed = '<p><br><b><strong><i><em><u><s><strike><ul><ol><li><a>'
             . '<h1><h2><h3><h4><h5><h6><blockquote><span><div><font>'
             . '<table><thead><tbody><tr><td><th><img><pre><code><hr>';
    $html = strip_tags($html, $allowed);

    // Strip inline event handlers (onclick, onerror, ...).
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

    // Neutralise javascript: URLs in href/src.
    $html = preg_replace('#(href|src)\s*=\s*(["\']?)\s*javascript:[^"\'>\s]*#i', '$1=$2#', $html);

    return trim($html);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = sanitize_html($_POST['message'] ?? '');

    // Plain-text version used only to check the message isn't effectively empty.
    $plainCheck = trim(strip_tags(str_replace('&nbsp;', ' ', $message)));

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid recipient email address.';
    } elseif ($subject === '') {
        $error = 'Subject cannot be empty.';
    } elseif ($plainCheck === '') {
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
                $message,
                true // send as HTML so bold/formatting is preserved
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
            max-width: 560px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            padding: 32px;
        }
        h1 { margin: 0 0 4px; font-size: 24px; color: #1a202c; }
        p.sub { margin: 0 0 24px; color: #718096; font-size: 14px; }
        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
        }
        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            margin-bottom: 18px;
            font-family: inherit;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15); }

        /* Rich text editor */
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
            padding: 6px;
            background: #f7fafc;
        }
        .toolbar button {
            width: auto;
            min-width: 34px;
            height: 32px;
            padding: 0 8px;
            background: #fff;
            color: #2d3748;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .toolbar button:hover { background: #edf2f7; }
        .editor {
            border: 1px solid #e2e8f0;
            border-radius: 0 0 10px 10px;
            min-height: 180px;
            max-height: 420px;
            overflow-y: auto;
            padding: 12px 14px;
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 18px;
            outline: none;
        }
        .editor:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15); }
        .editor:empty:before {
            content: attr(data-placeholder);
            color: #a0aec0;
        }
        .editor p { margin: 0 0 10px; }

        .send-btn {
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
        .send-btn:hover { background: #5a67d8; }
        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
        .alert.error   { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Send an Email</h1>
        <p class="sub">Powered by Brevo SMTP &middot; supports bold text &amp; formatting</p>

        <?php if ($sent): ?>
            <div class="alert success">Your email was sent successfully.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="" id="mailForm">
            <label for="to">Recipient email</label>
            <input type="email" id="to" name="to" placeholder="someone@example.com"
                   value="<?= h($to) ?>" required>

            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Subject line"
                   value="<?= h($subject) ?>" required>

            <label>Message</label>
            <div class="toolbar" aria-label="Formatting toolbar">
                <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
                <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
                <button type="button" data-cmd="underline" title="Underline"><u>U</u></button>
                <button type="button" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                <button type="button" data-cmd="insertUnorderedList" title="Bulleted list">&#8226; List</button>
                <button type="button" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                <button type="button" data-cmd="createLink" title="Insert link">Link</button>
                <button type="button" data-cmd="removeFormat" title="Clear formatting">Clear</button>
            </div>
            <div class="editor" id="editor" contenteditable="true"
                 data-placeholder="Write or paste your message here..."><?= $message ?></div>

            <!-- The editor HTML is copied here right before submitting. -->
            <input type="hidden" name="message" id="messageInput">

            <button type="submit" class="send-btn">Send Mail</button>
        </form>
    </div>

    <script>
        var editor = document.getElementById('editor');

        // Toolbar actions.
        document.querySelectorAll('.toolbar button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cmd = this.getAttribute('data-cmd');
                editor.focus();
                if (cmd === 'createLink') {
                    var url = prompt('Enter the link URL:', 'https://');
                    if (url) { document.execCommand(cmd, false, url); }
                } else {
                    document.execCommand(cmd, false, null);
                }
            });
        });

        // Copy the editor content into the hidden field on submit.
        document.getElementById('mailForm').addEventListener('submit', function (e) {
            var text = editor.innerText.replace(/\u00a0/g, ' ').trim();
            if (text === '') {
                e.preventDefault();
                alert('Please write a message before sending.');
                return;
            }
            document.getElementById('messageInput').value = editor.innerHTML;
        });
    </script>
</body>
</html>
