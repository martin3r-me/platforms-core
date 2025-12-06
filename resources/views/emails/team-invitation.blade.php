<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Team-Einladung</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937;">
    <h2 style="color:#111827; margin-bottom: 10px;">Einladung zum Team "{{ $teamName }}"</h2>
    <p style="margin: 0 0 12px 0;">
        {{ $inviterName }} hat dich eingeladen, dem Team "{{ $teamName }}" beizutreten.
    </p>
    <p style="margin: 0 0 12px 0;">
        Klicke auf den folgenden Link, um die Einladung anzunehmen:
    </p>
    <p style="margin: 0 0 16px 0;">
        <a href="{{ $acceptUrl }}" style="background-color:#2563eb;color:#ffffff;padding:10px 14px;border-radius:6px;text-decoration:none;display:inline-block;">
            Einladung annehmen
        </a>
    </p>
    <p style="margin: 0 0 12px 0;">
        Alternativ kannst du diesen Link kopieren und im Browser öffnen:<br>
        <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
    </p>
    <p style="margin-top: 24px; font-size: 12px; color: #6b7280;">
        Wenn du diese Einladung nicht erwartest, kannst du diese E-Mail ignorieren.
    </p>
</body>
</html>

